<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOPLU İŞLEM ve YASAL SAKLAMA — kanonik kaynak
 * `docs/reference/panel-v3/MedyaModulu.dc.html` ("Toplu işlem" ve
 * "Yönetişim" bölümleri), plan `docs/109-PANEL-V3.md` §2.
 *
 * İki yeni gerçeklik, iki ayrı sebep:
 *
 * ── 1. Yasal saklama (`legal_hold_*`) ────────────────────────────────
 * Kaynağın kuru çalışması "Yasal saklama altında · silme ve taşıma
 * kilitli" diye bir atlama sebebi sayıyor. Bu sebep bir ETİKET değil bir
 * KİLİT olmalı: sütun olmasaydı ekranda bir cümle dururdu ve arkasında
 * hiçbir şey olmazdı. Sebep de saklanır — "kilitli" demek yetmez, sahip
 * altı ay sonra "hangi uyuşmazlık yüzünden?" diye sorar.
 *
 * Kilit `deleted_at` gibi bir yaşam döngüsü durumu DEĞİLDİR ve onunla
 * aynı sütuna sıkıştırılamaz: çöpteki bir dosyanın da yasal saklaması
 * olabilir ve o dosya süresi dolsa bile kalıcı silinmemelidir.
 *
 * ── 2. Toplu iş kaydı (`media_bulk_operations`) ──────────────────────
 * Kaynağın iki cümlesi bu tabloyu zorunlu kılar: "Bu kayıt silinemez ve
 * değiştirilemez" ve "Aynı işlem anahtarıyla iş iki kez çalıştırılamaz."
 *
 * Var olan `media_audits` tablosu bunu taşıyamaz, çünkü o tablo VARLIK
 * BAŞINA yazar (`media_asset_id` zorunlu). 1.800 dosyalık bir dönüştürme
 * oraya 1.800 satır yazar ve "kim ne yaptı" listesi tek bir işten
 * okunamaz hâle gelir. İki tablo iki soruyu cevaplar: `media_audits`
 * "bu fotoğrafa ne oldu", `media_bulk_operations` "bu iş neydi".
 *
 * `operation_key` çalışma alanı içinde TEKİLDİR: çift tıklama, yeniden
 * deneme ya da geri düğmesi bin dosyayı iki kez işlemez. Tekilliği
 * uygulama katmanında bir `select`le aramak yetmezdi — iki eşzamanlı
 * istek arasındaki yarışı yalnız veritabanı kapatır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('legal_hold_reason')->nullable()->after('lifecycle_status');
            $table->timestamp('legal_hold_at')->nullable()->after('legal_hold_reason');
            $table->unsignedBigInteger('legal_hold_by')->nullable()->after('legal_hold_at');
        });

        Schema::create('media_bulk_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('operation_key');
            $table->string('action');
            $table->string('scope');
            /*
                Dört sayı birden: sahibin okuduğu cümle "1.831 dosya
                dönüştürüldü" değil, "1.831 planlandı, 1.798 uygulandı, 31
                atlandı, 2 hata". Yalnız başarıyı saymak, atlananları
                görünmez kılardı.
            */
            $table->unsignedInteger('planned_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            // Aktör silinse bile kayıt kalır: failin bilinmediğini söylemek,
            // kaydı silmekten dürüsttür (`EloquentMediaAudit` ile aynı kural).
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['workspace_id', 'operation_key']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_bulk_operations');

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn(['legal_hold_reason', 'legal_hold_at', 'legal_hold_by']);
        });
    }
};
