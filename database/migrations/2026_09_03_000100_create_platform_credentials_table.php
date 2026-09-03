<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform sağlayıcı kimlik-bilgisi kasası — `modules/ai-provider-account-vault.md`.
 *
 * Sır DÜZ yazıyla saklanmaz: `secret_ciphertext` uygulama anahtarıyla
 * şifrelenir (master key webroot dışında, env'de). Düz alanlar (domain,
 * endpoint) ayrı sütunda açık durur — sır değiller. `secret_hints` yalnız
 * son-4 maskesini taşır, tam değeri DEĞİL: panelin gösterdiği tek şey odur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table): void {
            $table->id();
            // Sağlayıcı başına tek satır — enum değeriyle (mailgun/iyzico/openai/gemini).
            $table->string('provider')->unique();
            // Düz (sır olmayan) alanlar: domain, endpoint, base_url, organization...
            $table->json('plain_fields')->nullable();
            // Şifrelenmiş sır alanların JSON'u — asla düz okunmaz.
            $table->text('secret_ciphertext')->nullable();
            // Alan → son 4 karakter maskesi. Panelin gördüğü tek sır izi.
            $table->json('secret_hints')->nullable();
            // active | disabled. Kapalı bir kayıt çözülmez, silinmez.
            $table->string('state')->default('active');
            $table->timestamp('last_rotated_at')->nullable();
            // Kim yazdı — denetim için; kullanıcı silinse de kayıt kalır.
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
