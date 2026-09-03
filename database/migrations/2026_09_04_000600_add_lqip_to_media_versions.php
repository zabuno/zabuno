<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LQIP (`docs/49` Faz 6 madde 4): sürüm başına tek, çok küçük (≈16 px)
 * bulanık yer tutucu — data URI olarak satırda taşınır ki misafir menüsü
 * fotoğraf inene kadar boş kutu değil, rengi görsün.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_versions', function (Blueprint $table): void {
            $table->text('lqip')->nullable()->after('crop_intent');
        });
    }

    public function down(): void
    {
        Schema::table('media_versions', function (Blueprint $table): void {
            $table->dropColumn('lqip');
        });
    }
};
