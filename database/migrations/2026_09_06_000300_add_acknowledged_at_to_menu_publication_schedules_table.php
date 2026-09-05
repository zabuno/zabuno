<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÇIKMAYAN YAYINI SAHİP GÖRSÜN, SONRA KAPATABİLSİN.
 *
 * Zamanlanmış yayın çıkmadığında (zamanlayıcı durdu, süreç yayının
 * ortasında öldü, kayıt başarısız oldu) ürünün bunu SÖYLEMESİ gerekir.
 * Ama kapatılamayan bir uyarı birkaç gün içinde okunmayan bir süse döner,
 * ve okunmayan uyarı olmayan uyarıdır.
 *
 * `acknowledged_at` yalnız GÖRÜNÜRLÜĞÜ kapatır; kaydın `state` sütununa
 * dokunmaz. Başarısız bir planı `cancelled` yapıp uyarıdan kurtulmak daha
 * kolay olurdu, ama o zaman "o gece ne oldu, iptal mi ettim yoksa yayın mı
 * patladı" sorusunun cevabı silinirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_publication_schedules', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('menu_publication_schedules', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
