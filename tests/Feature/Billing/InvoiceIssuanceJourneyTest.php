<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FATURA — tahsilatın karşılığında bir BELGE doğar (docs/107 Faz 1.4,
 * docs/130).
 *
 * Dondurulan sözleşme:
 *  - Başarılı her tahsilat bir fatura kaydı doğurur; kayıt DEĞİŞMEZ
 *    (`updated_at` yok, düzeltme yolu karşı belgedir).
 *  - Aynı ödemenin webhook + callback tekrarı İKİNCİ bir belge yaratmaz.
 *  - İade bir karşı belge (credit_note) keser; fatura silinmez.
 *  - Şirket bilgisi `.env`'de boşken belge bunu SÖYLER ("not yet provided");
 *    uydurulmuş bir ünvan, adres ya da vergi numarası yazılmaz.
 *  - KDV oranı yapılandırmada yoksa fatura KDV ayrımı GÖSTERMEZ; bir oran
 *    varsayılmaz.
 *  - e-arşiv/e-fatura gönderimi YAPILANDIRILMAMIŞ olduğunu söyler; hiçbir
 *    kayıt "gönderildi" durumuna geçmez.
 */
final class InvoiceIssuanceJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_GATEWAY = 'App\Application\Billing\Port\SandboxPaymentGatewayPort';

    private const SANDBOX_SECRET = 'invoice-sandbox-secret-01';

    private const AMOUNT = 149900;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    // --- kurulum ----------------------------------------------------------

    private function configure(): void
    {
        config()->set('services.iyzico.mode', 'sandbox');
        config()->set('services.iyzico.sandbox.api_key', 'sandbox-api-key');
        config()->set('services.iyzico.sandbox.secret_key', self::SANDBOX_SECRET);
        config()->set('services.iyzico.sandbox.base_url', 'https://sandbox-api.iyzipay.com');
        config()->set('billing.subscription.period_days', 30);
        config()->set('billing.invoice.number_prefix', null);
        config()->set('billing.invoice.vat_rate_basis_points', null);
        // Şirket kimliği BOŞ: sahip henüz girmedi (FF-198).
        config()->set('legal.company', [
            'legal_name' => null,
            'address' => null,
            'mersis' => null,
            'tax_office' => null,
            'tax_number' => null,
            'email' => null,
            'phone' => null,
        ]);
    }

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
            'slug' => $slug,
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
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function completeProfile(int $workspaceId): void
    {
        DB::table('billing_profiles')->insert([
            'workspace_id' => $workspaceId,
            'legal_name' => 'Kadıköy Kebap Gıda Ltd. Şti.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'address' => 'Moda Cad. No:1',
            'city' => 'Istanbul',
            'country' => 'TR',
            'email' => 'muhasebe@kadikoykebap.test',
            'phone' => '+905551112233',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function initiatedTransaction(int $workspaceId, string $token, string $conversationId): int
    {
        $userId = (int) DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->value('user_id');

        return (int) DB::table('payment_transactions')->insertGetId([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'plan_id' => $this->planId(),
            'mode' => 'sandbox',
            'idempotency_key' => $conversationId,
            'conversation_id' => $conversationId,
            'token' => $token,
            'redirect_url' => 'https://sandbox-cf.iyzipay.com/'.$token,
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'period_days' => 30,
            'state' => 'initiated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bindFake(string $port): MockInterface
    {
        $fake = Mockery::mock($port);
        app()->instance($port, $fake);

        return $fake;
    }

    /** @return array<string, mixed> */
    private function retrieveResult(string $conversationId, string $status = 'SUCCESS'): array
    {
        return [
            'signature_valid' => true,
            'status' => $status,
            'conversation_id' => $conversationId,
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'payment_id' => 'pay-01',
            'payment_transaction_id' => 'pay-01-tx',
            'error_message' => null,
        ];
    }

    /** Tarayıcı geri dönüşü — ödemeyi uç duruma taşıyan gerçek yol. */
    private function settleViaCallback(string $token, string $conversationId, string $status = 'SUCCESS'): void
    {
        $gateway = $this->bindFake(self::SANDBOX_GATEWAY);
        $gateway->shouldReceive('retrieveCheckout')
            ->andReturn($this->retrieveResult($conversationId, $status));

        $this->post('/api/billing/iyzico/callback', ['token' => $token])->assertRedirect();
    }

    /** @return array{owner: User, workspaceId: int, token: string, conversationId: string, transactionId: int} */
    private function paidWorkspace(string $slug): array
    {
        $this->configure();
        $owner = $this->verifiedUser($slug.'@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, $slug);
        $this->completeProfile($workspaceId);
        $token = 'tok-'.$slug;
        $conversationId = 'conv-'.$slug;
        $transactionId = $this->initiatedTransaction($workspaceId, $token, $conversationId);
        $this->settleViaCallback($token, $conversationId);

        return [
            'owner' => $owner,
            'workspaceId' => $workspaceId,
            'token' => $token,
            'conversationId' => $conversationId,
            'transactionId' => $transactionId,
        ];
    }

    // --- INVOICE-ISSUE-01 -------------------------------------------------

    #[Test]
    public function a_successful_payment_issues_exactly_one_immutable_invoice(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00');
        ['workspaceId' => $workspaceId, 'transactionId' => $transactionId] = $this->paidWorkspace('issue');

        self::assertSame(1, DB::table('invoices')->count(), 'Tahsilat başına TEK belge.');

        $invoice = DB::table('invoices')->first();
        self::assertSame($workspaceId, (int) $invoice->workspace_id);
        self::assertSame($transactionId, (int) $invoice->payment_transaction_id);
        self::assertSame('invoice', $invoice->kind);
        self::assertSame('2026', $invoice->series);
        self::assertSame(1, (int) $invoice->number);
        self::assertSame('2026-000001', $invoice->document_number);
        self::assertSame(self::AMOUNT, (int) $invoice->amount_minor);
        self::assertSame('TRY', $invoice->currency);
        self::assertSame('Pro', $invoice->plan_name);
        self::assertSame(30, (int) $invoice->period_days);

        // ALICI ödeme anındaki fatura profilinden ANLIK olarak kopyalanır.
        self::assertSame('Kadıköy Kebap Gıda Ltd. Şti.', $invoice->buyer_legal_name);
        self::assertSame('1234567890', $invoice->buyer_tax_number);

        // KDV oranı yapılandırılmadı: hiçbir ayrım UYDURULMAZ.
        self::assertNull($invoice->vat_rate_basis_points);
        self::assertNull($invoice->net_minor);
        self::assertNull($invoice->vat_minor);

        // Değişmez kayıt: defter gibi `updated_at` taşımaz.
        self::assertFalse(
            DB::getSchemaBuilder()->hasColumn('invoices', 'updated_at'),
            'Bir faturanın güncellenebildiği yer, fatura değildir.',
        );
    }

    // --- INVOICE-REPLAY-01 ------------------------------------------------

    #[Test]
    public function a_repeated_settlement_of_the_same_payment_does_not_issue_a_second_document(): void
    {
        ['token' => $token, 'conversationId' => $conversationId] = $this->paidWorkspace('replay');

        self::assertSame(1, DB::table('invoices')->count());

        // Aynı ödeme için ikinci bir callback (geç gelen webhook ile aynı etki).
        $this->settleViaCallback($token, $conversationId);

        self::assertSame(1, DB::table('invoices')->count(), 'Tekrar gelen olay ikinci belge yaratmaz.');
    }

    // --- INVOICE-FAILURE-01 -----------------------------------------------

    #[Test]
    public function a_failed_payment_issues_no_document(): void
    {
        $this->configure();
        $owner = $this->verifiedUser('failed@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'invoice-failed');
        $this->completeProfile($workspaceId);
        $this->initiatedTransaction($workspaceId, 'tok-failed', 'conv-failed');

        $this->settleViaCallback('tok-failed', 'conv-failed', 'FAILURE');

        self::assertSame('failed', DB::table('payment_transactions')->value('state'));
        self::assertSame(0, DB::table('invoices')->count(), 'Tahsil edilmeyen paranın belgesi olmaz.');
    }

    // --- INVOICE-REFUND-01 ------------------------------------------------

    #[Test]
    public function a_refund_issues_a_counter_document_and_never_deletes_the_invoice(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00');
        ['workspaceId' => $workspaceId, 'transactionId' => $transactionId] = $this->paidWorkspace('refund');

        $superadmin = $this->verifiedUser('superadmin-invoice@example.test');
        DB::table('platform_role_assignments')->insert([
            'user_id' => $superadmin->id,
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gateway = $this->bindFake(self::SANDBOX_GATEWAY);
        $gateway->shouldReceive('refund')->once()->andReturn([
            'status' => 'success',
            'payment_id' => 'pay-01',
            'conversation_id' => 'conv-refund',
            'amount_minor' => self::AMOUNT,
            'currency' => 'TRY',
            'error_message' => null,
        ]);

        $this->actingAs($superadmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/admin/workspaces/{$workspaceId}/transactions/{$transactionId}/refund", [
                'reason' => 'Customer asked for a refund.',
            ])
            ->assertOk();

        self::assertSame(2, DB::table('invoices')->count(), 'Fatura silinmez; karşı belge eklenir.');

        $invoice = DB::table('invoices')->where('kind', 'invoice')->first();
        $creditNote = DB::table('invoices')->where('kind', 'credit_note')->first();

        self::assertNotNull($creditNote);
        self::assertSame('2026-000002', $creditNote->document_number, 'Karşı belge aynı seriden SIRADAKİ numarayı alır.');
        self::assertSame((int) $invoice->id, (int) $creditNote->counter_of_invoice_id);
        self::assertSame(self::AMOUNT, (int) $creditNote->amount_minor);
        self::assertSame($transactionId, (int) $creditNote->payment_transaction_id);
    }

    // --- INVOICE-SELLER-BLANK-01 ------------------------------------------

    #[Test]
    public function the_document_says_the_company_identity_is_not_yet_provided_instead_of_inventing_one(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId] = $this->paidWorkspace('seller');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertOk();

        $response->assertJsonPath('invoices.0.seller.complete', false);
        $response->assertJsonPath('invoices.0.seller.legal_name', null);

        /** @var list<string> $missing */
        $missing = $response->json('invoices.0.seller.missing');
        self::assertContains('legal_name', $missing);
        self::assertContains('tax_number', $missing);
        self::assertContains('address', $missing);

        $invoiceId = (int) $response->json('invoices.0.id');

        $pdf = $this->actingAs($owner)
            ->get("/api/workspaces/{$workspaceId}/invoices/{$invoiceId}/document.pdf")
            ->assertOk();

        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    // --- INVOICE-EARCHIVE-01 ----------------------------------------------

    #[Test]
    public function the_e_archive_route_reports_that_it_is_not_configured_and_no_record_claims_to_be_sent(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId] = $this->paidWorkspace('earchive');

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertOk()
            ->assertJsonPath('earchive.configured', false)
            ->assertJsonPath('earchive.state', 'not_configured')
            ->assertJsonPath('invoices.0.earchive_state', 'not_dispatched');
    }

    // --- INVOICE-VAT-01 ---------------------------------------------------

    #[Test]
    public function a_configured_vat_rate_is_snapshot_on_the_document_and_split_with_integer_arithmetic(): void
    {
        $this->configure();
        config()->set('billing.invoice.vat_rate_basis_points', 2000);

        $owner = $this->verifiedUser('vat@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'invoice-vat');
        $this->completeProfile($workspaceId);
        $this->initiatedTransaction($workspaceId, 'tok-vat', 'conv-vat');
        $this->settleViaCallback('tok-vat', 'conv-vat');

        $invoice = DB::table('invoices')->first();

        self::assertSame(2000, (int) $invoice->vat_rate_basis_points);
        self::assertSame(124917, (int) $invoice->net_minor);
        self::assertSame(24983, (int) $invoice->vat_minor);
        self::assertSame(
            self::AMOUNT,
            (int) $invoice->net_minor + (int) $invoice->vat_minor,
            'Ayrım toplanınca tahsil edilen tutarı vermeli.',
        );
    }

    // --- INVOICE-AUTHZ-01 -------------------------------------------------

    #[Test]
    public function the_invoice_surface_is_enumeration_safe(): void
    {
        ['owner' => $owner, 'workspaceId' => $workspaceId] = $this->paidWorkspace('authz');
        $outsider = $this->verifiedUser('outsider-invoice@example.test');
        $member = $this->verifiedUser('member-invoice@example.test');
        $this->addMember($workspaceId, $member, 'member');

        $this->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertStatus(401);

        $this->actingAs($outsider)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertStatus(404);

        $this->actingAs($member)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertStatus(404);

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertOk();

        // Başka bir çalışma alanının belgesi kendi adresinden bile okunamaz.
        $otherOwner = $this->verifiedUser('other-invoice@example.test');
        $otherWorkspaceId = $this->workspaceOwnedBy($otherOwner, 'invoice-other');
        $invoiceId = (int) DB::table('invoices')->value('id');

        $this->actingAs($otherOwner)
            ->get("/api/workspaces/{$otherWorkspaceId}/invoices/{$invoiceId}/document.pdf")
            ->assertStatus(404);
    }

    // --- INVOICE-NO-BUYER-01 ----------------------------------------------

    #[Test]
    public function a_payment_without_a_recorded_buyer_produces_no_document_and_says_so(): void
    {
        // Fatura profili YOK: alıcı uydurulamaz, belge kesilmez ve bu
        // eksiklik sessizce yutulmaz — sayılır ve söylenir.
        $this->configure();
        $owner = $this->verifiedUser('nobuyer@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'invoice-nobuyer');
        $this->initiatedTransaction($workspaceId, 'tok-nobuyer', 'conv-nobuyer');
        $this->settleViaCallback('tok-nobuyer', 'conv-nobuyer');

        self::assertSame('succeeded', DB::table('payment_transactions')->value('state'));
        self::assertSame(0, DB::table('invoices')->count());

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/invoices")
            ->assertOk()
            ->assertJsonPath('payments_without_document', 1);
    }

    // --- INVOICE-VAULT-UNTOUCHED-01 ---------------------------------------

    #[Test]
    public function issuing_a_document_never_reads_the_provider_vault(): void
    {
        // Fatura kesmek bir sağlayıcı çağrısı değildir: kasa boşken de çalışır.
        $this->app->make(PlatformCredentialAdminPort::class)->status(CredentialProvider::Iyzico);

        ['workspaceId' => $workspaceId] = $this->paidWorkspace('vault');

        self::assertSame(1, DB::table('invoices')->where('workspace_id', $workspaceId)->count());
    }
}
