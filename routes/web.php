<?php

declare(strict_types=1);

use App\Domain\Publication\BusinessType;
use App\Http\Controllers\Analytics\StoreGuestMenuEventsController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SendEmailVerificationNotificationController;
use App\Http\Controllers\Content\ShowCorporatePageController;
use App\Http\Controllers\EngineeringAppController;
use App\Http\Controllers\FoundationStatusController;
use App\Http\Controllers\Media\ServeOriginalController;
use App\Http\Controllers\Media\ServeRenditionController;
use App\Http\Controllers\Ordering\StoreGuestOrderController;
use App\Http\Controllers\PlatformAdminAppController;
use App\Http\Controllers\Publication\ShowDraftPreviewController;
use App\Http\Controllers\PublicSite\ShowContactFormController;
use App\Http\Controllers\PublicSite\ShowHelpController;
use App\Http\Controllers\PublicSite\StoreContactMessageController;
use App\Http\Controllers\QrDestination\RedirectQrTokenController;
use App\Http\Controllers\QrDestination\ShowPublicMenuByKeyController;
use App\Http\Controllers\QrDestination\ShowPublicMenuController;
use App\Http\Controllers\QrDestination\ShowPublicMenuItemController;
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

/*
    MİSAFİRİN SİPARİŞİ (`docs/115` S2).

    OTURUM YOK, CSRF YOK: misafir menüyü de oturumsuz görüyor ve masadan
    sipariş vermek için hesap açması istenmiyor — sahibin kararı buydu.
    Masa gövdeden değil, adresteki karekod token'ından okunur.

    Hız sınırı menü olayından DAHA DAR: bir olay satırı yazmakla mutfağa
    iş düşürmek aynı şey değil. Sınır yine de bir masadaki misafirlerin
    arka arkaya sipariş vermesini engellemeyecek kadar cömert; asıl
    yığılma kalkanı masa başına AÇIK SİPARİŞ tavanıdır
    (`StoreGuestOrderController::MAX_OPEN_ORDERS_PER_TABLE`), çünkü o
    tavan mutfağın gerçek kapasitesini ölçer, isteğin hızını değil.
*/
Route::post('/q/{token}/orders', StoreGuestOrderController::class)
    ->middleware('throttle:20,1')
    ->name('guest.orders.store');

// Görsel türevleri: değişmez, sağlama toplamı taşıyan, herkese açık adres
// (`docs/76`). Misafirin menüdeki fotoğrafı görebilmesi için oturum
// gerekmez — menünün kendisi de zaten herkese açıktır.
Route::get('/media/r/{rendition}-{fingerprint}.{format}', ServeRenditionController::class)
    ->where('rendition', '[0-9]+')
    ->where('fingerprint', '[a-f0-9]{32}')
    // `svg`: sahibin 2026-09-05 kararı. Gövde alımda temizlendi, adres
    // parmak izi taşıyor ve denetleyici onu betik çalıştıramaz başlıklarla
    // veriyor (`ServeRenditionController`).
    ->where('format', '(webp|png|jpeg|svg)')
    ->name('media.rendition');

// Aslın İMZALI adresi (`docs/49` Faz 6 madde 2): 10 dakikalık imza,
// kiracı + varlık; süresi dolunca 403. Oturum gerekmez — imza yetkidir.
Route::get('/media/original/{workspace}/{asset}', ServeOriginalController::class)
    ->where('workspace', '[0-9]+')
    ->where('asset', '[0-9]+')
    ->middleware('signed')
    ->name('media.original');

/*
    TASLAK ÖNİZLEMESİ — "Telefonda önizle" (sahibin 2026-09-05 kararı).

    MİSAFİRİN ADRESİ DEĞİLDİR ve onunla karıştırılamaz: ayrı bir yol
    parçası (`/menu-preview/`), on beş dakikalık imza ve `noindex`. Oturum
    istemez çünkü sahip bu bağlantıyı kendi telefonunda açar; imza yetkidir
    ve `media.original` ile tam olarak aynı deseni izler (`docs/49` Faz 6).
*/
Route::get('/menu-preview/{workspace}/{menu}', ShowDraftPreviewController::class)
    ->where('workspace', '[0-9]+')
    ->where('menu', '[0-9]+')
    ->middleware('signed')
    ->name('publication.draftPreview');

