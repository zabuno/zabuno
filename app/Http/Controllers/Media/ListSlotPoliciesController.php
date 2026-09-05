<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Domain\Media\MediaSurface;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SlotPolicy;
use App\Domain\Media\UploadSizeLimits;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Bir görselin NEREDE kullanılacağı ve o yerin ne gerektirdiği.
 *
 * Neden bir uç nokta: kullanıcı 17 opak slot adı arasından seçim yapıyor ve
 * hangi ölçüde görsel yükleyeceğini HİÇBİR YERDEN öğrenemiyordu. Sonuç,
 * menüde bulanık ya da yanlış kırpılmış görseldi — ve bunu ancak yayınladan
 * sonra fark ediyordu.
 *
 * Politika istemciye açılır ki kullanıcı yüklemeden ÖNCE bilsin.
 *
 * Yalnız `menu` yüzeyinin slotları döner: "Pricing", "Features" ve
 * "Testimonial" Zabuno'nun kendi tanıtım sitesine aittir, restoranın
 * menüsüne değil (`docs/50` "3 Neden" kapısı).
 *
 * BELGE SLOTU BU LİSTEDE YOKTUR ve olmaması bilinçlidir. Bu uç, GÖRSEL
 * yükleme sihirbazının kaynağıdır: sihirbaz dosyayı `accept="image/*"` ile
 * seçtirir, ölçüsünü okur, kırpar ve istemcide küçültür. Bunların hiçbiri
 * bir PDF'te yapılamaz. `document` slotunu bu listeye koymak, kullanıcıya
 * bir yer SEÇTİRİP sonra dosyayı seçtirmemek olurdu — açılır kutudaki her
 * seçenek bir sözdür (`docs/76`). Belge yükleme yolu (sürükle-bırak
 * alanının belge kabul etmesi) ayrı bir pakettir; kapı sunucuda AÇIKTIR ve
 * `PdfIntakeAndDeliveryTest` onu koruyor.
 */
final class ListSlotPoliciesController extends Controller
{
    /**
     * Sihirbazın gerçekten seçtirebildiği biçimler (`MediaDropzone`,
     * `accept="image/*"`).
     *
     * @var list<string>
     */
    private const WIZARD_FORMATS = ['jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'svg'];

    public function __invoke(): JsonResponse
    {
        $catalogue = SlotCatalogue::fromArray((array) config('media-slots.slots', []));

        $offerable = array_filter(
            $catalogue->forSurface(MediaSurface::Menu),
            static function (SlotPolicy $policy): bool {
                foreach (self::WIZARD_FORMATS as $format) {
                    if ($policy->acceptsFormat($format)) {
                        return true;
                    }
                }

                return false;
            },
        );

        $slots = array_map(
            static fn (SlotPolicy $policy): array => [
                'key' => $policy->key,
                'minWidth' => $policy->minWidth,
                'minHeight' => $policy->minHeight,
                'aspect' => $policy->aspect,
                'formats' => $policy->formats,
                'altRequired' => $policy->altRequired,
            ],
            $offerable,
        );

        $limits = UploadSizeLimits::fromArray((array) config('media-slots.limits', []));

        return response()->json([
            'slots' => array_values($slots),
            'limits' => [
                /*
                    `maxBytes` MUTLAK TAVANDIR ve kalıyor: ekranın tanımadığı
                    bir tür geldiğinde tutunacağı bir sayı olmalı. Asıl
                    cevap `maxBytesByKind`tir — sahip tek düz bir sayı değil,
                    KENDİ dosyasının sınırını okumalı (FF-158).

                    Değerler yapılandırmadan olduğu gibi değil, `UploadSizeLimits`
                    üzerinden geçer: kapının UYGULADIĞI sayı ile ekranın
                    YAZDIĞI sayı aynı yerden gelir. İkisi ayrı okunsaydı,
                    tavan bir gün daraldığında ekran eski sayıyı yazmaya
                    devam eder ve reddi açıklayamazdı.

                    VİDEO BU SÖZLÜKTE YOKTUR ve bu bilinçlidir: kabul
                    edilmeyen bir tür için sayı göndermek, ekranda "şu
                    boyuta kadar yükleyebilirsiniz" diye görünürdü.
                */
                'maxBytes' => $limits->ceilingBytes,
                'maxBytesByKind' => $limits->all(),
                'maxMegapixels' => (int) config('media-slots.limits.max_megapixels'),
            ],
        ]);
    }
}
