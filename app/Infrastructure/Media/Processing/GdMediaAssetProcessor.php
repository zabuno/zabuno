<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Processing;

use App\Application\Media\Dto\GeneratedRendition;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SlotPolicy;
use GdImage;

/**
 * Gerçek görsel işleyici (`docs/76`, P0-08).
 *
 * GD SEÇİLDİ çünkü PHP ile birlikte gelir: geliştirme makinesinde, CI'da ve
 * sunucuda ekstra bir eklenti kurulumu gerektirmez. Imagick daha yetenekli
 * ama her ortamda yok; "bazı ortamlarda çalışan" bir işleyici, hiç çalışmayan
 * bir işleyicinin daha sinsi hâlidir.
 *
 * GD'nin sınırı DÜRÜSTÇE bildirilir: HEIC çözemez. Telefondan gelen
 * fotoğrafların çoğu HEIC'tir, dolayısıyla bu sık karşılaşılacak bir yol ve
 * sahip "yükledim, bir şey olmadı" ile bırakılamaz — okunabilir bir sebep
 * alır.
 */
final class GdMediaAssetProcessor implements MediaAssetProcessorPort
{
    public function __construct(private readonly SlotCatalogue $slots) {}

    public function process(string $absolutePath, string $slot = '', ?string $targetFormat = null): MediaProcessingResult
    {
        if ($targetFormat !== null) {
            $refusal = $this->refuseTargetFormat($targetFormat, $slot);

            if ($refusal !== null) {
                return MediaProcessingResult::failed($refusal);
            }
        }

        if (! is_readable($absolutePath)) {
            return MediaProcessingResult::failed('Yüklenen dosya okunamadı. Lütfen yeniden yükleyin.');
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false || $bytes === '') {
            return MediaProcessingResult::failed('Yüklenen dosya boş görünüyor. Lütfen yeniden yükleyin.');
        }

        $probe = @getimagesizefromstring($bytes);

        if ($probe === false) {
            return MediaProcessingResult::failed($this->undecodableReason($bytes));
        }

        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            return MediaProcessingResult::failed($this->undecodableReason($bytes));
        }

        try {
            $policy = $this->slots->has($slot) ? $this->slots->get($slot) : null;

            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            if ($sourceWidth < 1 || $sourceHeight < 1) {
                return MediaProcessingResult::failed('Görselin ölçüleri okunamadı.');
            }

            $renditions = [];

            foreach ($this->targetWidths($policy, $sourceWidth) as $width) {
                $rendition = $this->renderAt($source, $policy, $sourceWidth, $sourceHeight, $width, $targetFormat);

                if ($rendition === null) {
                    return MediaProcessingResult::failed(
                        'Görsel türevi üretilemedi. Dosya bozuk olabilir; farklı bir görsel deneyin.'
                    );
                }

                $renditions[] = $rendition;
            }

            if ($renditions === []) {
                return MediaProcessingResult::failed('Bu görselden hiçbir boyut üretilemedi.');
            }

            return MediaProcessingResult::succeeded($renditions, $sourceWidth, $sourceHeight, $this->lqip($source, $sourceWidth, $sourceHeight));
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Hedef genişlikler slot politikasından gelir, KAYNAKLA sınırlanır.
     *
     * Upscale yasaktır (INV-01): 500 px'lik bir fotoğraftan 960 px üretmek
     * bilgi eklemez, sadece menüde bulanık bir görsel yaratır. Kaynaktan
     * büyük her hedef kaynağın kendi ölçüsüne kırpılır ve yinelenenler
     * düşer.
     *
     * @return list<int>
     */
    private function targetWidths(?SlotPolicy $policy, int $sourceWidth): array
    {
        $requested = $policy?->renditions ?? [320, 640, 960];

        $capped = array_map(static fn (int $width): int => min($width, $sourceWidth), $requested);
        $unique = array_values(array_unique(array_filter($capped, static fn (int $w): bool => $w > 0)));
        sort($unique);

        return $unique;
    }

    private function renderAt(
        GdImage $source,
        ?SlotPolicy $policy,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        ?string $targetFormat = null,
    ): ?GeneratedRendition {
        [$cropX, $cropY, $cropWidth, $cropHeight] = $this->cropBox($policy, $sourceWidth, $sourceHeight);

        $targetHeight = max(1, (int) round($targetWidth * ($cropHeight / $cropWidth)));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof GdImage) {
            return null;
        }

        try {
            $preserveAlpha = ($policy?->transparency ?? 'flatten') === 'preserve';

            if ($preserveAlpha) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            } else {
                // Saydamlık düzleştirilecekse zemin BEYAZ olur; siyah zemin
                // koyu bir yemek fotoğrafının kenarlarını yutar.
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, imagecolorallocate($canvas, 255, 255, 255));
            }

