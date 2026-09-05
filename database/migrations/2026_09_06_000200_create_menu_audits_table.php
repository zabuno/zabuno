<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menü denetim izi — FF-154.
 *
 * ASİMETRİYİ KAPATIR. Depoda MEDYA için tam bir iz vardı (`media_audits`,
 * "bu fotoğrafı kim sildi?") ama MENÜ için hiçbir şey yoktu. Oysa misafirin
 * ÖDEDİĞİ fiyatı değiştiren yüzey menüdür, dosya kütüphanesi değil; ve
 * ekipte artık dört rol var (sahip, yönetici, editör, mutfak), yani menüye
 * birden fazla kişi dokunuyor. *"Dün kebabın fiyatını kim değiştirdi?"*
 * bugün hiçbir yerden cevaplanamıyor.
 *
 * MEDYA DESENİ DEVAM EDER, BİR FARKLA. `media_audits` yalnız "kim, ne zaman,
 * hangi varlık, hangi eylem" tutar ve bu bir fotoğraf için yeter: dosya ya
 * vardır ya yoktur. Menüde eylem tek başına HİÇBİR ŞEY anlatmaz — "fiyat
 * değişti" cümlesi sahibin sorusunu kapatmaz, "380'den 420'ye çıktı"
 * kapatır. Bu yüzden bu tabloda `before_value`/`after_value` var; ikinci bir
 * desen kurmak için değil, aynı desenin menüye uyan hâli olduğu için.
 *
 * SATIR BİR KEZ YAZILIR (`updated_at` YOK): düzeltilebilen bir denetim izi
 * denetim izi değildir. Konu (`subject_id`) ve menü (`menu_id`) YABANCI
 * ANAHTAR DEĞİL, düz sayıdır — kaydın en değerli olduğu an, ürünün artık
 * menüde olmadığı andır ve bir `cascade` tam o kaydı silerdi. Adın kopyası
 * (`subject_label`) aynı sebeple saklanır: silinmiş bir satırın kimliği
 * "137 numaralı ürün" değil, "Adana Kebap"tır.
 *
 * KİRACI İZOLASYONU YAPISALDIR: `workspace_id` zorunlu bir yabancı
 * anahtardır ve her yazma yolu onu kaynak varlıktan doğrular. Bir kiracı
 * silindiğinde izi de gider — iz, kiracının kendi kaydıdır.
 *
 * SAKLANAN ŞEY MENÜ VERİSİDİR. Öncesi/sonrası alanlarına yalnız misafirin
 * zaten göreceği değerler yazılır (fiyat, ad, görünürlük, alerjen); kişisel
 * veri, oturum bilgisi, sağlayıcı anahtarı ya da başka bir sır BURAYA
 * YAZILMAZ. Fail yalnız `users` tablosuna bir kimlikle bağlanır, e-posta
 * kopyalanmaz (kullanıcı silinirse alan boşalır ve iz "failin bilinmediğini"
 * söyler — kaydı silmekten dürüsttür).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            /*
                Hangi menüde olduğu, konudan AYRI tutulur: bir menü satırının
                kimliğinden menüsüne ulaşmak, satır silindikten sonra artık
                mümkün değildir. Yabancı anahtar değil ve boş olabilir.
            */
            $table->unsignedBigInteger('menu_id')->nullable();

            // menu | category | menu_item — `MenuAuditSubject` enum'ının değeri.
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');

            /*
                Olay ANINDAKİ insan-okur ad. 255, çünkü kaynak sütunlar
                (`products.name`, `menu_categories.name`, `menus.name`) da
                255'tir; yazıcı yine de kırpar, böylece bileşik bir etiket
                PostgreSQL'de `value too long` ile isteği düşüremez.
            */
            $table->string('subject_label', 255)->nullable();

            // `MenuAuditAction` enum'ının değeri; en uzunu 23 karakter.
            $table->string('action', 32);

            /*
                Öncesi/sonrası METİN, sınırlı `string` değil: alerjen listesi
                gibi değerlerin uzunluğu önceden bilinmez ve kırpılmış bir
                "önceki alerjenler" satırı, kaydı yanlış bilgiye çevirirdi.
            */
            $table->text('before_value')->nullable();
            $table->text('after_value')->nullable();

            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            // Çalışma alanının zaman çizgisi (izin tek okuma deseni).
            $table->index(['workspace_id', 'created_at']);
            // Tek bir menünün geçmişi.
            $table->index(['menu_id', 'created_at']);
            // Tek bir ürünün geçmişi: "bu kebabın fiyatı kaç kez değişti?"
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_audits');
    }
};
