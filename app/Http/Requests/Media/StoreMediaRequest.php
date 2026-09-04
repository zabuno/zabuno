<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SvgSanitizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreMediaRequest extends FormRequest
{
    /**
     * Real, decode-free magic-byte check for images — reads the file's own
     * bytes rather than its client extension or reported MIME type, so a
     * spoofed extension (e.g. a PHP payload renamed to .jpg) is rejected
     * before it ever reaches storage (MEDIA-INTAKE-MIME-SPOOF-REJECT-01).
     */
    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Sınırlar config'den (`media-slots.limits`) — 2026-09-04'e kadar
        // burada sabit `max:51199` (50 MB) yazıyordu, config ise 30 MB
        // diyordu ve `max_megapixels` HİÇ uygulanmıyordu (`docs/98` FF-68).
        $maxBytes = (int) config('media-slots.limits.max_bytes', 30 * 1024 * 1024);
        $maxKilobytes = (int) ceil($maxBytes / 1024);
        $maxPixels = ((int) config('media-slots.limits.max_megapixels', 40)) * 1_000_000;
        $slot = (string) $this->input('slot', '');

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes, function (string $attribute, mixed $value, Closure $fail) use ($maxPixels, $maxBytes, $slot): void {
                if (! $value instanceof UploadedFile) {
                    $fail('The file failed magic-byte validation.');

                    return;
                }

                /*
                    METİNLE BAŞLAYAN HER DOSYA SVG YOLUNA GİRER.

                    `getimagesize` bir SVG'yi hiç tanımaz, dolayısıyla eski
                    kapıda SVG "geçersiz görsel" diye düşerdi. Sahip SVG'yi
                    açtırdı (2026-09-05, `docs/108` §6.2), ama ayrım UZANTIYA
                    ya da istemcinin bildirdiği MIME'a göre YAPILAMAZ: ikisi
                    de yükleyenin denetimindedir.

                    Bu yüzden ölçüt dosyanın kendi ilk baytıdır. `<` ile
                    başlayan her dosya SVG kapısına girer — ve orada SVG
                    olmadığı anlaşılan bir gövde (PHP, HTML) reddedilir.
                    Yani `fixtures/malicious/php-as-jpg.jpg` ve
                    `html-as-png.png` eskisi gibi 422 döner, sebebi değişir.
                */
                if ($this->startsLikeMarkup($value)) {
                    $this->validateSvg($value, $slot, $maxBytes, $fail);

                    return;
                }

                $info = @getimagesize($value->getRealPath());

                if ($info === false || ! in_array($info[2], self::ALLOWED_IMAGE_TYPES, true)) {
                    $fail('The file failed magic-byte validation.');

                    return;
                }

                /*
                    DECOMPRESSION BOMB burada durur — DECODE EDİLMEDEN.

                    `getimagesize` başlıktan okur; 100000×100000 iddia eden
                    bir PNG birkaç yüz bayttır ama açılırsa 40 GB bellek
                    ister. Piksel tavanı, dosyayı çözmeye kalkmadan önce
                    başlıktaki iddiaya bakar (`docs/49` Faz 2, `config/
                    media-slots.php` limits).
                */
                $width = (int) ($info[0] ?? 0);
                $height = (int) ($info[1] ?? 0);

                if ($width < 1 || $height < 1 || $width * $height > $maxPixels) {
                    $fail('The image claims more pixels than this service accepts.');
                }
            }],
            'altText' => ['required', 'string', 'max:255'],
            'slot' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * SVG KAPISI — sahibin kararının güvenlik yarısı.
     *
     * Üç ayrı kapı, bu sırayla:
     *
     *   1. SLOT. SVG her yere gitmez. `itemImage` bir yemek fotoğrafı
     *      slotudur ve orada vektör kabul etmek slot politikasının kendi
     *      sözüyle çelişirdi (INV-04). İzin `config/media-slots.php`
     *      `formats` dizisinde yazar; burada bir ikinci liste tutulmaz.
     *
     *   2. TEMİZLEYİCİ. `SvgSanitizer` saf bir alan sınıfıdır; gövdeyi
     *      okur ve "çalışabilir/dışarı çıkan bir şey var mı" sorusunu
     *      cevaplar.
     *
     *   3. FAIL-CLOSED. Saldırı bulunan ya da hiç okunamayan gövde
     *      REDDEDİLİR — temizlenip kabul EDİLMEZ. Sessizce temizleyip
     *      saklamak iki şeyi birden yapardı: saldırıyı arşivlemek ve
     *      sahibin dosyasını haber vermeden değiştirmek. Ayrıca
     *      `MaliciousIntakeGateTest`in CI sözü ("`fixtures/malicious/`
     *      içindeki her dosya, hiçbir şey saklanmadan reddedilir") ancak
     *      böyle korunur.
     */
    private function validateSvg(UploadedFile $file, string $slot, int $maxBytes, Closure $fail): void
    {
        $catalogue = SlotCatalogue::fromArray((array) config('media-slots.slots', []));

        if (! $catalogue->has($slot) || ! $catalogue->get($slot)->acceptsFormat('svg')) {
            $fail('Bu alana SVG yüklenemez. SVG yalnız logo, baskı logosu ve favicon gibi vektör alanlarında kullanılır.');

            return;
        }

        $path = (string) $file->getRealPath();

        // `max:` kuralı zaten düştüyse gövdeyi belleğe hiç almayız: fazla
        // büyük bir dosyayı okumak, reddedileceğini bile bile okumaktır.
        if (! is_readable($path) || (int) (@filesize($path) ?: 0) > $maxBytes) {
            $fail('The file failed magic-byte validation.');

            return;
        }

        $body = @file_get_contents($path);

        if ($body === false) {
            $fail('The file failed magic-byte validation.');

            return;
        }

        $result = (new SvgSanitizer)->sanitize($body);

        if (! $result->isSafe()) {
            // Sebep SAHİBİN cümlesidir: "geçersiz dosya" ona ne olduğunu da
            // ne yapacağını da söylemez (`docs/76`).
            $fail($result->failureReason ?? 'Bu SVG dosyası güvenle temizlenemedi ve kabul edilmedi.');
        }
    }

    /** Dosyanın ilk anlamlı baytı `<` mi? Uzantıya ve MIME'a bakılmaz. */
    private function startsLikeMarkup(UploadedFile $file): bool
    {
        $path = (string) $file->getRealPath();

        if (! is_readable($path)) {
            return false;
        }

        $head = (string) @file_get_contents($path, false, null, 0, 64);
        $head = ltrim(str_starts_with($head, "\xEF\xBB\xBF") ? substr($head, 3) : $head);

        return str_starts_with($head, '<');
    }
}
