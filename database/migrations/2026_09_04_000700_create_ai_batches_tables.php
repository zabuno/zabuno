<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOPLU ORKESTRA (`docs/98` FF-75, `docs/adr/ADR-L11`).
 *
 * `ai_batches` + `ai_batch_pages` KALICI hafızadır: 40 sayfalık bir menü
 * okuması hangi sayfada, hangi bağlantıda, neden düştü — hepsi burada
 * durur. Kuyruktaki iş GEÇİCİ hafızadır: biter, kaybolur. Sayfa başına
 * bir iş, toplayıcı (collector) tek bir inceleme listesi çıkarır.
 *
 * `ai_connection_assignments.purpose`: yapışkanlığa "işin amacı" boyutu
 * (`docs/97` R30, Faz 5'e bırakılmıştı — öne çekildi). Toplu trafik
 * etkileşimli trafikten ayrı bir bağlantıya yapışabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('menu_id');
            $table->string('capability');
            $table->string('purpose')->default('batch');
            $table->string('state')->default('queued'); // queued | running | collected | failed
            $table->unsignedInteger('total_pages')->default(0);
            $table->unsignedInteger('done_pages')->default(0);
            $table->unsignedInteger('failed_pages')->default(0);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('collector_summary')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'state']);
        });

        Schema::create('ai_batch_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_batch_id')->constrained('ai_batches')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedBigInteger('media_asset_id');
            $table->unsignedInteger('position');
            $table->string('state')->default('queued'); // queued | running | done | failed
            $table->unsignedBigInteger('ai_artifact_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['ai_batch_id', 'state']);
        });

        Schema::table('ai_connection_assignments', function (Blueprint $table): void {
            $table->string('purpose')->default('interactive')->after('provider');
            $table->dropUnique(['workspace_id', 'provider']);
            $table->unique(['workspace_id', 'provider', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_connection_assignments', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'provider', 'purpose']);
            $table->dropColumn('purpose');
            $table->unique(['workspace_id', 'provider']);
        });
        Schema::dropIfExists('ai_batch_pages');
        Schema::dropIfExists('ai_batches');
    }
};
