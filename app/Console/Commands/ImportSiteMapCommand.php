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

        $created = 0;
        $refreshed = 0;

        foreach ($rows as $row) {
            $existing = ContentPage::query()->where('page_key', $row['page_key'])->first();

            $attributes = [
                'locale' => self::localeOf($row['canonical_path']),
                'canonical_path' => $row['canonical_path'],
                'content_type' => self::contentTypeOf($row['canonical_path']),
                'template_key' => self::contentTypeOf($row['canonical_path']),
                'parent_key' => $row['parent_path'] === null
                    ? null
                    : SiteMapParser::pageKeyFor($row['parent_path']),
                'title' => $row['title'],
                'priority' => $row['priority'],
                'is_template' => $row['is_template'],
                'is_external' => $row['is_external'],
            ];

            if ($this->option('dry-run')) {
                $existing === null ? $created++ : $refreshed++;

                continue;
            }

            if ($existing === null) {
                ContentPage::query()->create($attributes + [
                    'page_key' => $row['page_key'],
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
