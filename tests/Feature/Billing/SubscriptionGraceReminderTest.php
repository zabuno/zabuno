<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * ÖDEMESİZ SÜREYE GİRİŞ HİZMET E-POSTASI — `docs/107` Faz 1.3'ün TEK açık
 * maddesi: "sahip ödemesiz süreye girdiğini yalnız panele bakarsa öğrenir —
 * e-posta hatırlatması yok."
 *
 * ═══ BU TESTİN DONDURDUĞU SÖZLEŞME ═══
 *
 *  1. **YALNIZ `Grace`.** Evre `SubscriptionLifecycle`'ın MEVCUT tek
 *     hesabından okunur; bu paket ikinci bir evre hesabı açmaz. `Active`,
 *     `Cancelling`, `Ended` ve `Suspended` sessizdir. İptal etmiş sahibe
 *     "ödemen gecikti" demek, kabul ettiğimiz iptali kabul etmemek olurdu;
 *     askıdaki sahibe ödemesiz süreyi haber vermek ise geçmiş bir tarihi
 *     bugünmüş gibi sunmaktır.
 *
 *  2. **ALICI: ÇALIŞMA ALANININ BUGÜNKÜ HER SAHİBİ.** Fatura profilindeki
 *     e-posta DEĞİL. O adres muhasebecinin/mali müşavirin adresi olabilir
 *     ve belgeyi alan kişi ile paneli açıp ödemeyi yapabilen kişi aynı
 *     kişi değildir. Hatırlatma bir BELGE değil, bir DAVRANIŞ çağrısıdır:
 *     yalnız o davranışı yapabilen role gider.
 *
 *  3. **METİN İKİ TARİHİ VE BİR YOLU TAŞIR.** Dönemin bitişi, ödemesiz
 *     sürenin bitişi (= ücretli özelliklerin açık kalacağı SON tarih) ve
 *     `/app#billing`. Bir GÜN SAYISI değil TARİH yazılır: "7 gün kaldı"
 *     cümlesi, e-postanın okunduğu güne göre yanlışlaşır ve bu depo aynı
 *     dersi veri hakları bildiriminde bir kez öğrendi.
 *
 *  4. **KORKUYU BÜYÜTMEZ.** Yayınlanmış menü ve veri KORUNUR; kapanan
 *     yalnız planın verdiği EK yeteneklerdir (`SubscriptionPhase::Suspended`
 *     belgesi: "Hesap kapandı DEĞİLDİR"). Bunu söylemeyen bir hatırlatma,
 *     masadaki karekodun söneceğini sandıran bir hatırlatmadır.
 *
 *  5. **IDEMPOTENCY: abonelik/çalışma alanı + DÖNEMİN `ends_at`'i + ALICI.**
 *     Günde bir koşan bir tarama, ödemesiz süre boyunca aynı sahibe yedi
 *     kez aynı postayı atardı. Damga GERÇEK bir dışarı gönderim
 *     başarısında basılır; `log` sürücüsü gönderim DEĞİLDİR (bu deponun
 *     `MailSupportNotifier`/`MailDataRightsNotifier` sözleşmesi) ve damga
 *     basmaz — aksi hâlde taşıyıcı takılan bir kurulumda hatırlatma
 *     "gönderildi" sayılır ve bir daha hiç denenmezdi. Yeni bir dönem yeni
 *     bir olaydır ve yeniden haber verilir.
 *
 *  6. **BİR ALICININ DÜŞMESİ TARAMAYI KESMEZ.** Sebep kaydedilir, diğer
 *     alıcılar postasını alır ve düşen alıcı ödemesiz süre sürdükçe ertesi
 *     günkü koşuda yeniden denenir — başarıya kadar, başarıdan sonra bir
 *     daha değil (`RunDueErasures` ile aynı ders).
 *
 *  7. **KULLANICI DİLİ ALANI YOKTUR** (`users` tablosunda locale sütunu
 *     yok) ve bu paket öyle bir şema AÇMAZ. Metin, deponun mevcut
 *     kaynak-dil geri düşüşüyle üretilir (`SiteText::pick(null)` →
 *     `i18n.source_locale`), yani uygulamanın o anki diline göre
 *     DEĞİŞMEZ — zamanlayıcıdan koşan bir işin "o anki dili" zaten
 *     kimsenin seçtiği bir dil değildir.
 *
 * Requirement IDs: GRACE-REMINDER-01..05.
 */
final class SubscriptionGraceReminderTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'zabuno:send-grace-reminders';

    private const GRACE_DAYS = 7;

    /** Ödenmiş dönemin bitişi ve (ends_at + 7) ödemesiz sürenin bitişi. */
    private const PERIOD_ENDS_AT = '2026-09-30 10:00:00';

    private const PERIOD_END_DATE = '2026-09-30';

    private const GRACE_END_DATE = '2026-10-07';

    /** Dönem bitti, ödemesiz süre sürüyor: 30 Eylül < 2 Ekim < 7 Ekim. */
    private const NOW_IN_GRACE = '2026-10-02 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        /*
            SAYILAR TESTTE SABİTLENİR, KONFİGÜRASYONDAN OKUNMAZ. Ödemesiz
            süre bir `.env` değeridir; testin onu okuması, değerin bir gün
            değişmesiyle testin sessizce başka bir şeyi ölçmeye başlaması
            demekti.
        */
        config()->set('billing.subscription.grace_days', self::GRACE_DAYS);
        config()->set('billing.subscription.period_days', 30);

        Carbon::setTestNow(self::NOW_IN_GRACE);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- kurulum yardımcıları ---------------------------------------------

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
            'slug' => 'kadikoy-'.Str::random(8),
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->addMember($workspaceId, $owner, 'owner');

        return $workspaceId;
    }

    private function addMember(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function planId(): int
    {
        return (int) DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'code' => 'pro-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['menu.rich_media']),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function subscribe(int $workspaceId, string $endsAt, ?string $cancelledAt = null): void
    {
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_id' => $this->planId(),
            // `state` EVRE DEĞİLDİR: yaşayan bir abonelik satırı olduğunu
            // söyler, hangi evrede olduğunu değil (`SubscriptionPhase`).
            'state' => 'active',
            'ends_at' => Carbon::parse($endsAt),
            'cancelled_at' => $cancelledAt === null ? null : Carbon::parse($cancelledAt),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function billingProfileEmail(int $workspaceId, string $email): void
    {
        DB::table('billing_profiles')->insert([
            'workspace_id' => $workspaceId,
            'legal_name' => 'Kadıköy Kebap A.Ş.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'address' => 'Moda Caddesi 1',
            'city' => 'İstanbul',
            'country' => 'TR',
            'email' => $email,
            'phone' => '+902160000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- gözlem yardımcıları ----------------------------------------------

    private function transport(): ArrayTransport
    {
        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();

        return $transport;
    }

    /**
     * `Mail::fake()` KULLANILMADI ve bu bilinçli (`WorkspaceErasureTest` ile
     * aynı gerekçe): sahte posta şablonu hiç çizmez, yani bozuk bir Blade
     * dosyası testten geçer ve arıza yalnız üretimde görünürdü.
     *
     * @return list<Email>
     */
    private function sentMails(): array
    {
        $mails = [];

        foreach ($this->transport()->messages() as $sent) {
            $message = $sent->getOriginalMessage();

            if ($message instanceof Email) {
                $mails[] = $message;
            }
        }

        return $mails;
    }

    /** @return list<string> */
    private function recipients(): array
    {
        $addresses = [];

        foreach ($this->sentMails() as $mail) {
            foreach ($mail->getTo() as $address) {
                $addresses[] = $address->getAddress();
            }
        }

        sort($addresses);

        return $addresses;
    }

    /** Gövde metin VE HTML'i kapsar: hatırlatmanın hangi gövdede çıktığı sözleşme değildir. */
    private function bodyOf(Email $mail): string
    {
        return (string) $mail->getTextBody()."\n".(string) $mail->getHtmlBody();
    }

    private function flushMails(): void
    {
        $this->transport()->flush();
    }

    private function runReminder(): int
    {
        return $this->artisan(self::COMMAND)->run();
    }

    /** @return list<Event> */
    private function scheduledEvents(): array
    {
        return array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, self::COMMAND),
        ));
    }

    // --- 1. ÖDEMESİZ SÜREDEKİ SAHİPLER HABER ALIR --------------------------

    public function test_grace_reminds_every_current_owner_with_both_dates_and_the_panel_path(): void
    {
        /*
            ÖNCE ÇAĞIRAN, SONRA ÇAĞRILAN. Zamanlayıcıya bağlanmamış bir
            komut, ekranda verilmiş ama tutulmayan bir sözdür; bu depo o
            dersi medya çöp kutusunda bir kez öğrendi (FF-161). Günde bir,
            çünkü ödemesiz süre gün ölçeğindedir; `withoutOverlapping`,
            çünkü paylaşımlı barındırmada dakikada bir koşan kuyrukla aynı
            makineyi paylaşır ve uzayan bir tarama ertesi koşunun üstüne
            binmemelidir.
        */
        self::assertCount(
            1,
            $this->scheduledEvents(),
            'GRACE-REMINDER-01: `'.self::COMMAND.'` zamanlayıcıda tam olarak bir kez olmalı.'
        );

        foreach ($this->scheduledEvents() as $event) {
            self::assertMatchesRegularExpression(
                '/^\d{1,2} \d{1,2} \* \* \*$/',
                $event->expression,
                'GRACE-REMINDER-01: hatırlatma gün ölçeğinde bir iştir (her gün, sabit saatte).'
            );
            self::assertTrue(
                $event->withoutOverlapping,
                'GRACE-REMINDER-01: uzayan bir tarama ertesi koşuyla üst üste binmemeli.'
            );
        }

        $firstOwner = $this->verifiedUser('sahip@kadikoykebap.test');
        $secondOwner = $this->verifiedUser('ortak@kadikoykebap.test');
        $manager = $this->verifiedUser('mudur@kadikoykebap.test');

        $workspaceId = $this->workspaceOwnedBy($firstOwner);
        $this->addMember($workspaceId, $secondOwner, 'owner');
        $this->addMember($workspaceId, $manager, 'manager');
        $this->billingProfileEmail($workspaceId, 'mali-musavir@disaridan.test');

        $this->subscribe($workspaceId, self::PERIOD_ENDS_AT);

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            ['ortak@kadikoykebap.test', 'sahip@kadikoykebap.test'],
            $this->recipients(),
            'GRACE-REMINDER-01: hatırlatma çalışma alanının BUGÜNKÜ her sahibine gider; '
            .'müdüre ve fatura profilindeki adrese gitmez — ödemeyi yapabilen rol sahiptir.'
        );

        $mails = $this->sentMails();

        self::assertCount(
            2,
            $mails,
            'GRACE-REMINDER-01: her alıcı KENDİ postasını alır; tek postaya iki alıcı yazmak, '
            .'bir adresin düşmesiyle diğerini de düşürürdü.'
        );

        foreach ($mails as $mail) {
            $body = $this->bodyOf($mail);

            self::assertStringContainsString(
                self::PERIOD_END_DATE,
                $body,
                'GRACE-REMINDER-01: dönemin bitiş TARİHİ yazılır (gün sayısı, okunduğu güne göre yanlışlaşır).'
            );
            self::assertStringContainsString(
                self::GRACE_END_DATE,
                $body,
                'GRACE-REMINDER-01: ödemesiz sürenin bitişi = ücretli özelliklerin açık kalacağı SON tarih.'
            );
            self::assertStringContainsString(
                '/app#billing',
                $body,
                'GRACE-REMINDER-01: sahip ödemeyi nereden yapacağını okumadan bu postayı kapatmamalı.'
            );
            self::assertMatchesRegularExpression(
                '/menu/i',
                $body,
                'GRACE-REMINDER-01: yayınlanmış menünün korunduğu SÖYLENİR; söylenmezse sahip '
                .'masadaki karekodun söneceğini sanır.'
            );
            self::assertMatchesRegularExpression(
                '/(stay|stays|remain|remains|keep|kept|live|online|visible)/i',
                $body,
                'GRACE-REMINDER-01: "hesap kapanıyor" değil; kapanan yalnız planın EK yetenekleridir.'
            );
        }

        /*
            DİL: kullanıcı tercihi ŞEMASI YOK, kaynak dile geri düşülür.
            Aynı koşu farklı bir uygulama dili altında AYNI gövdeyi
            üretmelidir — üretmiyorsa metin, kimsenin seçmediği bir dile
            (zamanlayıcı sürecinin o anki diline) bağlanmış demektir.
        */
        $bodyUnderDefaultLocale = $this->bodyOf($mails[0]);

        $this->flushMails();
        DB::table('subscriptions')->where('workspace_id', $workspaceId)->delete();

        $otherOwner = $this->verifiedUser('sahip@ikinci-kebap.test');
        $otherWorkspaceId = $this->workspaceOwnedBy($otherOwner);
        $this->subscribe($otherWorkspaceId, self::PERIOD_ENDS_AT);

        $this->app->setLocale('tr');
        self::assertSame(Command::SUCCESS, $this->runReminder());

        $localeMails = $this->sentMails();
        self::assertCount(1, $localeMails);

        self::assertSame(
            $bodyUnderDefaultLocale,
            $this->bodyOf($localeMails[0]),
            'GRACE-REMINDER-01: `users` tablosunda dil sütunu YOK; metin mevcut kaynak-dil geri '
            .'düşüşüyle üretilir ve uygulamanın o anki diline göre değişmez.'
        );
    }

    // --- 2. AYNI DÖNEMDE BİR KEZ, YENİ DÖNEMDE YENİDEN ---------------------

    public function test_a_successful_run_never_repeats_in_the_same_period_but_a_new_period_notifies_again(): void
    {
        $owner = $this->verifiedUser('sahip@tekrar-kebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, self::PERIOD_ENDS_AT);

        self::assertSame(Command::SUCCESS, $this->runReminder());
        self::assertCount(1, $this->sentMails());

        $this->flushMails();

        // Ödemesiz süre yedi gün sürer; komut yedi kez koşar. Ertesi gün:
        Carbon::setTestNow('2026-10-03 09:00:00');
        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            [],
            $this->recipients(),
            'GRACE-REMINDER-02: aynı dönem + aynı alıcı için ikinci bir posta çıkmaz; '
            .'ödemesiz süre boyunca her gün aynı postayı almak, postayı okunmaz kılardı.'
        );

        /*
            YENİ DÖNEM YENİ BİR OLAYDIR. Sahip ödedi (`ends_at` ileri
            taşındı), o dönem de bitti ve yeniden ödemesiz süredeyiz.
            Damga DÖNEMİN `ends_at`'ine bağlı olduğu için bu, susturulmuş
            bir tekrar değil, ilk kez duyulan yeni bir haberdir.
        */
        DB::table('subscriptions')
            ->where('workspace_id', $workspaceId)
            ->update(['ends_at' => Carbon::parse('2026-11-05 10:00:00')]);

        Carbon::setTestNow('2026-11-08 09:00:00');
        $this->flushMails();

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            ['sahip@tekrar-kebap.test'],
            $this->recipients(),
            'GRACE-REMINDER-02: yeni dönem yeniden haber verir.'
        );

        $body = $this->bodyOf($this->sentMails()[0]);
        self::assertStringContainsString('2026-11-05', $body);
        self::assertStringContainsString('2026-11-12', $body);
    }

    // --- 3. DİĞER EVRELER SESSİZ -------------------------------------------

    public function test_active_cancelling_ended_and_suspended_subscriptions_stay_silent(): void
    {
        // Ödenmiş dönem sürüyor: haber verilecek bir gecikme yok.
        $this->subscribe($this->workspaceOwnedBy($this->verifiedUser('aktif@kebap.test')), '2026-10-20 10:00:00');

        // İptal etti, dönem sürüyor: çıkışını kabul ettik; "ödemen gecikti" demeyiz.
        $this->subscribe(
            $this->workspaceOwnedBy($this->verifiedUser('iptal@kebap.test')),
            '2026-10-20 10:00:00',
            '2026-10-01 12:00:00',
        );

        // İptal etti ve dönem bitti: iptalliye ödemesiz süre VERİLMEZ (`SubscriptionLifecycle`).
        $this->subscribe(
            $this->workspaceOwnedBy($this->verifiedUser('biten@kebap.test')),
            '2026-09-30 10:00:00',
            '2026-09-25 12:00:00',
        );

        // Ödemesiz süre de doldu (1 Eylül + 7 gün < 2 Ekim): artık askıda.
        $this->subscribe($this->workspaceOwnedBy($this->verifiedUser('askida@kebap.test')), '2026-09-01 10:00:00');

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            [],
            $this->recipients(),
            'GRACE-REMINDER-03: hatırlatma YALNIZ `Grace` evresinin postasıdır; diğer dört evre sessizdir.'
        );
    }

    // --- 4. `log` SÜRÜCÜSÜ GÖNDERİM DEĞİLDİR -------------------------------

    public function test_the_log_transport_is_not_a_delivery_and_leaves_the_reminder_owed(): void
    {
        /*
            Bu deponun dağıtım sözleşmesinde `mail.default` taşıyıcı
            girilene kadar `log`tur: mesaj bir dosyaya yazılır ve kimseye
            ulaşmaz. Ona damga basmak, sahibin hiç gelmeyecek bir postayı
            beklemesi ve hatırlatmanın bir daha HİÇ denenmemesi demekti.
        */
        config()->set('mail.default', 'log');

        $owner = $this->verifiedUser('sahip@tasiyicisiz-kebap.test');
        $workspaceId = $this->workspaceOwnedBy($owner);
        $this->subscribe($workspaceId, self::PERIOD_ENDS_AT);

        $this->runReminder();

        self::assertSame(
            [],
            $this->recipients(),
            'GRACE-REMINDER-04: `log` sürücüsü dışarı giden bir taşıyıcı değildir.'
        );

        // Gerçek bir taşıyıcı geldiğinde hatırlatma HÂLÂ borçtur ve çıkar.
        config()->set('mail.default', 'array');

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            ['sahip@tasiyicisiz-kebap.test'],
            $this->recipients(),
            'GRACE-REMINDER-04: `log` koşusu damga basmadığı için, taşıyıcı gelir gelmez posta çıkar.'
        );
    }

    // --- 5. DÜŞEN ALICI TARAMAYI KESMEZ, BAŞARIYA KADAR DENENİR ------------

    public function test_one_failing_recipient_is_recorded_without_stopping_the_scan_and_is_retried_until_it_succeeds(): void
    {
        $failing = 'dusen@kadikoykebap.test';

        $firstOwner = $this->verifiedUser($failing);
        $secondOwner = $this->verifiedUser('saglam@kadikoykebap.test');

        $workspaceId = $this->workspaceOwnedBy($firstOwner);
        $this->addMember($workspaceId, $secondOwner, 'owner');
        $this->subscribe($workspaceId, self::PERIOD_ENDS_AT);

        EventFacade::listen(function (MessageSending $event) use ($failing): void {
            foreach ($event->message->getTo() as $address) {
                if ($address->getAddress() === $failing) {
                    throw new RuntimeException('mailgun-temporary-failure');
                }
            }
        });

        Log::spy();

        self::assertSame(
            Command::FAILURE,
            $this->runReminder(),
            'GRACE-REMINDER-05: düşen bir gönderimden sonra sessizce BAŞARILI dönmek, '
            .'arızayı zamanlayıcının günlüğünde görünmez kılardı.'
        );

        self::assertSame(
            ['saglam@kadikoykebap.test'],
            $this->recipients(),
            'GRACE-REMINDER-05: bir alıcının düşmesi taramayı kesmez; diğer sahip postasını alır.'
        );

        Log::shouldHaveReceived('warning')
            ->withArgs(static function (string $message, array $context = []): bool {
                return isset($context['reason']) && trim((string) $context['reason']) !== '';
            });

        // Ertesi günkü koşu: taşıyıcı toparlandı, ödemesiz süre sürüyor.
        EventFacade::forget(MessageSending::class);
        $this->flushMails();
        Carbon::setTestNow('2026-10-03 09:00:00');

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            [$failing],
            $this->recipients(),
            'GRACE-REMINDER-05: düşen alıcı ödemesiz süre sürdükçe yeniden denenir; '
            .'başaran alıcı ise ikinci kez rahatsız edilmez.'
        );

        $this->flushMails();
        Carbon::setTestNow('2026-10-04 09:00:00');

        self::assertSame(Command::SUCCESS, $this->runReminder());

        self::assertSame(
            [],
            $this->recipients(),
            'GRACE-REMINDER-05: BAŞARIDAN SONRA durur — tekrar, yalnız başarısızlığın telafisidir.'
        );
    }
}
