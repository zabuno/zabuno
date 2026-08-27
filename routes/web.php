<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FoundationStatusController;
use App\Http\Controllers\PlatformAdminAppController;
use App\Http\Controllers\QrDestination\RedirectQrTokenController;
use App\Http\Controllers\QrDestination\ShowPublicMenuController;
use App\Http\Controllers\Seo\ShowRobotsController;
use App\Http\Controllers\Team\ShowTeamInvitationController;
use App\Http\Controllers\WorkspaceAppController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

Route::get('/', [FoundationStatusController::class, '__invoke'])->name('foundation.status');

/**
 * Public, unauthenticated legal-readiness pages: server-side these only
 * need to serve the same React-mounting app shell as '/' so the
 * client-side AppShell can render the corresponding pending-review legal
 * page per pathname (S1-WP01A).
 */
Route::get('/terms', [FoundationStatusController::class, '__invoke'])->name('legal.terms');
Route::get('/privacy', [FoundationStatusController::class, '__invoke'])->name('legal.privacy');
Route::get('/kvkk', [FoundationStatusController::class, '__invoke'])->name('legal.kvkk');

/**
 * Public, unauthenticated QR resolver: /q/{token} redirects to the stable
 * /menu/{token} URL, which renders the current published-menu snapshot.
 * Neither route mutates state or requires CSRF/auth (S1-WP04b1).
 */
Route::get('/robots.txt', ShowRobotsController::class)->name('seo.robots');

// QR çözümleyici hız sınırlıdır: token uzayı taranabilir bir yüzeydir ve
// her istek bir veritabanı araması yapar. Sınır cömerttir — bir masadaki
// misafirlerin arka arkaya taraması engellenmemeli — ama bir tarayıcıyı
// durdurmaya yeter.
Route::get('/q/{token}', RedirectQrTokenController::class)
    ->middleware('throttle:qr-resolve')
    ->name('qr.resolve');
Route::get('/menu/{token}', ShowPublicMenuController::class)->name('qr.publicMenu');

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
