<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\Exception\SupportReferenceExhaustedException;
use App\Application\Support\Port\SupportReferenceGeneratorPort;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\SupportChannel;
use App\Domain\Support\SupportReference;
use App\Infrastructure\Support\Persistence\EloquentSupportRequestRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * FF-201 RED — referans BENZERSİZDİR ve çarpışma sessizce çözülür.
 *
 * Referans rastgele üretilir; iki talebin aynı numarayı alması olasılığı
 * düşük ama sıfır değildir. Tekil indeks bunu veritabanında keser; kod ise
 * kesildiğinde YENİ bir numara dener — müşteriye "tekrar deneyin" demez.
 *
 * SQLite ve MySQL tekil ihlalini `23000` ile, PostgreSQL `23505` ile
 * bildirir. Yalnız birini tanıyan bir kod, öteki veritabanında çarpışmayı
 * beklenmedik bir 500'e çevirirdi.
 *
 * Requirement IDs: SUPPORT-REFERENCE-UNIQUE-RETRY-01,
 * SUPPORT-REFERENCE-UNIQUE-CODES-01, SUPPORT-REFERENCE-EXHAUSTED-01,
 * SUPPORT-REFERENCE-ALPHABET-01.
 */
final class SupportReferenceTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $sequence */
    private function bindSequence(array $sequence): void
    {
        $this->app->instance(SupportReferenceGeneratorPort::class, new class($sequence) implements SupportReferenceGeneratorPort
        {
            /** @param  list<string>  $sequence */
            public function __construct(private array $sequence) {}

            public function generate(): string
            {
                $next = array_shift($this->sequence);

                if ($next === null) {
                    self::fail('Üreteç beklenenden fazla çağrıldı.');
                }

                return $next;
            }
        });
    }

    private function newRequest(): NewSupportRequest
    {
        return new NewSupportRequest(
            name: 'Hüseyin',
            email: 'huseyin@example.com',
            subject: 'Menüm görünmüyor',
            message: 'Karekodu okutunca boş sayfa.',
            channel: SupportChannel::PublicContact,
            locale: 'tr',
            workspaceId: null,
            userId: null,
        );
    }

    // --- SUPPORT-REFERENCE-ALPHABET-01 ------------------------------------

    public function test_the_default_generator_only_uses_the_unambiguous_alphabet(): void
    {
        $generator = $this->app->make(SupportReferenceGeneratorPort::class);
        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $reference = $generator->generate();

            self::assertMatchesRegularExpression(SupportReference::PATTERN, $reference);
            self::assertDoesNotMatchRegularExpression('/[01OIL]/', substr($reference, 3), 'Karışan karakterler alfabede olmamalı.');
            $seen[$reference] = true;
        }

        // 200 çekilişte tekrar, üretecin bozuk olduğuna işarettir.
        self::assertGreaterThan(190, count($seen));
    }

    // --- SUPPORT-REFERENCE-UNIQUE-RETRY-01 --------------------------------

    public function test_a_colliding_reference_is_replaced_without_failing_the_request(): void
    {
        DB::table('support_requests')->insert([
            'reference' => 'ZB-AAAAA',
            'name' => 'Önceki',
            'email' => 'onceki@example.com',
            'subject' => 'Eski talep',
            'message' => 'Eski.',
            'channel' => 'public_contact',
            'status' => 'received',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bindSequence(['ZB-AAAAA', 'ZB-BBBBB']);

        $received = $this->app->make(SupportRequestRepositoryPort::class)->receive($this->newRequest());

        self::assertSame('ZB-BBBBB', $received->reference, 'SUPPORT-REFERENCE-UNIQUE-RETRY-01: çarpışan numara yenisiyle değiştirilmeli.');
        self::assertSame(2, DB::table('support_requests')->count());
    }

    // --- SUPPORT-REFERENCE-EXHAUSTED-01 -----------------------------------

    public function test_repeated_collisions_fail_loudly_rather_than_looping_forever(): void
    {
        DB::table('support_requests')->insert([
            'reference' => 'ZB-AAAAA',
            'name' => 'Önceki',
            'email' => 'onceki@example.com',
            'subject' => 'Eski talep',
            'message' => 'Eski.',
            'channel' => 'public_contact',
            'status' => 'received',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bindSequence(array_fill(0, EloquentSupportRequestRepository::MAX_REFERENCE_ATTEMPTS, 'ZB-AAAAA'));

        $this->expectException(SupportReferenceExhaustedException::class);

        $this->app->make(SupportRequestRepositoryPort::class)->receive($this->newRequest());
    }

    // --- SUPPORT-REFERENCE-UNIQUE-CODES-01 --------------------------------

    public function test_both_sqlstate_codes_are_recognised_as_a_unique_violation(): void
    {
        $sqlite = new QueryException('sqlite', 'insert into support_requests', [], new PDOException('UNIQUE constraint failed: support_requests.reference', 23000));
        $postgres = new QueryException('pgsql', 'insert into support_requests', [], new PDOException('duplicate key value violates unique constraint "support_requests_reference_unique"', 0));

        // PDO PostgreSQL SQLSTATE'i dize olarak taşır; sayı değil.
        $reflection = new \ReflectionProperty(\Exception::class, 'code');
        $reflection->setValue($postgres, '23505');

        $other = new QueryException('sqlite', 'insert into support_requests', [], new PDOException('no such table', 0));

        self::assertTrue(EloquentSupportRequestRepository::isUniqueViolation($sqlite));
        self::assertTrue(EloquentSupportRequestRepository::isUniqueViolation($postgres));
        self::assertFalse(EloquentSupportRequestRepository::isUniqueViolation($other));
    }
}
