<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host yetenek kanıtı — `docs/16` MED-01.
 *
 * Her prob bir satırdır ve satırlar silinmez: bir host değişikliğinin neyi
 * değiştirdiği ancak iki kayıt karşılaştırılarak görülebilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_capability_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('php_version');
            $table->json('capabilities');
            $table->json('degradations');
            $table->text('claim');
            $table->timestampTz('ran_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_capability_evidence');
    }
};