// Basılı QR token'ının adresi. KALICI adres artık `/menu/{key}/{slug}`;
// bu yol eski bağlantılar ve eski basılı kodlar için yaşamaya devam eder
// ve kalıcı olarak kanonik adrese taşır (`docs/38` §21).
Route::get('/menu/{token}', ShowPublicMenuController::class)
    ->where('token', '[A-Za-z0-9_-]{43}')
    ->name('qr.publicMenu');

/*
    ESKİ KANONİK ADRES — kalıcı olarak yenisine taşınır (FF-116).

    `/menu/{key}/{slug}` biçimi 2026-09-04'te değişti: en anlamlı parça
    (işletme adı) en sondaydı, en anlamsız parça (10 karakterlik anahtar)
    ortadaydı. Bu yol ölmez; paylaşılmış her bağlantı ve dış link 301 ile
    yeni adrese taşınır. Yönlendirmeyi denetleyici yapar (`getPathInfo()`
    kanonikle karşılaştırılır), bu yüzden ayrı bir denetleyici gerekmez.
*/
Route::get('/menu/{key}/{slug?}', ShowPublicMenuByKeyController::class)
    ->where('key', '[a-z0-9]{10}')
    ->name('publicMenu.legacy');

/*
    YAYINLANAN MENÜNÜN KANONİK ADRESİ (`docs/105` §4.2):

        /restoran/pasa-doner/menu/ab12cd34ef

    Baştaki segment iki iş yapar: insana ne olduğunu söyler ve kiracıya kendi
    kökünü verir. Kurumsal site `/tr/urun/...` altında yaşayacak; kiracı
    adresleri ayrı bir kökte durduğu için bir işletme slug'ı hiçbir zaman
    `/pricing` ile çakışamaz.

    İlk segment SERBEST DEĞİLDİR — yalnız bilinen tür segmentleriyle eşleşir.
    Serbest bıraksaydık bu rota `/pricing` dahil her şeyi yutardı.
*/
Route::get('/{type}/{business}/menu/{key}', ShowPublicMenuByKeyController::class)
    ->where('type', BusinessType::segmentPattern())
    ->where('business', '[a-z0-9-]+')
    ->where('key', '[a-z0-9]{10}')
    ->name('publicMenu.canonical');

// Adı okunamayan işletme: slug uydurulmaz, adres kısalır.
Route::get('/{type}/menu/{key}', ShowPublicMenuByKeyController::class)
    ->where('type', BusinessType::segmentPattern())
    ->where('key', '[a-z0-9]{10}')
    ->name('publicMenu.canonicalNoSlug');

/*
    TEK ÜRÜNÜN ADRESİ (`docs/105` §4.3):

        /restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap

    Sahibin ilk örneği `#item=101` idi; fragment sunucuya hiç ulaşmadığı için
    indekslenemez, ölçülemez ve paylaşıldığında hangi ürün olduğu sunucuda
    bilinemez. Menü sayfasının kendi çıpası (`#item-101`) yerinde kalır.

    Kimlik segmentin BAŞINDADIR: slug yalnız okunabilirliktir ve yanlışsa
    adres kendini onarır.
*/
Route::get('/{type}/{business}/menu/{key}/{itemSegment}/{item}', ShowPublicMenuItemController::class)
    ->where('type', BusinessType::segmentPattern())
    ->where('business', '[a-z0-9-]+')
    ->where('key', '[a-z0-9]{10}')
    ->where('itemSegment', BusinessType::itemSegmentPattern())
    ->where('item', '[0-9]+(?:-[a-z0-9-]*)?')
    ->name('publicMenu.item');

// Biçimi tutmayan her `/menu/...` isteği AYNI çıkmaz sokağa düşer.
// Bu olmadan Laravel'in genel 404'ü devreye girer ve iki şey birden olur:
// tekdüzelik bozulur (QR-PUBLIC-404-UNIFORM-01) ve hata metni rota şeklini
// ifşa eder.
Route::get('/menu/{any}/{rest?}', static fn (Request $request) => GuestDeadEnd::respond($request))
    ->where('any', '.*')
    ->where('rest', '.*')
    ->name('publicMenu.deadEnd');

