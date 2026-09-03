<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasa yazma denetim izi — append-only, SIRSIZ.
 *
 * Kim hangi sağlayıcının kimlik-bilgisini ne zaman değiştirdi/kapattı:
 * güvenlik açısından tutulması gereken olgu budur. Sır değeri, alan içeriği
 * ya da maske BURAYA yazılmaz — yalnız eylem ve fail. `updated_at` yok:
 * satır bir kez yazılır, değişmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credential_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            // set | disabled
            $table->string('action');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credential_audits');
    }
};
