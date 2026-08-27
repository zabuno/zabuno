<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Domain\Media\MediaSurface;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\SlotPolicy;
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
 */
final class ListSlotPoliciesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $catalogue = SlotCatalogue::fromArray((array) config('media-slots.slots', []));

        $slots = array_map(
            static fn (SlotPolicy $policy): array => [
                'key' => $policy->key,
                'minWidth' => $policy->minWidth,
                'minHeight' => $policy->minHeight,
                'aspect' => $policy->aspect,
                'formats' => $policy->formats,
                'altRequired' => $policy->altRequired,
            ],
            $catalogue->forSurface(MediaSurface::Menu),
        );

        return response()->json([
            'slots' => array_values($slots),
            'limits' => [
                'maxBytes' => (int) config('media-slots.limits.max_bytes'),
                'maxMegapixels' => (int) config('media-slots.limits.max_megapixels'),
            ],
        ]);
    }
}
