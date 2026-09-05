<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ZİYARETÇİ + ÜRÜN BAŞINA TEK **SAYILAN** OY — `docs/116` §4 (P4).
 *
 * ═══ P1'İN AÇIK BIRAKTIĞI SORU BURADA CEVAPLANIYOR ═══
 *
 * `2026_09_07_000100` göçü bu kısıtı bilerek koymadı ve sebebini yazdı:
 * *"misafir fikrini değiştirirse ne olur? Değişmez bir defterde cevap 'yeni
 * bir satır' olmak zorundadır ve o satır benzersizlik kısıtını ihlal
 * ederdi."*
 *
 * Cevap: kısıt SATIRLARIN TAMAMI üzerinde değil, **sayılan** satırlar
 * üzerinde kurulur. Misafir fikrini değiştirdiğinde eski satır
 * `excluded_at` ile işaretlenir ve kısmî indeksin kapsamından çıkar; yeni
 * satır sorunsuz girer. Böylece:
 *
 * - defter değişmez kalır (iki satır da yerinde),
 * - "tek oy" kuralı VERİTABANINDA doğru olur, yalnız denetleyicide değil,
 * - eşzamanlı iki isteğin ikisini birden saydırması imkânsızlaşır.
 *
 * Son madde bu göçün asıl işidir. Kural yalnız denetleyicide dursaydı, aynı
 * anda gelen iki istek "önce oku, sonra yaz" arasında birbirini görmez ve
 * tek ziyaretçi iki sayılan oy bırakırdı. Yarış koşulunu yorumla değil,
 * kısıtla kapatıyoruz.
 *
 * ═══ NEDEN KISMÎ İNDEKS, NEDEN LARAVEL'İN `unique()`'İ DEĞİL ═══
 *
 * Blueprint'in akıcı `unique()` çağrısı koşul taşıyamaz; koşulsuz bir
 * benzersizlik ise tam olarak P1'in reddettiği şeydir. `WHERE` yan tümceli
 * bir indeks hem PostgreSQL'de hem SQLite'ta AYNI sözdizimiyle çalışır —
 * bu deponun iki motoru da bunu destekler. Desteklemeyen bir motora
 * geçilirse bu göç GÜRÜLTÜYLE patlar, ki doğrusu budur: kısıt sessizce
 * kaybolursa "tek oy" bir cümleden ibaret kalır.
 *
 * `visitor_key IS NOT NULL` koşulu ikinci bir gerçeği taşır: dış kaynaktan
 * gelen sinyalin ziyaretçisi YOKTUR (Google'daki bir yorumcunun bizde
 * anahtarı olamaz) ve `NULL` anahtarlı satırlar birbirini kilitlememelidir.
 */
return new class extends Migration
{
    private const INDEX = 'rating_signals_one_counted_vote_unique';

    public function up(): void
    {
        if (! Schema::hasTable('rating_signals')) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX.' ON rating_signals '
            .'(workspace_id, subject_type, subject_id, visitor_key) '
            .'WHERE excluded_at IS NULL AND visitor_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
