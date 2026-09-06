<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Analytics\MeasurementConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Çerez tercihini YAZAN uç — FF-198.
 *
 * ÖLÇÜLDÜ: `MeasurementConsent::granted()/denied()` vardı, çerezi yazan uç
 * yoktu; kapı hep kapalıydı çünkü kimseye sorulmuyordu. Bu uç JavaScript
 * gerektirmez: kurumsal kabuk betiksiz çalışmak zorunda
 * (SHELL-SINGLE-SOURCE-04) ve tercih formu da öyle — düz bir `<form>`,
 * CSRF ve bir yönlendirme.
 *
 * ÇEREZ `httpOnly`: kararı yalnız sunucu okur (`analytics.blade.php`,
 * `measurement.blade.php`), betiğin okumasına gerek yok. `Lax`: form
 * aynı siteden gelir. Ömür `MeasurementConsent::LIFETIME_DAYS` — sonsuz
 * bir onay bir imzadır.
 */
final class StoreMeasurementConsentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $consent = $validated['decision'] === 'accept'
            ? MeasurementConsent::granted()
            : MeasurementConsent::denied();

        return redirect($this->localPath($validated['return_to'] ?? null))->cookie(
            MeasurementConsent::COOKIE,
            $consent->cookieValue(),
            MeasurementConsent::LIFETIME_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax',
        );
    }

    /**
     * Dönüş adresi YALNIZ bu sitenin bir yolu olabilir.
     *
     * `return_to` gövdeden gelir ve gövde kullanıcının elindedir. Dış bir
     * adrese yönlendirmek, "çerezi kabul et" düğmesini bir oltalama
     * bağlantısına çevirirdi. Kural dar: `/` ile başlar, `//` ile başlamaz
     * (şemasız mutlak adres), host taşımaz, kontrol karakteri taşımaz.
     * Uymayan her şey ana sayfaya düşer.
     */
    private function localPath(?string $candidate): string
    {
        if ($candidate === null || $candidate === '') {
            return '/';
        }

        if (! str_starts_with($candidate, '/') || str_starts_with($candidate, '//') || str_starts_with($candidate, '/\\')) {
            return '/';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return '/';
        }

        $parts = parse_url($candidate);

        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return '/';
        }

        return $candidate;
    }
}
