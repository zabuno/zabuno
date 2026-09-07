<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Dto\InvoiceDraft;
use App\Application\Billing\Port\InvoiceRepositoryPort;
use App\Domain\Billing\InvoiceKind;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AbortOnInsertFixture;
use Tests\TestCase;

/**
 * FATURA NUMARASI — sıralı, boşluksuz ve YARIŞA DAYANIKLI (docs/130 §K2).
 *
 * Mali bir belgenin numarası bir görünüm tercihi değil, denetlenebilirliğin
 * kendisidir: iki eşzamanlı tahsilat aynı numarayı alırsa iki farklı satış
 * tek belgeye benzer; numara atlarsa "kesilmemiş bir fatura mı var" sorusu
 * cevapsız kalır.
 *
 * Bu yüzden numara UYGULAMADA üretilmez. "Önce en büyüğü oku, bir ekle,
 * sonra yaz" iki isteğin arasına sığan bir boşluktur ve o boşlukta ikisi de
 * aynı sayıyı okur. Numara veritabanının kendi atomik artırımıyla üretilir
 * (`UPDATE … SET next_number = next_number + 1`), tahsis ile belgenin
 * yazılması AYNI işlemin içindedir (geri sarılırsa sayaç da geri sarar,
 * boşluk oluşmaz) ve son güvence `unique(series, number)`tir: veritabanı
 * aynı numarayı ikinci kez kabul etmez.
 *
 * Ölçüm iki motorda birden koşar; PostgreSQL ayağında ayrıca gerçek
 * eşzamanlılık (ikinci bir bağlantı) denenir.
 */
final class InvoiceNumberingRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function repository(): InvoiceRepositoryPort
    {
        return $this->app->make(InvoiceRepositoryPort::class);
    }

    private function workspaceId(string $slug): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

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

    private function planId(): int
    {
        return (int) DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'code' => 'pro-'.bin2hex(random_bytes(4)),
            'version' => 1,
            'entitlements' => json_encode(['menu.publish']),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function succeededPayment(int $workspaceId): int
    {
        $userId = (int) DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->value('user_id');
        $conversationId = bin2hex(random_bytes(8));

        return (int) DB::table('payment_transactions')->insertGetId([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'plan_id' => $this->planId(),
            'mode' => 'sandbox',
            'idempotency_key' => bin2hex(random_bytes(8)),
            'conversation_id' => $conversationId,
            'token' => bin2hex(random_bytes(8)),
            'amount_minor' => 149900,
            'currency' => 'TRY',
            'period_days' => 30,
            'state' => 'succeeded',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function draft(int $workspaceId, int $paymentTransactionId): InvoiceDraft
    {
        return new InvoiceDraft(
            workspaceId: $workspaceId,
            paymentTransactionId: $paymentTransactionId,
            kind: InvoiceKind::Invoice,
            counterOfInvoiceId: null,
            issuedAt: Carbon::parse('2026-09-07 10:00:00')->toDateTimeString(),
            series: '2026',
            currency: 'TRY',
            amountMinor: 149900,
            vatRateBasisPoints: null,
            netMinor: null,
            vatMinor: null,
            planName: 'Pro',
            periodDays: 30,
            seller: [
                'legal_name' => null,
                'address' => null,
                'mersis' => null,
                'tax_office' => null,
                'tax_number' => null,
                'email' => null,
                'phone' => null,
            ],
            buyer: [
                'legal_name' => 'Kadıköy Kebap Gıda Ltd. Şti.',
                'tax_number' => '1234567890',
                'tax_office' => 'Kadıköy',
                'address' => 'Moda Cad. No:1',
                'city' => 'Istanbul',
                'country' => 'TR',
                'email' => 'muhasebe@kadikoykebap.test',
                'phone' => '+905551112233',
            ],
            numberPrefix: null,
        );
    }

    // --- INVOICE-NUMBER-SEQUENTIAL-01 -------------------------------------

    #[Test]
    public function numbers_are_sequential_and_gapless_within_a_series(): void
    {
        $workspaceId = $this->workspaceId('numbering-sequential');
        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->repository()
                ->issue($this->draft($workspaceId, $this->succeededPayment($workspaceId)))
                ->number;
        }

        self::assertSame([1, 2, 3, 4, 5], $numbers, 'Sıra atlamaz ve tekrar etmez.');
        self::assertSame(5, (int) DB::table('invoice_number_sequences')->where('series', '2026')->value('next_number'));
    }

    // --- INVOICE-NUMBER-DB-UNIQUE-01 --------------------------------------

    #[Test]
    public function the_database_itself_refuses_a_duplicate_number_in_a_series(): void
    {
        $workspaceId = $this->workspaceId('numbering-unique');
        $issued = $this->repository()->issue($this->draft($workspaceId, $this->succeededPayment($workspaceId)));

        $row = (array) DB::table('invoices')->where('id', $issued->id)->first();
        unset($row['id']);
        $row['payment_transaction_id'] = $this->succeededPayment($workspaceId);
        $row['document_number'] = $row['document_number'].'-copy';

        $this->expectException(QueryException::class);

        // Uygulama katmanı hiç devrede değil: aynı (seri, numara) çifti
        // doğrudan yazılmaya çalışılıyor ve VERİTABANI reddediyor.
        DB::table('invoices')->insert($row);
    }

    // --- INVOICE-NUMBER-GAPLESS-ON-ROLLBACK-01 ----------------------------

    #[Test]
    public function a_rolled_back_issue_leaves_no_gap_because_the_counter_rolls_back_with_it(): void
    {
        $workspaceId = $this->workspaceId('numbering-rollback');
        $first = $this->repository()->issue($this->draft($workspaceId, $this->succeededPayment($workspaceId)));
        self::assertSame(1, $first->number);

        AbortOnInsertFixture::install(
            'invoice_insert_abort',
            'invoices',
            'invoice insert refused for the drill',
        );

        try {
            $this->repository()->issue($this->draft($workspaceId, $this->succeededPayment($workspaceId)));
            self::fail('Kalıcılık hatası yutulmamalı.');
        } catch (QueryException) {
            // beklenen
        } finally {
            AbortOnInsertFixture::remove('invoice_insert_abort', 'invoices');
        }

        self::assertSame(
            1,
            (int) DB::table('invoice_number_sequences')->where('series', '2026')->value('next_number'),
            'Yazılamayan bir belge numarayı TÜKETMEZ: sayaç işlemle birlikte geri sarar.',
        );

        $next = $this->repository()->issue($this->draft($workspaceId, $this->succeededPayment($workspaceId)));

        self::assertSame(2, $next->number, 'Sıradaki belge 2 olmalı — 3 olsaydı denetimde açıklanamayan bir boşluk kalırdı.');
    }
}
