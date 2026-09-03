<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İNSAN TANIKLIĞI kanıtları — `docs/98` FF-63.
 *
 * Readiness listesindeki üç madde bir test koşturularak üretilemez: fiziksel
 * bir telefonla basılı QR'ı taramak, RPO/RTO'ya KARAR vermek, üçüncü taraf
 * bir denetim raporuna atıf vermek. Bunlar bir insanın "yaptım/karar verdim/
 * işte rapor" demesiyle var olur — ve o cümlenin kim tarafından, ne zaman
 * söylendiği kanıtın kendisidir.
 *
 * Otomatik kanıtlardan (tenant izolasyonu, yedek tatbikatı) AYRI tablo:
 * ikisi aynı şey değildir ve aynı tabloya koymak "makine doğruladı" ile
 * "insan söyledi"yi ayırt edilemez kılardı. Ekran ikisini farklı etiketler.
 *
 * Append-only: bir tanıklık düzeltilmez, yenisi eklenir; ekran en yenisini
 * gösterir, eskiler iz olarak kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_attestations', function (Blueprint $table): void {
            $table->id();
            // qr-physical-scan | rpo-rto-decision | owasp-asvs-audit
            $table->string('key');
            // passed | failed | decided | recorded
            $table->string('status');
            // Tanığın kendi cümlesi: ne yapıldı, hangi cihaz, hangi karar.
            $table->text('summary');
            // Rapor/belge/ekran görüntüsü adresi ya da depo içi yol. Opsiyonel.
            $table->string('reference')->nullable();
            // Yapılandırılmış ayrıntı (ör. rpo_hours, rto_hours, device).
            $table->json('payload')->nullable();
            $table->foreignId('attested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('attested_at');
            // Kanonik alanların özeti — satır sonradan elle değiştirilirse
            // uç nokta 500 verir, sessizce "geçti" demez (diğer kanıt
            // tablolarıyla aynı disiplin).
            $table->string('integrity_sha256', 64);
            $table->timestamps();

            $table->index(['key', 'attested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_attestations');
    }
};
