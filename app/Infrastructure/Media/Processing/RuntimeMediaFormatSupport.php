<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Port\MediaFormatSupportPort;
use App\Domain\Media\ConversionTargetCatalogue;

/**
 * Yeteneği VARSAYMAZ, SUNUCUYA SORAR.
 *
 * GD, PHP ile birlikte gelir ama içindeki kodlayıcılar DERLEME ZAMANINDA
 * belirlenir: aynı PHP sürümü bir makinede AVIF üretir, diğerinde üretmez.
 * `imageavif()` fonksiyonunun varlığı da tek başına yetmez — fonksiyon
 * tanımlı olup `gd_info()` "AVIF Support" demiyorsa çağrı boş çıktı verir.
 * O yüzden İKİSİ birden sorulur.
 *
 * VİDEO. `webm` burada asla desteklenmez ve sebebi bir ikili dosyanın
 * yokluğu DEĞİLDİR: ürünün video çözen/kodlayan bir hattı hiç yok
 * (`MediaAssetProcessorPort`in bütün uygulamaları raster görsel işler).
 * Makinede ffmpeg bulunsa bile onu çağıracak bir kod olmadığı için
 * "ffmpeg yok" demek yanlış olurdu; dürüst cevap `no-video-pipeline`.
 * Kaynak WebM'i listeliyor, ürün onu yapamıyor — fark ekranda yazılır.
 */
final class RuntimeMediaFormatSupport implements MediaFormatSupportPort
{
    public function supports(string $format): bool
    {
        return $this->limitation($format) === null;
    }

    public function limitation(string $format): ?string
    {
        $target = ConversionTargetCatalogue::canonical()->find($format);

        if ($target === null) {
            return 'unknown-format';
        }

        if ($target->isVideo()) {
            return 'no-video-pipeline';
        }

        return $this->canEncode($format) ? null : 'encoder-missing';
    }

    private function canEncode(string $format): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $info = gd_info();

        return match ($format) {
            // JPEG her GD derlemesinde vardır; yine de sorulur — "vardır"
            // varsayımı tam olarak bu dosyanın kaldırmak istediği şeydir.
            'jpeg' => function_exists('imagejpeg') && (bool) ($info['JPEG Support'] ?? false),
            'webp' => function_exists('imagewebp') && (bool) ($info['WebP Support'] ?? false),
            'avif' => function_exists('imageavif') && (bool) ($info['AVIF Support'] ?? false),
            default => false,
        };
    }
}
