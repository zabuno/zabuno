<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Platform\PlatformRole;
use App\Domain\Support\SupportAccessWindow;
use App\Http\Middleware\EnsureSupportAccessIsReadOnly;
use App\Mail\SupportAccessOpened;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * KİRACI OLARAK BAKMA — `docs/122` Y7 ve §5, `docs/133`.
 *
 * `docs/122` §5 bu paketin sözleşmesidir ve dört şart koyar; bu dosyanın
 * her testi o dört şarttan birini DONDURUR:
 *
 *  1. **Her oturum bir sebep ister.** Sebepsiz, boşluktan ibaret ya da tek
 *     kelimelik bir sebep oturumu açtırmaz.
 *  2. **Süreli olur.** Bitiş anı açılışta yazılır, yapılandırmadan gelir,
 *     uzatılamaz ve süre dolduğunda oturum kendiliğinden biter — kimse
 *     "çıkış" yapmasa bile.
 *  3. **Kiracının GÖREBİLECEĞİ biçimde yazılır.** Kayıt, sahibin kendi
 *     denetim izi ekranından okunur; süperadmin tarafında gösterilmesi
 *     yeterli sayılmaz.
 *  4. **Yapılabilecekler kısıtlıdır.** Yazma İSTEK DÜZEYİNDE kapalıdır:
 *     ödeme, plan değişikliği, silme, davet ve yayın uçlarının hepsi aynı
 *     kilide çarpar ve hiçbiri denetleyicisine ulaşmaz.
 *
 * BU DOSYA YASAĞI DİZGENİN GEÇTİĞİ YERDE DEĞİL, KULLANILDIĞI YERDE ARAR:
 * beş ayrı gerçek yazma ucuna gerçek istek atılır ve hepsinin reddedildiği
 * ölçülür. "Kodda bir `if` var" bir kanıt değildir; reddedilmiş bir istek
 * kanıttır.
 */