            $resampled = imagecopyresampled(
                $canvas, $source,
                0, 0, $cropX, $cropY,
                $targetWidth, $targetHeight, $cropWidth, $cropHeight,
            );

            if ($resampled === false) {
                return null;
            }

            [$format, $mimeType, $encoded] = $this->encode($canvas, $preserveAlpha, $targetFormat);

            if ($encoded === null) {
                return null;
            }

            return new GeneratedRendition(
                profile: (string) $targetWidth.'w',
                width: $targetWidth,
                height: $targetHeight,
                format: $format,
                mimeType: $mimeType,
                bytes: $encoded,
            );
        } finally {
            imagedestroy($canvas);
        }
    }

    /**
     * Slotun oranı sabitse kaynak ORTADAN kırpılır: yemek fotoğraflarında
     * konu neredeyse her zaman merkezdedir ve kenardan kırpmak tabağın
     * yarısını keser.
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function cropBox(?SlotPolicy $policy, int $sourceWidth, int $sourceHeight): array
    {
        $aspect = $policy?->aspect;

        if ($aspect === null || ! str_contains($aspect, ':')) {
            return [0, 0, $sourceWidth, $sourceHeight];
        }

        [$left, $right] = array_map('floatval', explode(':', $aspect, 2));

        if ($left <= 0.0 || $right <= 0.0) {
            return [0, 0, $sourceWidth, $sourceHeight];
        }

        $wanted = $left / $right;
        $actual = $sourceWidth / $sourceHeight;

        if (abs($wanted - $actual) < 0.001) {
            return [0, 0, $sourceWidth, $sourceHeight];
        }

        if ($actual > $wanted) {
            $cropWidth = max(1, (int) round($sourceHeight * $wanted));

            return [(int) round(($sourceWidth - $cropWidth) / 2), 0, $cropWidth, $sourceHeight];
        }

        $cropHeight = max(1, (int) round($sourceWidth / $wanted));

        return [0, (int) round(($sourceHeight - $cropHeight) / 2), $sourceWidth, $cropHeight];
    }

    /**
     * Saydamlık korunacaksa PNG: WebP'nin GD'deki kaybı alfa kenarlarında
     * görünür ve logo bir kez üretilir, milyon kez gösterilir. Diğer her
     * yerde WebP, çünkü aynı görünürlükte belirgin biçimde küçüktür —
     * misafirin mobil verisi bizim tercihimiz değil, onun faturası.
     *
     * @return array{0:string,1:string,2:string|null}
     */
    /**
     * LQIP: 16 px genişlikte, düşük kaliteli JPEG, data URI (~300 bayt).
     * Misafir fotoğraf inene kadar boş kutu değil, yemeğin rengini görür.
     * Üretilemezse null — yer tutucu süs, başarısızlığı boru hattını
     * durdurmaz.
     */
    private function lqip(GdImage $source, int $sourceWidth, int $sourceHeight): ?string
    {
        $width = 16;
        $height = max(1, (int) round($sourceHeight * $width / max(1, $sourceWidth)));
        $tiny = imagecreatetruecolor($width, $height);
        imagecopyresampled($tiny, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        $bytes = $this->capture(static fn (): bool => imagejpeg($tiny, null, 40));
        imagedestroy($tiny);

        return $bytes === null || $bytes === '' ? null : 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    private function encode(GdImage $canvas, bool $preserveAlpha, ?string $targetFormat = null): array
    {
        /*
            HEDEF BİÇİM SEÇİLDİYSE O BİÇİM ÜRETİLİR — yedeğe düşülmez.
            "İstediğini yapamadım, onun yerine şunu yaptım" bir dönüştürme
            ekranında sessiz bir yalandır: sahip AVIF'e bastığında listede
            AVIF görmeli, ya da hiçbir şey görmemeli. Buraya gelindiğinde
            biçimin üretilebilirliği `refuseTargetFormat` ile zaten
            sorulmuştur; yine de kodlayıcı boş çıktı verirse `null` döner ve
            iş başarısız SAYILIR.
        */
        if ($targetFormat !== null) {
            return match ($targetFormat) {
                'avif' => ['avif', 'image/avif', $this->capture(static fn (): bool => imageavif($canvas, null, 62))],
                'webp' => ['webp', 'image/webp', $this->capture(static fn (): bool => imagewebp($canvas, null, 82))],
                'jpeg' => ['jpeg', 'image/jpeg', $this->capture(static fn (): bool => imagejpeg($canvas, null, 82))],
                default => [$targetFormat, 'application/octet-stream', null],
            };
        }

        if ($preserveAlpha) {
            return ['png', 'image/png', $this->capture(static fn (): bool => imagepng($canvas, null, 6))];
        }

        if (function_exists('imagewebp') && (bool) (gd_info()['WebP Support'] ?? false)) {
            $webp = $this->capture(static fn (): bool => imagewebp($canvas, null, 82));

            if ($webp !== null) {
                return ['webp', 'image/webp', $webp];
            }
        }

        // WebP yoksa ürün DURMAZ: JPEG her GD derlemesinde vardır.
        return ['jpeg', 'image/jpeg', $this->capture(static fn (): bool => imagejpeg($canvas, null, 82))];
    }

    /** @param  callable():bool  $writer */
    private function capture(callable $writer): ?string
    {
        ob_start();
        $ok = $writer();
        $bytes = (string) ob_get_clean();

        return ($ok && $bytes !== '') ? $bytes : null;
    }

    /**
     * DÖNÜŞTÜRME REDDİ — dosyaya dokunmadan ÖNCE, okunabilir bir cümleyle.
     *
     * İki sebep var ve ikisi de sessizce yedeğe düşmektense söylenmelidir:
     *
     *   1. Bu sunucu o biçimi kodlayamıyor. Uç zaten desteklenmeyen hedefi
     *      reddediyor; burası son kapı — başka bir çağıran doğrudan gelirse
     *      ürün yanlış biçim üretip "oldu" dememeli.
     *   2. Saydam bir görsel JPEG'e çevriliyor. JPEG saydamlık taşımaz;
     *      logo beyaz bir kutunun içine düşer. Asıl korunduğu için geri
     *      dönüş vardır ama sahip bunu ancak menüde görürdü — ve o kadar
     *      geç bir fark ediş, ürünün hatasıdır.
     */
    private function refuseTargetFormat(string $targetFormat, string $slot): ?string
    {
        if (! (new RuntimeMediaFormatSupport)->supports($targetFormat)) {
            return 'Bu sunucu '.strtoupper($targetFormat).' üretemiyor; dosyaya dokunulmadı.';
        }

        $preservesAlpha = $this->slots->has($slot)
            && $this->slots->get($slot)->transparency === 'preserve';

        if ($preservesAlpha && $targetFormat === 'jpeg') {
            return 'Saydam bir görsel JPEG\'e çevrilemez: saydamlık beyaz zemine dönerdi. '
                .'AVIF ya da WebP saydamlığı korur.';
        }

        return null;
    }

    private function undecodableReason(string $bytes): string
    {
        // HEIC/HEIF, iPhone'un varsayılan formatıdır ve GD onu çözemez.
        // Genel bir "desteklenmeyen dosya" cümlesi burada işe yaramaz:
        // sahip ne yapacağını bilemez.
        if (str_contains(substr($bytes, 0, 32), 'ftyp')) {
            return 'Bu fotoğraf HEIC biçiminde ve sunucu onu okuyamıyor. '
                .'Telefonunuzun kamera ayarlarından "En Uyumlu" (JPEG) seçeneğini '
                .'kullanabilir veya fotoğrafı JPEG olarak paylaşıp yeniden yükleyebilirsiniz.';
        }

        return 'Bu dosya bir görsel olarak okunamadı. JPEG, PNG veya WebP bir dosya yükleyin.';
    }
}
