<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ai_artifacts.used_fallback` — `docs/97` R12.
 *
 * Bir taslak yedek sağlayıcıdan geldiyse bunu SAKLAMAK gerekir: inceleme
 * ekranı `ShowMenuAiImportController`'dan (satır satır, canlı çağrının
 * SONRASINDA) okur — bayrağın yalnız çalışma zamanı nesnesinde (`AiArtifact`)
 * yaşaması, sayfa yenilendiğinde "bu öneri yedekten geldi" bilgisini
 * kaybettirirdi (`docs/51` UNK-03: sessiz geçiş yasak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_artifacts', function (Blueprint $table): void {
            $table->boolean('used_fallback')->default(false)->after('uncertain_field_count');
        });
    }

    public function down(): void
    {
        Schema::table('ai_artifacts', function (Blueprint $table): void {
            $table->dropColumn('used_fallback');
        });
    }
};