final class TenantSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array{workspace:int, location:int, menu:int, owner:User} */
    private function tenant(string $seed): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı '.$seed,
            'slug' => $seed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Marka '.$seed,
            'slug' => $seed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$seed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$seed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü '.$seed,
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['workspace' => $workspaceId, 'location' => $locationId, 'menu' => $menuId, 'owner' => $owner];
    }

    private const REASON = 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).';

    // --- ŞART 1: SEBEP ZORUNLU ---------------------------------------------

    #[Test]
    public function a_session_cannot_be_opened_without_a_reason(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('sebepsiz');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }

    #[Test]
    public function a_reason_made_of_whitespace_is_not_a_reason(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('bosluk');

        /*
            Kırpma DOĞRULAMADAN ÖNCE olur. Sonra olsaydı "               "
            `required` ve `min` kapılarını geçer, kiracının panelinde boş bir
            sebep olarak görünürdü — ve görünen boş bir sebep, sebep
            sorulmamasıyla aynı şeydir.
        */
        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => '                    '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }

    #[Test]
    public function a_one_word_reason_is_refused(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('tekkelime');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => 'test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    #[Test]
    public function the_reason_is_stored_verbatim_and_returned(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('sebep-yazildi');

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => '  '.self::REASON.'  ']);

        $response->assertCreated();
        $response->assertJsonPath('session.reason', self::REASON);
        $response->assertJsonPath('session.workspaceId', $tenant['workspace']);

        $this->assertSame(self::REASON, (string) DB::table('support_access_sessions')->value('reason'));
    }

    // --- ŞART 2: SÜRELİ -----------------------------------------------------

    #[Test]
    public function the_window_comes_from_configuration_and_is_not_hard_coded(): void
    {
        Mail::fake();
        config(['support.access_session_minutes' => 7]);

        $admin = $this->superAdmin();
        $tenant = $this->tenant('sure-yapilandirma');

        Carbon::setTestNow('2026-09-07 10:00:00');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated()
            ->assertJsonPath('windowMinutes', 7);

        $expiresAt = Carbon::parse((string) DB::table('support_access_sessions')->value('expires_at'));
        $this->assertSame('2026-09-07 10:07:00', $expiresAt->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    #[Test]
    public function an_unreadable_window_setting_is_not_unlimited(): void
    {
        /*
            Bir yazım hatasının bütün gün açık kalan bir oturuma dönüşmesi,
            bu paketin engellemek için var olduğu kusurun ta kendisi. Boş,
            sıfır, negatif ya da sayı olmayan bir değer sonsuz değil,
            `FALLBACK_MINUTES`'tır. Tavan da ayrı yaşar: süreyi sahip seçer,
            "süreli olma" özelliğini yapılandırma iptal edemez.
        */
        $window = new SupportAccessWindow;

        foreach ([null, '', '0', '-5', 'yarın', []] as $bad) {
            config(['support.access_session_minutes' => $bad]);
            $this->assertSame(SupportAccessWindow::FALLBACK_MINUTES, $window->minutes());
        }

        config(['support.access_session_minutes' => 100000, 'support.access_session_max_minutes' => 60]);
        $this->assertSame(60, $window->minutes());
    }

    #[Test]
    public function the_session_ends_by_itself_when_the_window_closes(): void
    {
        Mail::fake();
        config(['support.access_session_minutes' => 15]);

        $admin = $this->superAdmin();
        $tenant = $this->tenant('sure-doldu');

        Carbon::setTestNow('2026-09-07 10:00:00');
        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        // Bir dakika kala: hâlâ açık, hâlâ salt okunur.
        Carbon::setTestNow('2026-09-07 10:14:00');
        $this->actingAs($admin)->getJson('/api/admin/support-access')->assertJsonPath('session.active', true);

        /*
            SÜRE DOLMASI BİR İŞ DEĞİL, BİR SORGU. Hiçbir kuyruk işçisi, cron
            ya da zamanlayıcı koşmadı; yalnız saat ilerledi. Kapanma bir
            zamanlayıcıya bağlansaydı, zamanlayıcısı çalışmayan bir kurulumda
            oturum sessizce açık kalırdı.
        */
        Carbon::setTestNow('2026-09-07 10:16:00');
        $this->actingAs($admin)->getJson('/api/admin/support-access')->assertJsonPath('session', null);

        // Ve süresi dolan oturum artık hiçbir kapıyı açık tutmuyor:
        // yazma kilidi kalktı, kiracıyı görme izni de gitti.
        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$tenant['workspace']}/brand/locations")
            ->assertNotFound();

        Carbon::setTestNow();
    }

    #[Test]
    public function there_is_no_route_that_extends_a_session(): void
    {
        /*
            Uzatılabilir bir süre yalnız ertelenmiş bir süresizliktir
            (`docs/122` §5). Bu test bir dizgeyi değil, YÖNLENDİRME
            TABLOSUNU okur: uzatma bir gün eklenirse burada patlar.
        */
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            if (str_contains($route->uri(), 'support-access')) {
                $paths[] = $route->methods()[0].' '.$route->uri();
            }
        }

        sort($paths);

        $this->assertSame([
            'GET api/admin/support-access',
            'POST api/admin/support-access/end',
            'POST api/admin/workspaces/{workspace}/support-access',
        ], $paths);
    }

    #[Test]
    public function a_second_session_cannot_stack_on_an_open_one(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $first = $this->tenant('ilk');
        $second = $this->tenant('ikinci');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$first['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        /*
            İkinci oturum iki şeyin birden yolu olurdu: sessizce süre
            uzatmanın ve iki kiracıya aynı anda bakmanın. İkisini de kapatan
            şey ayrı bir kural DEĞİL, kilidin kendisidir: oturum açma ucu da
            bir yazmadır ve açık bir oturumda her yazma reddedilir. Tek
            kural, iki kusuru birden kapatıyor.
        */
        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$second['workspace']}/support-access", ['reason' => self::REASON])
            ->assertStatus(403)
            ->assertJsonPath('supportAccess.workspaceId', $first['workspace']);

        $this->assertSame(1, DB::table('support_access_sessions')->count());
    }

    // --- ŞART 3: KİRACININ GÖREBİLECEĞİ KAYIT -------------------------------

    #[Test]
    public function the_owner_sees_the_visit_in_their_own_panel(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('sahip-goruyor');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        /*
            SAHİBİN KENDİ EKRANI. Bu uç Ayarlar'ın dördüncü sekmesini besler
            (`AuditTrailRegion`) ve sahibin "bunu kim, ne zaman yaptı?" diye
            zaten baktığı yerdir. Yeni bir uç açılmadı: sahibin öğrenmesi
            gereken yeni bir yer yok.
        */
        $response = $this->actingAs($tenant['owner'])
            ->getJson("/api/workspaces/{$tenant['workspace']}/audit-trail");

        $response->assertOk();

        // Ayrı alan: kayıt yüz satırlık bir izin ortasına gömülmüyor.
        $response->assertJsonPath('supportAccess.0.reason', self::REASON);
        $response->assertJsonPath('supportAccess.0.actor', $admin->email);
        $response->assertJsonPath('supportAccess.0.active', true);

        // Ve zaman çizgisinde de var: kendi menü/medya olaylarıyla aynı sırada.
        $sources = array_column($response->json('data'), 'source');
        $this->assertContains('support-access', $sources);

        $row = collect($response->json('data'))->firstWhere('source', 'support-access');
        $this->assertSame(self::REASON, $row['subject']);
        $this->assertSame($admin->email, $row['actor']);
    }

    #[Test]
    public function the_record_survives_the_session_and_stays_readable_by_the_owner(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('kayit-kaliyor');

        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        // Ertesi gün: oturum çoktan bitti, kayıt duruyor ve "bitti" diyor.
        Carbon::setTestNow('2026-09-08 09:00:00');

        $response = $this->actingAs($tenant['owner'])
            ->getJson("/api/workspaces/{$tenant['workspace']}/audit-trail");

        $response->assertJsonPath('supportAccess.0.reason', self::REASON);
        $response->assertJsonPath('supportAccess.0.active', false);

        Carbon::setTestNow();
    }

    #[Test]
    public function the_owner_is_told_by_email_when_a_transport_exists(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);

        $admin = $this->superAdmin();
        $tenant = $this->tenant('sahibe-eposta');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        Mail::assertSent(
            SupportAccessOpened::class,
            fn (SupportAccessOpened $mail): bool => $mail->hasTo($tenant['owner']->email)
                && $mail->reason === self::REASON,
        );

        $this->assertNotNull(DB::table('support_access_sessions')->value('notified_at'));
    }

    #[Test]
    public function no_transport_means_no_stamp_and_no_lie(): void
    {
        Mail::fake();
        // `log` sürücüsü "dışarı giden taşıyıcı yok" demektir (`docs/93`).
        config(['mail.default' => 'log']);

        $admin = $this->superAdmin();
        $tenant = $this->tenant('tasiyici-yok');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        Mail::assertNothingSent();

        $row = DB::table('support_access_sessions')->first();
        $this->assertNull($row->notified_at);
        $this->assertNull($row->notification_failure);
        // Ama kayıt yine yazıldı: sahip panelinde görür, e-posta çıkmasa da.
        $this->assertSame(self::REASON, (string) $row->reason);
    }

    // --- ŞART 4: YAZMA YOK, İSTEK DÜZEYİNDE --------------------------------

    /**
     * Beş yazma ucu, beş ayrı aile — `docs/122` §5'in saydığı fiillerin
     * hepsi: ödeme, plan/kip değişikliği, silme, davet, yayın.
     *
     * Hiçbiri kendi denetleyicisine ULAŞMAZ. Kilit ara katmandadır ve
     * denetleyici yazılmamış olsa bile aynı cevabı verirdi — yasak dizgenin
     * geçtiği yerde değil, isteğin geçtiği yerde.
     *
     * @return list<array{0:string, 1:string, 2:array<string, mixed>}>
     */
    public static function writeEndpoints(): array
    {
        return [
            'ödeme' => ['postJson', '/api/admin/workspaces/{workspace}/manual-payments', ['amount' => 100]],
            'kip değişikliği' => ['putJson', '/api/admin/settings/billing-mode', ['mode' => 'live']],
            'silme' => ['deleteJson', '/api/workspaces/{workspace}/menu/{menu}', []],
            'davet' => ['postJson', '/api/workspaces/{workspace}/team/invitations', ['email' => 'yeni@example.com', 'role' => 'editor']],
            'yayın' => ['postJson', '/api/workspaces/{workspace}/menu/{menu}/publications', []],
        ];
    }

    #[Test]
    public function every_write_is_refused_at_request_level_during_a_session(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('yazma-yok');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        foreach (self::writeEndpoints() as $label => [$verb, $template, $payload]) {
            $uri = str_replace(
                ['{workspace}', '{menu}'],
                [(string) $tenant['workspace'], (string) $tenant['menu']],
                $template,
            );

            $response = $this->actingAs($admin)->{$verb}($uri, $payload);

            $response->assertStatus(403);
            $response->assertJsonPath('message', 'This support access session is read-only.');
            $this->assertSame(
                $tenant['workspace'],
                $response->json('supportAccess.workspaceId'),
                "[{$label}] reddi destek oturumundan gelmeli, başka bir yetki kapısından değil.",
            );
        }
    }

    #[Test]
    public function the_session_cannot_be_moved_to_another_tenant(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('civili');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        /*
            Bağlam değiştirme bir PUT'tur, yani bir yazma. Oturum boyunca
            kapalı olması bir yan etki değil, bir özellik: bakış tek
            restorana çivilenir ve "sırayla hepsine bakmak" diye bir oturum
            yoktur.
        */
        $other = $this->tenant('oteki');
        $this->actingAs($admin)
            ->putJson('/api/workspace-context', ['workspace_id' => $other['workspace']])
            ->assertStatus(403);
    }

    #[Test]
    public function the_session_does_not_leak_into_a_second_tenant(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $watched = $this->tenant('bakilan');
        $other = $this->tenant('bakilmayan');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$watched['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        // Bakılan kiracı okunabiliyor…
        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$watched['workspace']}/brand/locations")
            ->assertOk();

        // …bakılmayan okunamıyor. Destek oturumu bir kiracıya bakmaktır.
        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$other['workspace']}/brand/locations")
            ->assertNotFound();
    }

    #[Test]
    public function only_read_permissions_are_granted_and_the_screen_draws_no_write_button(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('yalniz-okuma');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        /*
            Bağlam AÇILIRKEN sunucuda kuruldu; destek görevlisi bir yazma
            isteğiyle kiracı seçmedi. Kabuk buradan hangi düğmeleri
            çizeceğini okur (`docs/98` FF-74) — ve okuduğu listede tek bir
            yönetme izni yok.
        */
        $response = $this->actingAs($admin)->getJson('/api/workspace-context');
        $response->assertOk();
        $response->assertJsonPath('id', $tenant['workspace']);

        $permissions = $response->json('permissions');
        sort($permissions);

        $this->assertSame([
            'analytics.view',
            'billing.view',
            'menu.view',
            'order.view',
            'qr.view',
            'rating.view',
            'workspace.view',
        ], $permissions);

        // Rol ADSIZ kalır ve bu dürüsttür: destek görevlisi bu restoranın
        // bir çalışanı değil; ona bir rol adı takmak onu ekipten biri gibi
        // gösterirdi.
        $response->assertJsonPath('role', null);
    }

    #[Test]
    public function the_tenants_own_management_screens_stay_closed(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('yonetim-kapali');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        /*
            `workspace.manage` VERİLMEDİ. Destek görevlisi kiracının ekip
            listesini, destek taleplerini ve kendi denetim izini OKUYAMAZ —
            üçü de yönetme izniyle açılır. Görünmesi gereken şey menü, puan
            ve karekod durumu; ekibin kim olduğu değil.
        */
        // Ekip listesi 404 verir, denetim izi 403: ikisi de var olan
        // kurallardır (`ShowWorkspaceAuditTrailController` iki basamaklı
        // kapıyı açıklıyor) ve destek oturumu hiçbirini gevşetmez.
        $this->actingAs($admin)->getJson("/api/workspaces/{$tenant['workspace']}/team/members")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/workspaces/{$tenant['workspace']}/audit-trail")->assertForbidden();
        $this->actingAs($admin)->getJson("/api/workspaces/{$tenant['workspace']}/support-requests")->assertNotFound();
    }

    #[Test]
    public function ending_the_session_gives_the_platform_admin_their_own_hands_back(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('bitirme');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        // Kilidin iki istisnasından biri: oturumu bitirmek.
        $this->actingAs($admin)
            ->postJson('/api/admin/support-access/end')
            ->assertOk()
            ->assertJsonPath('ended', true)
            ->assertJsonPath('session', null);

        $this->assertNotNull(DB::table('support_access_sessions')->value('ended_at'));

        // Artık normal bir süperadmin: kip anahtarına dokunabiliyor.
        $this->actingAs($admin)
            ->putJson('/api/admin/settings/billing-mode', ['mode' => 'sandbox'])
            ->assertStatus(200);

        // Ve kiracıyı artık göremiyor.
        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$tenant['workspace']}/brand/locations")
            ->assertNotFound();
    }

    #[Test]
    public function ending_a_session_twice_is_not_an_event(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $tenant = $this->tenant('iki-kez-bitir');

        $this->actingAs($admin)
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertCreated();

        $this->actingAs($admin)->postJson('/api/admin/support-access/end')->assertJsonPath('ended', true);
        $this->actingAs($admin)->postJson('/api/admin/support-access/end')->assertJsonPath('ended', false);

        $this->assertSame(1, DB::table('support_access_sessions')->whereNotNull('ended_at')->count());
    }

    #[Test]
    public function a_guest_can_never_open_a_session(): void
    {
        Mail::fake();
        $tenant = $this->tenant('misafir');

        $this->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertUnauthorized();

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }

    #[Test]
    public function a_user_without_the_platform_role_can_never_open_a_session(): void
    {
        Mail::fake();
        $tenant = $this->tenant('yetkisiz');

        // Kiracının KENDİ sahibi bile açamaz: bu yüzey kiracının değil,
        // platformun yüzeyidir. Ve 404, 403 değil — "yetkin yok" demek,
        // yüzeyin var olduğunu söylemektir.
        $this->actingAs($tenant['owner'])
            ->postJson("/api/admin/workspaces/{$tenant['workspace']}/support-access", ['reason' => self::REASON])
            ->assertNotFound();

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }

    #[Test]
    public function an_unknown_tenant_is_a_plain_404_and_writes_nothing(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/api/admin/workspaces/987654/support-access', ['reason' => self::REASON])
            ->assertNotFound();

        $this->assertSame(0, DB::table('support_access_sessions')->count());
    }

    #[Test]
    public function the_read_only_lock_has_exactly_two_exemptions(): void
    {
        /*
            İstisna listesi bir sınıf sabitidir ve bu test onu DONDURUR.
            Üçüncü bir satır eklemek, `docs/133` §4'e ait bir kapsam
            kararıdır ve burada görünür — sessizce eklenemez.
        */
        $this->assertSame(
            ['api/admin/support-access/end', 'logout'],
            EnsureSupportAccessIsReadOnly::EXEMPT_PATHS,
        );
    }

    #[Test]
    public function a_platform_admin_without_a_session_is_not_restricted(): void
    {
        // Kilit yalnız AÇIK bir oturumda çalışır; oturumu olmayan bir
        // süperadmin normal işini yapar. Aksi hâlde bu paket, çözdüğünden
        // fazlasını bozardı.
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings/billing-mode', ['mode' => 'sandbox'])
            ->assertStatus(200);
    }
}
