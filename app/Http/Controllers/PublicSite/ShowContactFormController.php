<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tıkanırsam kime sorarım?" — `docs/88` (P1-01).
 *
 * Bu sorunun cevabı sayfada "henüz bağlı bir iletişim formu yok" yazıyordu.
 */
final class ShowContactFormController extends Controller
{
    public function __construct(private readonly SiteShell $shell) {}

    public function __invoke(Request $request): View
    {
        // Kabuk verisi TEK yerden (`SiteShell`); sayfa yalnız kendi
        // gövdesinin ihtiyacını ekler.
        return view('public.contact', $this->shell->context($request, 'contact', '/contact') + [
            'plans' => [],
        ]);
    }
}
