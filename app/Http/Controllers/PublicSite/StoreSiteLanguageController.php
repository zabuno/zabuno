<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class StoreSiteLanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $offered = array_values(array_intersect(['en', 'tr'], (array) config('i18n.shipped_locales', [])));
        $validated = $request->validate([
            'language' => ['required', 'string', Rule::in($offered)],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        return redirect($this->localPath($validated['return_to'] ?? null))->cookie(
            (string) config('i18n.negotiation.methods.explicit.options.cookie', 'zbn_language'),
            $validated['language'],
            365 * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax',
        );
    }

    private function localPath(?string $candidate): string
    {
        if ($candidate === null || ! str_starts_with($candidate, '/')) {
            return '/';
        }

        // Reject URL-shaped and encoded slash/backslash bypasses before redirecting.
        $decoded = rawurldecode($candidate);
        if (str_starts_with($decoded, '//') || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return '/';
        }

        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return '/';
        }

        return $candidate;
    }
}
