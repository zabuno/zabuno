<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onay defteri — FF-198 (`docs/107` Faz 1.2).
 *
 * ÖLÇÜLDÜ: kayıt ekranında onay kutusu yoktu ve onayı tutan tablo yoktu.
 * Bir hesap açılıyor, biz de o kişinin neyi kabul ettiğini hiçbir yerde
 * tutmuyorduk. Sözleşme onayı bir varsayım değil KAYITTIR: kim, hangi
 * belgenin hangi sürümünü, ne zaman, hangi adresten.
 *
 * SATIR BİR KEZ YAZILIR (`updated_at` yok): düzeltilebilen bir onay kaydı
 * kanıt değildir. Geri alma yeni bir satırdır (`granted = false`).
 *
 * KULLANICI SİLİNİRSE KAYIT KALIR (`nullOnDelete`): kaydın en değerli olduğu
 * an, hesabın artık olmadığı andır — "bu kişi şu tarihte şu sürümü kabul
 * etmişti" sorusu tam o gün sorulur. `menu_audits` ile aynı gerekçe. Kayıt
 * kişisel veri KOPYALAMAZ: e-posta ya da ad yoktur, yalnız kimlik, ağ
 * adresi ve tarayıcı kimliği.
 *
 * `workspace_id` YABANCI ANAHTAR DEĞİL: ödeme onayı bir çalışma alanına
 * bağlıdır ve o çalışma alanı silindiğinde onayın kanıtı silinmemeli.
 *
 * SAKLAMA SÜRESİ bir hukuk kararıdır ve bu göç onu vermez (`docs/124`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable();

            // registration | marketing | checkout — `ConsentRecorder::KIND_*`.
            $table->string('kind', 32);
            // Belge anahtarı ve o an yayında olan sürümü (`LegalDocument`).
            $table->string('document_key', 64);
            $table->string('document_version', 32);
            $table->boolean('granted');

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('recorded_at');

            // "Bu kişi neyi ne zaman kabul etti?"
            $table->index(['user_id', 'recorded_at']);
            // "Bu sürümü kaç kişi kabul etti?" — metin değiştiğinde sorulur.
            $table->index(['document_key', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
