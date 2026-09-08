<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiracı olarak bakma oturumları — `docs/122` Y7, `docs/133`.
 *
 * Bu tablo bir KAYIT DEFTERİDİR, bir önbellek değil. Oturumun kendisi
 * buradadır: sunucuda, her istekte yeniden okunan bir satır. Çerezde ya da
 * istemcide tutulsaydı, "hâlâ bakıyor mu?" sorusunun cevabı tarayıcının
 * elinde olurdu ve süre dolduğunda kimse bunu zorlayamazdı.
 *
 * SATIR SİLİNMEZ VE GÜNCELLENMEZ (bitiş damgası dışında). Oturum bittiğinde
 * `ended_at` yazılır; satır durur. Düzeltilebilen bir denetim izi denetim
 * izi değildir (`ListPlatformAuditLogController` ile aynı cümle).
 *
 * ÇALIŞMA ALANI SİLİNİRSE KAYIT DE GİDER (`cascadeOnDelete`): destek
 * talebinin aksine bu satır kiracının KENDİ görebileceği bir kayıttır ve
 * kiracı yoksa gösterilecek kimse de yoktur. Fail (`actor_user_id`) ise
 * silinse bile kayıt durur: failin bilinmediğini söylemek, kaydı gizlemekten
 * dürüsttür.
 *
 * SEBEP NULLABLE DEĞİLDİR ve olmayacak. Sebepsiz bir bakış, bir gün kimsenin
 * hatırlamadığı bir erişimdir (`docs/122` §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Sebep KİRACININ okuyacağı bir cümledir; iç kısaltma değil.
            $table->string('reason', 500);
            $table->timestamp('started_at');
            /*
                BİTİŞ AÇILIRKEN YAZILIR, uzatılmaz. Yenileme ucu yoktur:
                yeniden bakmak yeni bir satır ve yeni bir sebep demektir ve
                kiracının panelinde iki satır görünür.
            */
            $table->timestamp('expires_at');
            // Erken kapanış. Boşsa oturum ya hâlâ açıktır ya da süresi
            // dolmuştur; ikisinin farkı `expires_at` ile okunur.
            $table->timestamp('ended_at')->nullable();
            /*
                SAHİBE HABER VERME (`docs/93` ilkesi): saklamak göndermekten
                önce gelir ve çıkmayan bir e-postaya damga basılmaz. Taşıyıcı
                yoksa ikisi de boş kalır — "gönderildi" demek yalan olurdu.
            */
            $table->timestamp('notified_at')->nullable();
            $table->string('notification_failure')->nullable();
            $table->timestamps();

            // Her istekte sorulan soru: "bu kullanıcının açık oturumu var mı?"
            $table->index(['actor_user_id', 'ended_at']);
            // Kiracının kendi paneli: "hesabıma kim baktı?", en yeni üstte.
            $table->index(['workspace_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_sessions');
    }
};
