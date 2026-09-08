<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Domain\Authorization\Permission;

/**
 * BİR İZİN GERÇEKTEN BİR ŞEYİ KORUYOR MU — ölçüm, iddia değil.
 *
 * `docs/111` §4.1'in kuralı: *"Rozet gözlemsiz çizilmez."* Bir yetki
 * matrisinde bunun karşılığı şudur: "Sahip menüyü yayınlayabilir" cümlesi,
 * `menu.publish` diye bir enum satırının VARLIĞINDAN çıkmaz. O satırın
 * kodda bir yerde SORULUYOR olması gerekir; sorulmayan bir izin, ne
 * verildiğinde bir şey açar ne de esirgendiğinde bir şey kapatır.
 *
 * Bu tarayıcı her izin için tek bir gözlem üretir: `app/` altında o enum
 * satırını okuyan dosyalar. Yorum satırlarını ayıklamaz ve buna gerek de
 * yok — kapı (`AuthorizationMatrixArtifactTest`) yalnız "en az bir yerde
 * geçiyor mu" diye sorar; sayı belgede ölçüm olarak yazılır, garanti olarak
 * değil.
 *
 * `app/Domain/Authorization` DIŞARIDA bırakılır: izni TANIMLAYAN dosyanın
 * kendisi, o iznin uygulandığının kanıtı değildir. Bu ayrım olmasaydı her
 * izin en az bir eşleşmeyle doğar ve kapı hiçbir zaman kırılmazdı.
 */
final class PermissionEnforcementScan
{
    /** Kendini kanıt sayamayacak dizin: izinlerin tanımlandığı yer. */
    private const EXCLUDED_PREFIX = 'app/Domain/Authorization/';

    public function __construct(private readonly string $basePath) {}

    /**
     * İzin anahtarı → onu okuyan dosya yolları (depo köküne göre, sıralı).
     *
     * @return array<string, list<string>>
     */
    public function sites(): array
    {
        $files = $this->phpFiles($this->basePath.'/app');
        $sources = [];

        foreach ($files as $file) {
            $relative = $this->relative($file);

            if (str_starts_with($relative, self::EXCLUDED_PREFIX)) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $sources[$relative] = $contents;
        }

        ksort($sources);

        $sites = [];

        foreach (Permission::cases() as $permission) {
            $needle = 'Permission::'.$permission->name;
            $hits = [];

            foreach ($sources as $relative => $contents) {
                /*
                    Sınır kontrolü şart: `Permission::Menu` araması
                    `Permission::MenuManage`'i de yakalardı ve `menu.view`
                    hiç kullanılmadığı hâlde kullanılıyor görünürdü. Bir
                    ölçüm aracının yapabileceği en kötü şey, ölçtüğünü
                    sandığından fazlasını saymaktır.
                */
                if (preg_match('/'.preg_quote($needle, '/').'(?![A-Za-z0-9_])/', $contents) === 1) {
                    $hits[] = $relative;
                }
            }

            $sites[$permission->value] = $hits;
        }

        return $sites;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];

        /** @var list<string> $entries */
        $entries = scandir($directory) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                $found = [...$found, ...$this->phpFiles($path)];

                continue;
            }

            if (str_ends_with($entry, '.php')) {
                $found[] = $path;
            }
        }

        return $found;
    }

    private function relative(string $path): string
    {
        $prefix = $this->basePath.'/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
