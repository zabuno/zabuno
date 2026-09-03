<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ASLIN PARMAK İZİ — `docs/49` Faz 3 madde 1 ve 4, `docs/98` FF-69.
 *
 * Asıl dosya karantinada değişmeden durur (immutable); ama "değişmedi"
 * iddiası ancak bir parmak iziyle kanıtlanır. Aynı parmak izi ikinci kez
 * geldiğinde de sahibe söylenir: "bu fotoğraf kütüphanende zaten var" —
 * kiracı İÇİNDE, kiracılar arası değil (`docs/49` §3.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('original_checksum_sha256', 64)->nullable()->after('size_bytes');
            $table->index(['workspace_id', 'original_checksum_sha256']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'original_checksum_sha256']);
            $table->dropColumn('original_checksum_sha256');
        });
    }
};
