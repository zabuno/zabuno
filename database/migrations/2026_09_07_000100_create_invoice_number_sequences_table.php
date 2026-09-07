<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belge numarası sayacı — SIRALI VE BOŞLUKSUZ (docs/107 Faz 1.4, docs/130).
 *
 * Neden bir tablo, neden bir veritabanı dizisi (sequence) değil: PostgreSQL
 * dizisi geri sarılan bir işlemde tükettiği sayıyı GERİ VERMEZ. Yazılamayan
 * bir belge, mali bir seride açıklanamayan bir boşluk bırakırdı — "burada
 * kesilmiş ama kaybolmuş bir fatura mı var?" sorusu cevapsız kalırdı.
 *
 * Bu satır ise belgeyle AYNI işlemin içinde artırılır: belge yazılamazsa
 * sayaç da geri sarar. Artırım uygulamada değil veritabanında yapılır
 * (`UPDATE … SET next_number = next_number + 1`), çünkü "önce oku, sonra
 * yaz" iki eşzamanlı tahsilatın aynı numarayı almasına açık bir kapıdır.
 * O `UPDATE` satır kilidini alır ve ikinci isteği kendi sırası gelene dek
 * bekletir.
 *
 * Seri = takvim yılı. Numara her yıl 1'den başlar; bir seri içinde boşluk
 * yoktur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->string('series', 16)->primary();
            // 0'dan başlar; ilk tahsis 1'i verir.
            $table->unsignedInteger('next_number')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
