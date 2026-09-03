<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\StoreGuestMenuEventsController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FoundationStatusController;
use App\Http\Controllers\Media\ServeRenditionController;
use App\Http\Controllers\PlatformAdminAppController;
use App\Http\Controllers\PublicSite\ShowContactFormController;
use App\Http\Controllers\PublicSite\ShowHelpController;
use App\Http\Controllers\PublicSite\StoreContactMessageController;
use App\Http\Controllers\QrDestination\RedirectQrTokenController;
use App\Http\Controllers\QrDestination\ShowPublicMenuByKeyController;
use App\Http\Controllers\QrDestination\ShowPublicMenuController;
use App\Http\Controllers\Seo\ShowRobotsController;
use App\Http\Controllers\Seo\ShowSitemapController;
use App\Http\Controllers\Team\ShowTeamInvitationController;
use App\Http\Controllers\WorkspaceAppController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use App\Http\Responses\GuestDeadEnd;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

Route::get('/', [FoundationStatusController::class, '__invoke'])->name('foundation.status');

/**
 * Public, unauthenticated legal-readiness pages: server-side these only
 * need to serve the same React-mounting app shell as '/' so the
 * client-side AppShell can render the corresponding pending-review legal
 * page per pathname (S1-WP01A).
 */
// Fiyat KAYDOLMADAN görülür (`docs/88`): fiyatı görmek için kaydolmak
// gereken bir ürün, kaydolmayı fiyatı görmeye bağlı kılar.
Route::get('/pricing', [FoundationStatusController::class, '__invoke'])->name('public.pricing');

/*
    "Tıkanırsam kime sorarım?" (`docs/88`).

    Gönderim HIZ SINIRLI: form herkese açık ve oturum istemiyor, dolayısıyla
    sınırsız gönderim bir tabloyu doldurmanın en ucuz yolu olurdu.
*/
// "İlk 15 dakika" — menüyü aktarmak, karekod basmak, fiyat değiştirmek.
// Oturum İSTEMEZ: tıkanan biri oturum açamıyor olabilir (`docs/89`).
Route::get('/help', ShowHelpController::class)->name('public.help');

Route::get('/contact', ShowContactFormController::class)->name('public.contact');
Route::post('/contact', StoreContactMessageController::class)
    ->middleware('throttle:5,1')
    ->name('public.contact.store');

Route::get('/terms', [FoundationStatusController::class, '__invoke'])->name('legal.terms');
Route::get('/privacy', [FoundationStatusController::class, '__invoke'])->name('legal.privacy');
Route::get('/kvkk', [FoundationStatusController::class, '__invoke'])->name('legal.kvkk');

/**
 * Public, unauthenticated QR resolver: /q/{token} redirects to the stable
 * /menu/{token} URL, which renders the current published-menu snapshot.
 * Neither route mutates state or requires CSRF/auth (S1-WP04b1).
 */
