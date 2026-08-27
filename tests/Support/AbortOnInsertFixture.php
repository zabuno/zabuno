<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Kontrollü kalıcılık hatası üreten tetikleyici — motordan bağımsız.
 *
 * Üç test bir işlemin ortasında gerçek bir INSERT'i başarısız kılıp geri
 * sarmanın çalıştığını kanıtlıyor. Bu, üretim koduna test kancası koymadan
 * atomikliği ölçmenin dürüst yolu: hata, uygulamanın kendi yazma yolunda
 * doğar.
 *
 * Sorun, tetikleyicilerin SQLite sözdizimiyle yazılmış olmasıydı
 * (`BEGIN SELECT RAISE(ABORT, …); END`). PostgreSQL'de bu sözdizimi yok;
 * orada tetikleyici bir fonksiyon çağırır. Testler bu yüzden yalnız
 * SQLite'ta koşabiliyordu — yani atomiklik kanıtı, ürünün gerçekte
 * çalışacağı motorda hiç üretilmemişti.
 *
 * Burası o farkı tek yerde kapatır. Testler ne istediklerini söyler
 * ("şu tabloya INSERT'i reddet"), nasıl söyleneceğini bu sınıf bilir.
 */
final class AbortOnInsertFixture
{
    /**
     * @param  string|null  $condition  SQL koşulu; yalnız sağlandığında reddeder.
     *                                  `null` ise her INSERT reddedilir.
     */
    public static function install(
        string $name,
        string $table,
        string $message,
        ?string $condition = null,
    ): void {
        if (self::isPostgres()) {
            self::installPostgres($name, $table, $message, $condition);

            return;
        }

        self::installSqlite($name, $table, $message, $condition);
    }

    public static function remove(string $name, string $table): void
    {
        if (self::isPostgres()) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name} ON {$table}");
            DB::unprepared("DROP FUNCTION IF EXISTS {$name}_fn()");

            return;
        }

        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
    }

    private static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private static function installSqlite(
        string $name,
        string $table,
        string $message,
        ?string $condition,
    ): void {
        $when = $condition === null ? '' : "WHEN ({$condition})";

        DB::unprepared(<<<SQL
            CREATE TRIGGER {$name}
            BEFORE INSERT ON {$table}
            {$when}
            BEGIN
                SELECT RAISE(ABORT, '{$message}');
            END;
            SQL);
    }

    private static function installPostgres(
        string $name,
        string $table,
        string $message,
        ?string $condition,
    ): void {
        // PostgreSQL'de koşul tetikleyici gövdesine değil `WHEN` yan
        // tümcesine yazılamaz (alt sorgu içeremez), bu yüzden koşul
        // fonksiyonun içinde değerlendirilir.
        $guardOpen = $condition === null ? '' : "IF ({$condition}) THEN";
        $guardClose = $condition === null ? '' : 'END IF;';

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$name}_fn() RETURNS trigger AS \$\$
            BEGIN
                {$guardOpen}
                    RAISE EXCEPTION '{$message}';
                {$guardClose}
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER {$name}
            BEFORE INSERT ON {$table}
            FOR EACH ROW EXECUTE FUNCTION {$name}_fn();
            SQL);
    }
}
