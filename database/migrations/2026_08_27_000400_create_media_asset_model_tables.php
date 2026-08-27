<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medya veri modelinin omurgası — `docs/49` Faz 1.
 *
 * Bugüne kadar tek bir düz tablo vardı: `media_assets(disk_path, status)`.
 * Bunun somut sonucu şuydu:
 *
 *   - Bir fotoğrafı düzenlemek ASLI bozardı; eski hâle dönüş yoktu.
 *   - Responsive görsel üretilemezdi, çünkü rendition kavramı yoktu.
 *   - "Bu görsel nerede kullanılıyor?" sorusunun cevabı yoktu; silme kördü.
 *   - Yayınlanmış bir menü, sonradan düzenlenen fotoğrafı HABERSİZ
 *     gösterirdi.
 *
 * Kritik ayrım: **`media_asset` fiziksel dosya DEĞİLDİR.** Bir varlığın
 * birden çok sürümü, her sürümün birden çok rendition'ı olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Üç durum ekseni ──────────────────────────────────────────────
        // Tek `status` sütunu üç ayrı soruyu aynı yere doldurulmuş hâlde
        // tutuyordu ve hiçbirini güvenilir cevaplayamıyordu.
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('processing_status')->default('quarantined')->after('slot');
            $table->string('lifecycle_status')->default('draft')->after('processing_status');
            $table->string('visibility')->default('private')->after('lifecycle_status');
            $table->string('display_name')->nullable()->after('original_name');
            $table->string('asset_kind')->default('image')->after('slot');

            $table->index(['workspace_id', 'lifecycle_status']);
            $table->index(['workspace_id', 'slot']);
        });

        // ── Fiziksel dosya ───────────────────────────────────────────────
        // Depolama anahtarı ASLA değişmez; kullanıcının gördüğü ad değişir.
        // Restoran adı ya da koleksiyon değişince tek bayt taşınmaz.
        Schema::create('media_blobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key')->unique();
            $table->string('disk')->default('local');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            // Bozulmayı ve yinelenen dosyayı görünür kılar. Tenant'lar ARASI
            // fiziksel deduplication bilerek yapılmaz: silme, kota ve
            // "başka tenant bu dosyaya sahip mi" sızıntısı karmaşıklaşır.
            $table->string('checksum_sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'checksum_sha256']);
        });

        // ── Sürüm ────────────────────────────────────────────────────────
        // Orijinal DEĞİŞMEZ; her düzenleme yeni sürüm yaratır ve eskiye
        // dönülebilir.
        Schema::create('media_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_blob_id')->constrained('media_blobs');
            $table->unsignedInteger('version_number');
            $table->string('created_by_kind')->default('upload');
            $table->json('crop_intent')->nullable();
            $table->timestamps();

            $table->unique(['media_asset_id', 'version_number']);
        });

        // ── Rendition ────────────────────────────────────────────────────
        // Yeniden üretilebilir türevler. Hangi boru hattı sürümüyle
        // üretildiği tutulur ki algoritma değişince toplu yeniden üretim
        // yapılabilsin.
        Schema::create('media_renditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_blob_id')->constrained('media_blobs');
            $table->string('profile');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('format');
            $table->string('pipeline_version');
            $table->timestamps();

            $table->unique(['media_version_id', 'profile']);
        });

        // ── Kullanım ─────────────────────────────────────────────────────
        // "Nerede kullanılıyor" sorusunun cevabı. SÜRÜME bağlanır, varlığa
        // değil: yayınlanmış bir menü, sonradan düzenlenen fotoğrafı
        // kendiliğinden göstermemeli.
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_version_id')->nullable()->constrained('media_versions');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('slot');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('locale')->nullable();
            $table->unsignedBigInteger('publication_id')->nullable();
            // Aynı fotoğraf menü ürününde içerik, ana sayfada dekoratif
            // olabilir; alternatif metin KULLANIMA göre değişir (W3C).
            $table->string('alt_text_override')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'media_asset_id']);
            $table->index(['entity_type', 'entity_id']);
        });

        // ── İşleme işleri ────────────────────────────────────────────────
        Schema::create('media_processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('state')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_processing_jobs');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_renditions');
        Schema::dropIfExists('media_versions');
        Schema::dropIfExists('media_blobs');

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'lifecycle_status']);
            $table->dropIndex(['workspace_id', 'slot']);
            $table->dropColumn([
                'processing_status',
                'lifecycle_status',
                'visibility',
                'display_name',
                'asset_kind',
            ]);
        });
    }
};
