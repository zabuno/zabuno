<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FATURA NUMARASI, GERÇEK EŞZAMANLILIK — yalnız PostgreSQL (docs/130 §K2).
 *
 * Kardeş testler (`InvoiceNumberingRaceTest`) sıranın boşluksuz olduğunu ve
 * veritabanının aynı numarayı iki kez kabul etmediğini iki motorda da
 * ölçer. Ölçemedikleri tek şey ŞUDUR: iki istek AYNI ANDA numara istediğinde
 * ne olur.
 *
 * O soruyu sormak ikinci bir bağlantı ister; SQLite'ın bellek
 * veritabanında ikinci bağlantı aynı veritabanı bile değildir. Bu yüzden
 * burada SQLite'ta sonuç "geçti" değil BİLİNMİYOR'dur ve öyle raporlanır.
 *
 * `DatabaseMigrations`, `RefreshDatabase` DEĞİL: ikincisi testi açık bir
 * işlemin içinde koşturur ve o işlemin yazdığını ikinci bağlantı göremez —
 * yani ölçmek istediğimiz yarış hiç kurulamazdı.
 */
final class PostgresInvoiceNumberingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const RIVAL = 'pgsql_invoice_rival';

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            self::markTestSkipped(
                'Gerçek eşzamanlılık ikinci bir bağlantı ister; bu makinede PostgreSQL yok, '
                .'sonuç BİLİNMİYOR ("geçti" değil). CI, DB_CONNECTION=pgsql ayağında ölçer.'
            );
        }

        parent::setUp();
    }

    #[Test]
    public function two_concurrent_allocations_are_serialised_by_the_database(): void
    {
        DB::table('invoice_number_sequences')->insert([
            'series' => '2026',
            'next_number' => 0,
            'created_at' => now(),
        ]);

        config()->set('database.connections.'.self::RIVAL, config('database.connections.pgsql'));
        $rival = DB::connection(self::RIVAL);
        // Rakip sonsuza kadar beklemesin: kilidi 400 ms içinde alamazsa
        // hata döner ve BEKLEDİĞİNİ böylece ölçebiliriz.
        $rival->statement("SET lock_timeout = '400ms'");

        DB::beginTransaction();

        try {
            // A tahsis etti, henüz commit etmedi.
            DB::table('invoice_number_sequences')->where('series', '2026')->update([
                'next_number' => DB::raw('next_number + 1'),
            ]);

            $blocked = false;

            try {
                $rival->table('invoice_number_sequences')->where('series', '2026')->update([
                    'next_number' => DB::raw('next_number + 1'),
                ]);
            } catch (QueryException) {
                $blocked = true;
            }

            self::assertTrue(
                $blocked,
                'İkinci tahsis BEKLEMELİ. Beklemeseydi iki istek aynı sayıyı okur ve '
                .'iki ayrı satış tek belge numarasına düşerdi.',
            );
        } finally {
            DB::rollBack();
            $rival->disconnect();
            DB::purge(self::RIVAL);
        }

        // A geri sardı: kimse numara tüketmedi, seride boşluk yok.
        self::assertSame(
            0,
            (int) DB::table('invoice_number_sequences')->where('series', '2026')->value('next_number'),
        );
    }
}
