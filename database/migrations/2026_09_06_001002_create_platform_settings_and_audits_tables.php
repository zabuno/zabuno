<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform ayarları + platform denetim izi (docs/107 Faz 1.1, kip anahtarı).
 *
 * `platform_settings`: süperadminin çalışma zamanında değiştirdiği, anahtar
 * → JSON değer. Config dosyası önyüklemede donar (`config:cache`); bu tablo
 * onun yerine geçmez, onun ÜSTÜNE gelir — ilk anahtar `billing.mode`.
 *
 * `platform_audits`: kasa denetiminin (`platform_credential_audits`)
 * sağlayıcıya bağlı olmayan kardeşi. Append-only, `updated_at` yok. Kip
 * değişimi ve iade buraya yazılır; `details` sır taşımaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_audits', function (Blueprint $table): void {
            $table->id();
            // billing.mode | billing.refund | ...
            $table->string('scope');
            $table->string('action');
            // workspace:{id}/transaction:{id} gibi — okunabilir, sırsız.
            $table->string('subject')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['scope', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audits');
        Schema::dropIfExists('platform_settings');
    }
};