Route::get('/robots.txt', ShowRobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', ShowSitemapController::class)->name('seo.sitemap');

// QR çözümleyici hız sınırlıdır: token uzayı taranabilir bir yüzeydir ve
// her istek bir veritabanı araması yapar. Sınır cömerttir — bir masadaki
// misafirlerin arka arkaya taraması engellenmemeli — ama bir tarayıcıyı
// durdurmaya yeter.
Route::get('/q/{token}', RedirectQrTokenController::class)
    ->middleware('throttle:qr-resolve')
    ->name('qr.resolve');
/*
    Misafir sayfasının olay ucu (`docs/84`).

    `/q/` altında duruyor çünkü misafir yüzeyine aittir ve o yüzeyin hız
    sınırı burada da anlamlıdır. Oturum İSTEMEZ: menü de istemiyor.
*/
Route::post('/q/events', StoreGuestMenuEventsController::class)
    ->middleware('throttle:60,1')
    ->name('guest.events');

// Görsel türevleri: değişmez, sağlama toplamı taşıyan, herkese açık adres
// (`docs/76`). Misafirin menüdeki fotoğrafı görebilmesi için oturum
// gerekmez — menünün kendisi de zaten herkese açıktır.
Route::get('/media/r/{rendition}-{fingerprint}.{format}', ServeRenditionController::class)
    ->where('rendition', '[0-9]+')
    ->where('fingerprint', '[a-f0-9]{32}')
    ->where('format', '(webp|png|jpeg)')
    ->name('media.rendition');

// Basılı QR token'ının adresi. KALICI adres artık `/menu/{key}/{slug}`;
// bu yol eski bağlantılar ve eski basılı kodlar için yaşamaya devam eder
// ve kalıcı olarak kanonik adrese taşır (`docs/38` §21).
Route::get('/menu/{token}', ShowPublicMenuController::class)
    ->where('token', '[A-Za-z0-9_-]{43}')
    ->name('qr.publicMenu');

// Yayınlanan menünün herkese açık, indekslenebilir adresi.
Route::get('/menu/{key}/{slug?}', ShowPublicMenuByKeyController::class)
    ->where('key', '[a-z0-9]{10}')
    ->name('publicMenu.canonical');

// Biçimi tutmayan her `/menu/...` isteği AYNI çıkmaz sokağa düşer.
// Bu olmadan Laravel'in genel 404'ü devreye girer ve iki şey birden olur:
// tekdüzelik bozulur (QR-PUBLIC-404-UNIFORM-01) ve hata metni rota şeklini
// ifşa eder.
Route::get('/menu/{any}/{rest?}', static fn (Request $request) => GuestDeadEnd::respond($request))
    ->where('any', '.*')
    ->where('rest', '.*')
    ->name('publicMenu.deadEnd');

/**
 * Public GET invitation entry (S1-WP01A delivery journey): a guest gets
 * the auth-shell mount with no sensitive dataset; only a matching,
 * verified, authenticated invitee session sees real workspace/email/role
 * fields (in the JSON contract). Constrained to the exact 64-char opaque
 * token shape so it never shadows another route.
 */
Route::get('/invitations/{token}', ShowTeamInvitationController::class)
    ->where('token', '[A-Za-z0-9_-]{64}')
    ->name('invitations.show');

/**
 * S1-WP02C: the authenticated, verified workspace app shell. Guarded the
 * same way Fortify guards its own routes (auth:web, verified) so a guest
 * gets a redirect/401 and an unverified user gets a redirect/403 — never a
 * bare 404.
 */
Route::get('/app', WorkspaceAppController::class)
    ->middleware(['auth:web', 'verified'])
    ->name('workspace.app');

/*
 * Çalışma alanı ekranları GERÇEK adres alır.
 *
 * Öncesinde gezinti fragment ile yapılıyordu (`/app#menu`). `docs/38` §4 bunu
 * açıkça reddediyor: *"Bir ekranı fragment ile temsil etmek, o ekranı sunucu
 * günlüklerinden, analitikten ve arama motorundan gizlemektir."* Fragment
 * sunucuya hiç gönderilmez — yani hangi ekranın kullanıldığı ölçülemez, bir
 * ekranın bağlantısı paylaşılamaz, tarayıcı geçmişi anlamlı olmaz.
 *
 * Politika belgede vardı ve motor kuruluydu; uygulanmamış olan tek yer
 * panelin kendisiydi.
 *
 * Bölüm adı burada DOĞRULANMAZ. Sunucu aynı kabuğu döndürür ve hangi bölümün
 * geçerli olduğuna istemci karar verir; bilinmeyen bölüm kabuk içinde
 * karşılanır. Sunucuda ikinci bir bölüm listesi tutmak, iki listenin
 * ayrışacağı bir gün yaratırdı.
 */
Route::get('/app/{workspace}/{section?}', WorkspaceAppController::class)
    ->where('workspace', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware(['auth:web', 'verified'])
    ->name('workspace.app.section');

/**
 * S1-WP01A follow-up: the standalone authenticated Platform Admin shell.
 * Guarded the same way as GET /app (auth:web, verified) plus the existing
 * EnsurePlatformSuperAdmin middleware, which enumeration-safely denies a
 * non-super-admin with a bare 404.
 */
Route::get('/platform', PlatformAdminAppController::class)
    ->middleware(['auth:web', 'verified', EnsurePlatformSuperAdmin::class])
    ->name('platform.admin');

/**
 * The same panel addressed by section. The section name is deliberately NOT
 * validated here: the client owns the section list, and a second list on the
 * server would drift away from it silently. An unknown section renders the
 * default section, exactly as an unknown fragment used to.
 */
Route::get('/platform/{section}', PlatformAdminAppController::class)
    ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware(['auth:web', 'verified', EnsurePlatformSuperAdmin::class])
    ->name('platform.admin.section');

/**
 * Shadows Fortify's default GET /email/verify/{id}/{hash} (registered
 * later, in FortifyServiceProvider::boot) so a browser (non-JSON) request
 * settles on the workspace app shell instead of the standalone "verified"
 * view. The JSON contract from App\Http\Responses\Auth\VerifyEmailResponse
 * (S1WP02A-VERIFY-01/03: always 2xx, never a redirect) applies to
 * wantsJson() requests only; a browser request is redirected to /app.
 */
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    if (! $request->user()->hasVerifiedEmail() && $request->user()->markEmailAsVerified()) {
        event(new Verified($request->user()));
    }

    if ($request->wantsJson()) {
        return app(VerifyEmailResponseContract::class)->toResponse($request);
    }

    return redirect('/app');
})->middleware(['auth:web', 'signed', 'throttle:verification'])->name('verification.verify');

/**
 * Shadows Fortify's own guest-guarded POST /register route (registered
 * later, in FortifyServiceProvider::boot) with a session-agnostic
 * controller — see App\Http\Controllers\Auth\RegisteredUserController.
 */
Route::post('/register', RegisteredUserController::class)->middleware('throttle:register');

/**
 * Shadows Fortify's default POST /logout — see
 * App\Http\Controllers\Auth\LogoutController.
 */
Route::post('/logout', LogoutController::class)->middleware('auth:web');
