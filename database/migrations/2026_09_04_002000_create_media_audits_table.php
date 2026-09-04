<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medya denetim izi — `docs/49` Faz 7 madde 4.
 *
 * "Bu fotoğrafı kim sildi?" bir restoranda gerçek bir sorudur: menüden bir
 * yemeğin görseli kaybolduğunda sahibi ekibine sorar ve bugüne kadar cevabı
 * hiçbir yerde yoktu. Kota, izin ve mutabakat vardı; kaydı tutan yoktu.
 *
 * Satır BİR KEZ yazılır (`updated_at` yok): denetim izi düzeltilebiliyorsa
 * denetim izi değildir. Varlık silinse bile kayıt kalır — asıl değeri olan
 * an, varlığın artık orada olmadığı andır; bu yüzden `media_asset_id` bir
 * yabancı anahtar DEĞİL, düz bir sayıdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Yabancı anahtar değil: varlık silindikten sonra da kayıt yaşar.
            $table->unsignedBigInteger('media_asset_id');
            // uploaded | renamed | trashed | restored | reprocessed |
            // version_restored | original_download_requested
            $table->string('action');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['media_asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_audits');
    }
};
