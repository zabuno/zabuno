<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Capability Plane — denetim ve artifact (`docs/51` §3.4, §3.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Her AI çağrısı bir satır bırakır — başarısız olanlar dahil.
         *
         * Bütçe bu tablodan TÜRETİLİR; ayrı bir sayaç tutulmaz. Ölçülmeyen
         * maliyet iddia edilemez (`docs/51` §10).
         */
        Schema::create('ai_invocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('capability');
            $table->string('model_identity');
            $table->string('outcome');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_minor')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'capability']);
        });

        /*
         * Üretilen artifact — TASLAKTA durur.
         *
         * `applied_at` bir artifact'in typed komuta dönüşüp uygulandığı andır
         * ve yalnız insan onayından sonra dolar. `idempotency_key` yeniden
         * denemenin aynı taslağı İKİ KEZ uygulamasını engeller
         * (`docs/16` AI-11).
         */
        Schema::create('ai_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_invocation_id')->nullable()->constrained('ai_invocations');
            $table->string('capability');
            $table->string('model_identity');
            $table->string('prompt_version');
            $table->string('schema_version');
            $table->string('idempotency_key')->unique();
            $table->json('fields');
            $table->unsignedInteger('uncertain_field_count')->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_artifacts');
        Schema::dropIfExists('ai_invocations');
    }
};
