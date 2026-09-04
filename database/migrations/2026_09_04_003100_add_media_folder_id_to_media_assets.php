<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varlığı klasöre bağlayan tek sütun — `docs/108` §3 madde 1.
 *
 * **Neden nullable.** Bugün depoda duran her fotoğrafın klasörü yok ve
 * olmayacak da: göç hiçbir satıra dokunmaz, hepsi `null` ile devam eder ve
 * kaynağın "Tümü" görünümünde eskisi gibi listelenir. Zorunlu bir sütun,
 * göç anında elli fotoğrafı uydurma bir klasöre tıkmak demek olurdu —
 * sahibin hiç kurmadığı bir düzen.
 *
 * `nullOnDelete`: klasör silinince fotoğraf silinmez, yalnız rafından
 * iner. Kaynağın değişmez kuralı budur — "asıl korunur" (`docs/108` §4).
 * Bir klasörü kaldıran sahip bir RAFI kaldırdığını düşünür; üstündeki
 * tabakları çöpe attığını değil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('media_folder_id')
                ->nullable()
                ->after('workspace_id')
                ->constrained('media_folders')
                ->nullOnDelete();

            // Kütüphane süzgecinin tek sorusu: "bu kiracının bu klasöründe
            // ne var?" — indeks tam o soruyu karşılar.
            $table->index(['workspace_id', 'media_folder_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'media_folder_id']);
            $table->dropConstrainedForeignId('media_folder_id');
        });
    }
};
