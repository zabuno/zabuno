<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABONELİĞİN EKSİK YARISI — çıkış, düşürme ve geri dönüş (`docs/107` Faz 1.3,
 * `docs/134`).
 *
 * Bugüne kadar `subscriptions` yalnız "hangi plan, ne zamana kadar" diyordu.
 * Bu göç iki NİYET daha kaydeder; ikisi de bir TARİH değil bir KARARDIR ve
 * hiçbiri `state` sütununa yazılmaz.
 *
 * 1. `cancelled_at` / `cancelled_by_user_id` — "sahip çıkmak istedi".
 *
 *    `state` sütununa `cancelled` YAZILMADI ve bu bilinçli:
 *    `DatabaseEntitlementRepository` yalnız `active`/`trialing` durumlarına
 *    yetki verir, yani durumu değiştirmek iptal eden sahibin yeteneklerini O
 *    SANİYE kapatırdı. Oysa iptalin sözü tam tersidir: ödenmiş dönem sonuna
 *    kadar hizmet sürer, yalnız yenileme durur (`RefundPolicy`, "When
 *    cancellation takes effect"). İptal bir DURUM değil, bir tarihtir.
 *
 *    NULL'A DÖNEBİLİR ve dönmelidir: dönem bitmeden fikir değiştiren sahip
 *    yeniden ödeme yapmak zorunda kalmaz. Kim/ne zaman sorusunun kalıcı
 *    cevabı bu satırda değil `platform_audits` kaydındadır; buradaki iki
 *    sütun yalnız BUGÜNKÜ niyeti taşır.
 *
 * 2. `scheduled_plan_id` / `scheduled_by_user_id` / `scheduled_at` —
 *    "sahip bir sonraki dönemde şu plana inmek istedi".
 *
 *    Düşürme ANINDA uygulanmaz ve fark iade edilmez: yayınlanmış İptal ve
 *    İade Politikası "başlamış bir dönemin ücreti iade edilmez" der, dolayısıyla
 *    ödenmiş dönemin ortasında hakkı azaltmak sahibin ödediği şeyi sessizce
 *    küçültmek olurdu. Karar `ends_at`'ten İTİBAREN geçerlidir.
 *
 *    ZAMANLAYICI YOK ve bilerek: hangi planın geçerli olduğu okuma anında
 *    tarihten TÜRETİLİR. Aynı karar bu depoda zaten var —
 *    `DatabaseEntitlementRepository` "durum alanı geç güncellenmiş olabilir
 *    ve tarih daha güvenilir bir kanıttır" der. Dakikada bir koşan bir komuta
 *    bağlansaydı, komut koşmadığı gün abonelik yanlış planı gösterirdi.
 *
 * İkisi BİRLİKTE bulunmaz: iptal eden sahip zaten çıkıyor, bir sonraki dönem
 * için plan seçmiyor. İptal, zamanlanmış düşürmeyi siler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // `plans` satırı SİLİNMEZ (katalog sürümlenir, pasifleşir), bu
            // yüzden burada silme davranışı tanımlanmadı: zamanlanmış plan
            // gerçekten yok olursa okuma tarafı düşürmeyi hiç uygulamaz.
            $table->foreignId('scheduled_plan_id')->nullable()->constrained('plans');
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_by_user_id');
            $table->dropConstrainedForeignId('scheduled_plan_id');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['scheduled_at', 'cancelled_at']);
        });
    }
};
