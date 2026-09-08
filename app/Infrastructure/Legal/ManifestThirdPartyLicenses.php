<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\Port\ThirdPartyLicensePort;
use App\Domain\Legal\DependencyInventory;
use App\Domain\Legal\DependencyLicense;
use Throwable;

/**
 * Lisans listesi manifestlerden TÜRETİLİR — FF-228, `docs/140` §5.
 *
 * ═══ NEDEN ELLE YAZILMADI ═══
 *
 * Elle yazılmış bir lisans listesi, bir `composer require` ya da
 * `npm install` ile — hiçbir uyarı vermeden — yanlışa döner. Bu depoda
 * bağımlılık eklemek sıradan bir iştir; listeyi elle güncellemeyi hatırlamak
 * ise değildir. Bu yüzden kaynak, deponun zaten TEK gerçeği olan dosyalardır:
 * `composer.json` + `composer.lock` ve `package.json` + `package-lock.json`.
 *
 * ═══ NEDEN YALNIZ DOĞRUDAN BAĞIMLILIKLAR LİSTELENİYOR ═══
 *
 * Kilit dosyalarında yüzlerce dolaylı paket var. Hepsini bir hukuk sayfasına
 * basmak okunmayacak bir duvar üretir ve o duvar, gerçekten dikkat edilmesi
 * gereken doğrudan bağımlılığı görünmez kılardı. Sayfa doğrudan olanları
 * ADIYLA sayar, dolaylı olanları SAYAR (kaç tane) ve tam listenin kilit
 * dosyasında olduğunu söyler. Bir sayım da bir olgudur; "birçok" değildir.
 *
 * ═══ OKUNAMAYAN DOSYA "BOŞ" DEĞİLDİR ═══
 *
 * Bir kilit dosyası dağıtımda bulunmayabilir (`package-lock.json` bir üretim
 * imajına kopyalanmayabilir). O durumda envanter `readable: false` taşır ve
 * belge bunu söyler; boş bir liste "hiçbir üçüncü taraf kod yok" diye
 * okunurdu ve bu, bu ürün için apaçık yanlıştır.
 *
 * ═══ SÜRÜM SABİTİ YOK ═══
 *
 * Lisans metinlerinin KENDİSİ buraya kopyalanmaz. Bir MIT ya da Apache-2.0
 * metnini çoğaltmak, kopyanın bir gün asıl metinden ayrışması demektir;
 * belge lisans ADINI ve paketin sürümünü söyler, metin paketin kendi
 * dağıtımındadır.
 */
final class ManifestThirdPartyLicenses implements ThirdPartyLicensePort
{
    /** @return list<DependencyInventory> */
    public function inventories(): array
    {
        return [$this->composer(), $this->npm()];
    }

    private function composer(): DependencyInventory
    {
        $manifest = $this->json(base_path('composer.json'));
        $lock = $this->json(base_path('composer.lock'));

        if ($manifest === null || $lock === null) {
            return new DependencyInventory('PHP (Composer)', 'composer.json / composer.lock', [], 0, false);
        }

        /*
            `php` ve `ext-*` bir üçüncü taraf paket DEĞİLDİR: biri dilin
            kendisi, diğerleri sunucuda kurulu eklentiler. Onları lisans
            listesine yazmak, lisansı olmayan bir şeye lisans aramak olurdu.
        */
        $direct = [];

        foreach (array_keys((array) ($manifest['require'] ?? [])) as $name) {
            $name = (string) $name;

            if ($name === 'php' || str_starts_with($name, 'ext-')) {
                continue;
            }

            $direct[$name] = true;
        }

        $packages = [];
        $transitive = 0;

        foreach ((array) ($lock['packages'] ?? []) as $package) {
            if (! is_array($package) || ! isset($package['name'])) {
                continue;
            }

            $name = (string) $package['name'];

            if (! isset($direct[$name])) {
                $transitive++;

                continue;
            }

            $packages[] = new DependencyLicense(
                $name,
                (string) ($package['version'] ?? ''),
                $this->license($package['license'] ?? null),
            );
        }

        return new DependencyInventory(
            'PHP (Composer)',
            'composer.json / composer.lock',
            $this->sorted($packages),
            $transitive,
        );
    }

