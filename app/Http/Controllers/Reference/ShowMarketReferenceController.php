<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reference;

use App\Application\Reference\Port\MarketReferencePort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marka formunun ihtiyaç duyduğu referans veri.
 *
 * Bu veri neden sunucudan geliyor: ICU sürümüne bağlı ve 247 ülke + 294
 * para birimi + 419 saat dilimi ediyor. JS paketine gömmek, her kullanıcıya
 * hiç açmayacağı bir formun tablolarını indirtir. Ayrıca doğrulama sunucuda
 * yapılıyor; listeyi de aynı kaynaktan vermek, listede olan bir değerin
 * doğrulamadan geçmesini garanti eder.
 */
final class ShowMarketReferenceController extends Controller
{
    public function __invoke(Request $request, MarketReferencePort $reference): JsonResponse
    {
        $country = $request->query('country');

        // Ülke verilmediyse tarayıcının saat diliminden öner.
        if ((! is_string($country) || $country === '') && is_string($request->query('timezone'))) {
            $country = $reference->countryForTimezone((string) $request->query('timezone'));
        }

        return response()->json([
            'markets' => $reference->markets(),
            'currencies' => $reference->currencies(),
            // Ülke verilmişse yalnız onun saat dilimleri: ABD'de 29 tane
            // var ve hepsini göstermek seçimi kolaylaştırmaz, zorlaştırır.
            'timezones' => is_string($country) && $country !== ''
                ? $reference->timezonesFor($country)
                : [],
            'defaults' => is_string($country) && $country !== ''
                ? $reference->defaultsFor($country)
                : null,
            // Öneri, seçim değildir: kullanıcı değiştirebilsin diye ayrı
            // alanda dönüyor.
            'suggestedCountry' => is_string($country) && $country !== '' ? $country : null,
        ]);
    }
}
