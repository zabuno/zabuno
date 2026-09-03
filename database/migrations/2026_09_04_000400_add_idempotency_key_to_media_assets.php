<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yükleme idempotency anahtarı — `docs/49` Faz 2 madde 1, `docs/98` FF-68.
 *
 * Telefonda bağlantı koparsa istemci aynı dosyayı yeniden gönderir. Anahtar
 * olmadan sunucu bunu İKİNCİ bir görsel sanır: kütüphanede aynı fotoğraf iki
 * kez, kota iki kez, sahibin kafası karışık. Anahtar istemcide üretilir,
 * yeniden denemede AYNI kalır; sunucu aynı anahtarı görünce var olanı döner.
 *
 * Tenant başına tekil: iki farklı restoranın aynı rastgele anahtarı üretmesi
 * imkânsıza yakın ama olsa bile birbirinin dosyasını görmemeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('slot');
            $table->unique(['workspace_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