    private function npm(): DependencyInventory
    {
        $manifest = $this->json(base_path('package.json'));
        $lock = $this->json(base_path('package-lock.json'));

        if ($manifest === null || $lock === null) {
            return new DependencyInventory('JavaScript (npm)', 'package.json / package-lock.json', [], 0, false);
        }

        /*
            YALNIZ `dependencies`. `devDependencies` derleme araçlarıdır
            (Vite, ESLint, Storybook, TypeScript); ürünün tarayıcıya gönderdiği
            pakette yer almazlar ve dağıtılmayan bir kod için lisans
            bildirimi yapmak, listeyi gürültüyle şişirirdi. Sayfa bunu açıkça
            söyler.
        */
        $direct = [];

        foreach (array_keys((array) ($manifest['dependencies'] ?? [])) as $name) {
            $direct[(string) $name] = true;
        }

        $packages = [];
        $transitive = 0;

        foreach ((array) ($lock['packages'] ?? []) as $path => $package) {
            $path = (string) $path;

            // Kök giriş (`""`) projenin kendisidir, bir bağımlılık değil.
            if ($path === '' || ! is_array($package)) {
                continue;
            }

            /*
                DOĞRUDAN OLAN, YALNIZ ÜST DÜZEY KOPYADIR.

                npm bir paketin ikinci bir sürümünü iç içe kurar
                (`node_modules/a/node_modules/tailwind-merge`). O kopya
                manifestte istenen paket DEĞİL, başka bir paketin
                bağımlılığıdır; adı aynı diye "doğrudan" saymak, sayfada aynı
                paketi üç kez farklı sürümle listelemek olurdu — ölçüldü.
            */
            $name = $this->nameFromPath($path);
            $isTopLevel = $path === 'node_modules/'.$name;

            if (! $isTopLevel || ! isset($direct[$name])) {
                $transitive++;

                continue;
            }

            $packages[] = new DependencyLicense(
                $name,
                (string) ($package['version'] ?? ''),
                $this->license($package['license'] ?? ($package['licenses'] ?? null)),
            );
        }

        return new DependencyInventory(
            'JavaScript (npm)',
            'package.json / package-lock.json',
            $this->sorted($packages),
            $transitive,
        );
    }

    /** `node_modules/@scope/name` → `@scope/name`; iç içe yollarda son parça. */
    private function nameFromPath(string $path): string
    {
        $position = strrpos($path, 'node_modules/');

        return $position === false ? $path : substr($path, $position + strlen('node_modules/'));
    }

    /**
     * Lisans alanı dize, dizi ya da nesne dizisi olabilir; hiçbiri değilse
     * `null` döner ve satır "bildirilmemiş" yazar.
     */
    private function license(mixed $raw): ?string
    {
        if (is_string($raw)) {
            return trim($raw) === '' ? null : trim($raw);
        }

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $names = [];

        foreach ($raw as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $names[] = trim($entry);

                continue;
            }

            if (is_array($entry) && isset($entry['type']) && is_string($entry['type'])) {
                $names[] = trim($entry['type']);
            }
        }

        return $names === [] ? null : implode(' OR ', $names);
    }

    /**
     * @param  list<DependencyLicense>  $packages
     * @return list<DependencyLicense>
     */
    private function sorted(array $packages): array
    {
        usort($packages, static fn (DependencyLicense $a, DependencyLicense $b): int => strcmp($a->name, $b->name));

        return array_values($packages);
    }

    /** @return array<string, mixed>|null */
    private function json(string $path): ?array
    {
        try {
            if (! is_file($path) || ! is_readable($path)) {
                return null;
            }

            $raw = file_get_contents($path);

            if ($raw === false) {
                return null;
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            // Okunamayan bir manifest bir sayfayı 500 yapmamalı: belge
            // "okunamadı" der ve okunabilir kalır.
            return null;
        }
    }
}