/*
    KURUMSAL SİTENİN TEK GİRİŞ KAPISI (`docs/105` §3, yönerge §7).

    Site haritasındaki 414 yol için 414 rota yazılmaz: hepsi kütükte bir
    kayıttır ve tek bir denetleyici `PageGate`'e sorup kararı uygular. Bir
    sayfayı açmak için koddan bileşen silinmez, yalnız yayın durumu değişir.

    Kapı YALNIZ `/tr/` ve `/en/` altında çalışır: bugün yayında olan `/pricing`,
    `/help` gibi adreslere dokunmaz. O adreslerin dil dizinine taşınması ayrı
    bir paketin işi (`docs/105` §4.1) ve 301'leriyle birlikte planlanacak.
*/
Route::get('/{locale}/{path?}', ShowCorporatePageController::class)
    ->where('locale', 'tr|en')
    ->where('path', '.*')
    ->name('corporate.page');

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
    /*
        BÖLÜM İÇİNDEKİ EKRANIN DA ADRESİ VAR — ve o adres yenilenebilmeli.

        Önceki desen tek segment kabul ediyordu. Ama panel gezintisi
        (`sectionHref`) bölüm-içi bir alt yol ekleyebiliyor ve
        `/app/{ws}/settings/brand` gibi adresler üretiyor: o adres istemci
        gezintisiyle ÇALIŞIYOR, yenilendiğinde ya da bağlantı paylaşıldığında
        çıplak bir 404 veriyordu (sahibin 2026-09-05 ekran görüntüsü).

        Bu, fragment'ten gerçek adrese geçmenin bütün gerekçesini yok ediyordu:
        `docs/38` §4 adresi "paylaşılabilir ve yer imine eklenebilir" olsun
        diye istiyor. Yenilenemeyen bir adres fragment'ten yalnız görünüşte
        farklıdır — ve fragment en azından 404 vermezdi.

        Desen HÂLÂ DAR: yalnız küçük harf, rakam, tire ve bölüm ayıracı olarak
        eğik çizgi. Serbest bir `.*` olsaydı bu rota `/app/{ws}/...` altındaki
        her şeyi yutardı ve yarın buraya eklenecek gerçek bir uç (indirme,
        dışa aktarma) sessizce kabuğa düşerdi.

        Bölüm adı yine DOĞRULANMIYOR: sunucu aynı kabuğu döndürür, hangi
        bölümün geçerli olduğuna istemci karar verir. Sunucuda ikinci bir
        bölüm listesi tutmak, iki listenin ayrışacağı bir gün yaratırdı.
    */
    ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*')
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

/*
    Mühendislik kabuğu (`docs/98` FF-66): release readiness, güvenlik
    kanıtı, AI denetim izi. Platform (plan/ödeme/anahtar) kabuğundan AYRI —
    aynı kişi olabilir, aynı iş değil. Yetki aynı: superadmin, aynı
    enumeration-safe 404.
*/
Route::get('/engineering', EngineeringAppController::class)
    ->middleware(['auth:web', 'verified', EnsurePlatformSuperAdmin::class])
    ->name('engineering');
Route::get('/engineering/{section}', EngineeringAppController::class)
    ->where('section', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware(['auth:web', 'verified', EnsurePlatformSuperAdmin::class])
    ->name('engineering.section');

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

/*
    Fortify'ın POST /email/verification-notification ucunu gölgeler.

    Fortify'ın kendi ucu her hâlükârda 202 döner; ekran da `response.ok`
    değerine bakıp "doğrulama e-postası gönderildi" yazar. Taşıyıcı düştüğünde
    o cümle yalandı: kullanıcı hiç çıkmamış bir e-postayı bekliyordu
    (`docs/110` P0-06). Sınır ve kimlik AYNI kalır — `auth:web` ve
    `throttle:verification`; değişen tek şey, çıkmayan bir e-postaya artık
    "gönderildi" denmemesi.
*/
Route::post('/email/verification-notification', SendEmailVerificationNotificationController::class)
    ->middleware(['auth:web', 'throttle:verification'])
    ->name('verification.send');

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
