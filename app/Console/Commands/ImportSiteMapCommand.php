<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use App\Support\Content\SiteMapParser;
use Illuminate\Console\Command;

/**
 * Site haritası belgesini sayfa kütüğüne aktarır — FF-117, yönerge Faz 1.
 *
 * KOMUT YIKICI DEĞİLDİR. Var olan bir kaydın yayın durumuna, yayın tarihine ya
 * da geçmişine DOKUNMAZ; yalnız kütükte olmayan yolları `planned` olarak ekler
 * ve başlık/öncelik gibi belgeden gelen alanları tazeler. Aksi hâlde belgeyi
 * her yeniden içe aktarmak, yayındaki sayfaları taslağa düşürürdü.
 */
final class ImportSiteMapCommand extends Command
{
    protected $signature = 'site:import-map {--file=docs/106-SITE-MAP-INPUT.md} {--dry-run}';

    protected $description = 'Site haritası belgesindeki canonical yolları sayfa kütüğüne aktarır.';

    public function handle(): int
    {
        $path = base_path((string) $this->option('file'));

        if (! is_file($path)) {
            $this->error("Site haritası bulunamadı: {$path}");

            return self::FAILURE;
        }

        $rows = SiteMapParser::parse((string) file_get_contents($path));

        if ($rows === []) {
            $this->error('Site haritasında hiç canonical yol bulunamadı.');

            return self::FAILURE;
        }

        /** @var array<string, string> $sourcePaths */
        $sourcePaths = (array) config('site-source-paths');
        $sourceLocale = (string) config('i18n.source_locale');

        $created = 0;
        $refreshed = 0;

        foreach ($rows as $row) {
            $pageKey = $row['page_key'];

            $shared = [
                'parent_key' => $row['parent_path'] === null
                    ? null
                    : SiteMapParser::pageKeyFor($row['parent_path']),
                'priority' => $row['priority'],
                'is_template' => $row['is_template'],
                'is_external' => $row['is_external'],
            ];

            /*
                BELGENİN KENDİ DİLİ. Girdi Türkçe yollarla yazılmış ve
                düzenlenmiyor (`docs/118`); dolayısıyla belgeden çıkan satır
                Türkçe bir satırdır ve öyle kalır. Bugünkü 386 kaydın
                anahtarı, adresi ve geçmişi bu yüzden hiç kıpırdamıyor.
            */
            $paths = [
                self::localeOf($row['canonical_path']) => [
                    'path' => $row['canonical_path'],
                    'title' => $row['title'],
                ],
            ];

            /*
                KAYNAK DİL SATIRI. Belge onu veremez — kaynak dil artık
                İngilizce ama girdi Türkçe (`docs/118` E4) — bu yüzden adres
                deponun kendi kayıt dosyasından gelir. Adresi yazılmamış bir
                sayfanın kaynak satırı ÜRETİLMEZ: makineyle türetilmiş bir
                `/en/urun/...` yarım çevrilmiş bir adres olurdu ve bir adres
                yayımlandıktan sonra geri alınamaz (`config/site-source-paths.php`).
            */
            if (isset($sourcePaths[$pageKey]) && ! isset($paths[$sourceLocale])) {
                $paths[$sourceLocale] = [
                    'path' => $sourcePaths[$pageKey],
                    /*
                        Başlık BELGEDEN gelir ve Türkçedir. Çevirmek bu
                        paketin açıkça yasakladığı şey; uydurmak daha da
                        kötü olurdu. Yer tutucu, sayfanın İngilizce içeriği
                        yazıldığı gün `PageMetadata` tarafından zaten
                        eziliyor: ziyaretçinin gördüğü başlık kütükten değil
                        içerik katmanından gelir (`ShowCorporatePageController`).
                    */
                    'title' => $row['title'],
                ];
            }

            foreach ($paths as $locale => $localized) {
                $attributes = $shared + [
                    'locale' => $locale,
                    'canonical_path' => $localized['path'],
                    'content_type' => self::contentTypeOf($row['canonical_path']),
                    'template_key' => self::contentTypeOf($row['canonical_path']),
                    'title' => $localized['title'],
                ];

                /*
                    Kayıt ARTIK `page_key + locale` ile bulunur. Yalnız
                    anahtarla aramak, aynı sayfanın iki dilini birbirinin
                    üstüne yazardı — kütüğün çok dilli olmasının anlamı da
                    tam olarak bu ikisinin ayrı satırlar olması.
                */
                $existing = ContentPage::query()
                    ->where('page_key', $pageKey)
                    ->where('locale', $locale)
                    ->first();

                if ($this->option('dry-run')) {
                    $existing === null ? $created++ : $refreshed++;

                    continue;
                }

                if ($existing === null) {
                    ContentPage::query()->create($attributes + [
                        'page_key' => $pageKey,
                        // Her sayfa PLANLANDI olarak doğar. "Yayında" bir başlangıç
                        // değeri olsaydı, 414 boş sayfa aynı anda yayına çıkardı.
                        'publication_status' => PagePublicationStatus::Planned->value,
                        'was_ever_published' => false,
                    ]);
                    $created++;

                    continue;
                }

                // Yayın durumu, yayın tarihi ve geçmiş KORUNUR.
                $existing->fill($attributes)->save();
                $refreshed++;
            }
        }

        $this->info("site:import-map — {$created} yeni, {$refreshed} tazelendi.");

        return self::SUCCESS;
    }

    /** Dil, yolun ilk segmentinden okunur; yoksa varsayılan Türkçedir. */
    private static function localeOf(string $path): string
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';

        return in_array($first, ['tr', 'en'], true) ? $first : 'tr';
    }

    /**
     * Sayfa türü yolun İKİNCİ segmentinden okunur (`/tr/urun/...` → `urun`).
     *
     * Tür, hangi şablonun kullanılacağını belirler; yönergenin §7'sindeki
     * "414 route için 414 layout üretme" kuralının uygulanışı budur.
     */
    private static function contentTypeOf(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        if ($segments !== [] && in_array($segments[0], ['tr', 'en'], true)) {
            array_shift($segments);
        }

        return $segments[0] ?? 'home';
    }
}
