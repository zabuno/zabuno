<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Davetin E-POSTASI çıktı mı? (`docs/110` P0-06)
 *
 * Davet satırı "bekliyor" diyordu ama bunun iki apayrı sebebi olabiliyordu:
 * e-posta ulaştı ve kişi henüz tıklamadı, ya da e-posta hiç çıkmadı. Sahip
 * ekranda ikisini de aynı görüyordu — ve ikisinin çözümü tamamen farklıdır.
 *
 * Sütunlar `contact_messages` ile AYNI çifttir (`docs/93`): ikinci bir desen
 * kurmuyoruz, çalışan deseni tekrar ediyoruz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('expires_at');
            $table->string('delivery_failure')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'delivery_failure']);
        });
    }
};
