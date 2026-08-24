<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_isolation_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('status');
            $table->string('scope');
            $table->string('runner');
            $table->timestampTz('ran_at');
            $table->unsignedInteger('duration_ms');
            $table->integer('exit_code');
            $table->char('git_sha', 40);
            $table->boolean('git_dirty');
            $table->char('source_snapshot_sha256', 64);
            $table->char('suite_manifest_sha256', 64);
            $table->char('output_sha256', 64);
            $table->char('integrity_sha256', 64);
            $table->text('claim');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_isolation_evidence');
    }
};
