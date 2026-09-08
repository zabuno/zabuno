<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veri hakları defteri — FF-226 (`docs/107` Faz 3.3, `docs/138`).
 *
 * ÖLÇÜLDÜ (2026-09-08): kiracının kendi verisini dışa aktarmasının ya da
 * sildirmesinin ÜRÜN İÇİNDE hiçbir yolu yoktu. Gizlilik politikası
 * ("When you ask us to delete your account we remove the data we are not
 * legally required to keep") bir söz veriyordu ve o sözün karşılığı yalnız
 * bir iletişim formuydu.
 *
 * TEK TABLO, İKİ TÜR. Dışa aktarma ve silme AYNI defterde durur çünkü
 * ikisi de aynı sorunun iki yüzü ("verim nerede, ne olur?") ve ikisi de
 * aynı denetim izine düşer. İki tablo olsaydı, zaman çizgisi iki sorgudan
 * birleştirilirdi ve bir gün biri unutulurdu.
 *
 * SATIR ASLA SİLİNMEZ — silme talebi yürütüldükten sonra bile. Kaydın en
 * değerli olduğu an, verinin artık olmadığı andır: "kim istedi, ne zaman,
 * kapsamı neydi, ne zaman tamamlandı" sorusu tam o gün sorulur. Bu yüzden
 * `workspace_id` yabancı anahtar DEĞİLDİR ve talep eden kullanıcı silinirse
 * satır `nullOnDelete` ile yaşamaya devam eder — `consent_records` ile aynı
 * gerekçe.
 *
 * SÜRE BURADA YAZILI DEĞİL. Silmenin geri alınabilir penceresi
 * `config/data-rights.php` içinden gelir; göç yalnız "ne zaman yürüyecek"
 * damgasını (`scheduled_for`) tutar. Bir sayıyı iki yere yazmak, iki gün
 * sonra ayrışmalarının garantisidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_data_requests', function (Blueprint $table): void {
            $table->id();

            // Yabancı anahtar DEĞİL: çalışma alanı bir gün gerçekten
            // silinirse bu kayıt kanıt olarak kalmalı.
            $table->unsignedBigInteger('workspace_id');

            // export | erasure — `DataRequestKind`.
            $table->string('kind', 16);
            // `DataRequestState`: queued/running/ready/failed/expired,
            // scheduled/cancelled/completed.
            $table->string('state', 16);

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');

            /*
                KAPSAM İSTEK ANINDA DONDURULUR. Bugün dışa aktarılan ya da
                silinen bölümlerin listesi koddan gelir; ama kod yarın
                değişir ve o gün "geçen ay ne aktardık?" sorusunun cevabı
                bu sütundur. Ölçülen bir olgu, türetilebilir bir tahmin
                değil.
            */
            $table->json('scope');

            // Dışa aktarma çıktısı. Yol İÇERİĞİ değil KONUMU söyler; dosya
            // özel diskte durur ve yalnız imzalı bir adresle iner.
            $table->string('artifact_path', 255)->nullable();
            $table->unsignedBigInteger('artifact_bytes')->nullable();
            $table->string('artifact_checksum_sha256', 64)->nullable();
            $table->timestamp('available_until')->nullable();

            // Silmenin geri alınabilir penceresinin bitişi.
            $table->timestamp('scheduled_for')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kırpılmış sebep; yığın izi burada okunmaz.
            $table->string('failure_reason', 190)->nullable();

            /*
                SİLİNEN SATIR SAYILARI — "her şey silindi" demeden önce
                gerçekten silindiğini ÖLÇMEK için. Tablo adı → silinen satır
                sayısı. Sayı olmadan verilen bir "tamamlandı" damgası,
                kontrol edilemeyen bir iddiadır.
            */
            $table->json('deleted_counts')->nullable();

            // `docs/93` deseni: gönderim yapılandırılmamışsa damga atılmaz,
            // kayıt yine ekranda durur.
            $table->timestamp('notified_at')->nullable();
            $table->string('notification_failure', 190)->nullable();

            $table->timestamps();

            // "Bu çalışma alanında ne istendi?" — denetim izinin sorgusu.
            $table->index(['workspace_id', 'requested_at']);
            // "Bugün yürümesi gereken silme var mı?" — zamanlayıcının sorgusu.
            $table->index(['kind', 'state', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_data_requests');
    }
};
