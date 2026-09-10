<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Port\DataRightsNotifierPort;
use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Support\Dto\ReceivedSupportRequest;
use App\Application\Support\Dto\SupportAccessSessionRow;
use App\Application\Support\Port\SupportAccessNotifierPort;
use App\Application\Support\Port\SupportNotifierPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;
use App\Domain\Support\SupportChannel;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * TAŞIYICI ARIZASI, KAYDEDİLMİŞ BİR TALEBİ 500'E ÇEVİREMEZ.
 *
 * Sahibin yolculuğu: müşteri destek formunu doldurur, talep veritabanına
 * YAZILIR ve referansı üretilir; hemen ardından alındı e-postası
 * gönderilecektir. Mailgun uç noktası panelde yanlış yazılmışsa gönderici
 * seçimi bir istisna atar (bkz. `MailTransportSelectorPort`). O istisna
 * bildirim ağının DIŞINDA doğarsa isteğin tepesine çıkar: ekranda beyaz bir
 * hata sayfası belirir, kullanıcı talebinin kaydedilmediğini sanıp tekrar
 * gönderir, ve satır "bildirilemedi" sebebini bile alamaz — çünkü o sebebi
 * yazacak kod hiç çalışmaz.
 *
 * Veri hakkı (silme / dışa aktarma) talebinde aynı arıza daha ağırdır:
 * silme ZATEN planlanmıştır, geri alınmaz, ve kullanıcı yine 500 görür.
 *
 * Bu dosya üç bildiricinin de arızayı KENDİ hata yolunda karşıladığını ve
 * çağırana arındırılmış, kırpılmış bir sebep döndürdüğünü ölçer.
 */
final class VaultMailTransportFaultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seçicinin gerçekte attığı türden, arındırılmış bir mesaj: hangi
     * ayarın yanlış olduğunu söyler, değerin kendisini tekrar etmez.
     */
    public const SANITIZED_FAULT = 'Kayıtlı Mailgun uç noktası taşıyıcının host alanına taşınamıyor '
        .'(sağlayıcı: mailgun, sebep: endpoint-not-carryable-as-host).';

    protected function setUp(): void
    {
        parent::setUp();

        /*
            Varsayılan `log` DEĞİL: bildiriciler `log` sürücüsünü görünce
            daha gönderime hiç girmeden "dışarı giden taşıyıcı yok" deyip
            çıkar. Bu dosyanın ölçtüğü yol ise seçimin KENDİSİNİN patladığı
            yoldur; sahte seçici zaten hiçbir ad döndürmüyor.
        */
        Config::set('mail.default', 'array');

        $this->app->bind(
            MailTransportSelectorPort::class,
            fn (): MailTransportSelectorPort => new class implements MailTransportSelectorPort
            {
                public function select(): string
                {
                    throw new RuntimeException(VaultMailTransportFaultTest::SANITIZED_FAULT);
                }
            }
        );
    }

    private function supportRequest(): ReceivedSupportRequest
    {
        return new ReceivedSupportRequest(
            id: 41,
            reference: 'ZBN-TEST-0041',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            subject: 'Menü yüklenmiyor',
            message: 'QR kodu okuttum, menü açılmadı.',
            channel: SupportChannel::PublicContact,
            locale: 'tr',
            workspaceId: null,
            receivedAt: new DateTimeImmutable('2026-09-10 09:00:00'),
        );
    }

    // --- PRD-MAILGUN-NOTIFIER-FAULT-IS-RECORDED-01 -----------------------

    #[Test]
    public function the_support_acknowledgement_records_the_fault_instead_of_throwing(): void
    {
        $reason = app(SupportNotifierPort::class)->acknowledge($this->supportRequest());

        self::assertIsString(
            $reason,
            'PRD-MAILGUN: taşıyıcı arızası alındı e-postası yolunda yakalanmadı; talep kaydedilmişken çağıran 500 alır.'
        );
        self::assertNotSame('', $reason);
        self::assertLessThanOrEqual(190, mb_strlen($reason));
    }

    #[Test]
    public function the_owner_notification_records_the_fault_instead_of_throwing(): void
    {
        Config::set('contact.notify', 'destek@example.com');

        $reason = app(SupportNotifierPort::class)->notifyOwner($this->supportRequest());

        self::assertIsString(
            $reason,
            'PRD-MAILGUN: taşıyıcı arızası sahibe bildirim yolunda yakalanmadı.'
        );
        self::assertNotSame('', $reason);
    }

    #[Test]
    public function the_data_rights_notice_records_the_fault_instead_of_throwing(): void
    {
        $row = new DataRequestRow(
            id: 7,
            workspaceId: 1,
            kind: DataRequestKind::Erasure,
            state: DataRequestState::Completed,
            requestedByEmail: 'ada@example.com',
            requestedAt: '2026-09-10 09:00:00',
            scopeSections: [],
            artifactBytes: null,
            availableUntil: null,
            scheduledFor: '2026-10-10 09:00:00',
            completedAt: null,
            cancelledAt: null,
            failureReason: null,
            notifiedAt: null,
            notificationFailure: null,
            deletedCounts: [],
        );

        $reason = app(DataRightsNotifierPort::class)->notify($row, 'ada@example.com', null);

        self::assertIsString(
            $reason,
            'PRD-MAILGUN: taşıyıcı arızası veri hakkı bildiriminde yakalanmadı; silme planlanmışken çağıran 500 alır.'
        );
        self::assertNotSame('', $reason);
    }

    #[Test]
    public function the_support_access_notice_records_the_fault_instead_of_throwing(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı Ada',
            'slug' => 'kebapci-ada',
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

        $session = new SupportAccessSessionRow(
            id: 3,
            workspaceId: $workspaceId,
            actorEmail: 'platform@example.com',
            reason: 'Sipariş kaydı incelemesi',
            startedAt: '2026-09-10 09:00:00',
            expiresAt: '2026-09-10 10:00:00',
            endedAt: null,
            active: true,
        );

        $reason = app(SupportAccessNotifierPort::class)->notifyWorkspaceOwners($session, 'Kebapçı Ada');

        self::assertIsString(
            $reason,
            'PRD-MAILGUN: taşıyıcı arızası destek erişimi bildiriminde yakalanmadı; erişim açılmışken çağıran 500 alır.'
        );
        self::assertNotSame('', $reason);
    }
}
