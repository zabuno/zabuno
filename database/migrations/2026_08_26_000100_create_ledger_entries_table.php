<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CORE-12 — değişmez çift-kayıtlı defter.
 *
 * `updated_at` KASTEN yoktur: bir satırın güncellendiği bir defter, defter
 * değildir. Düzeltme karşı kayıtla yapılır, satır değiştirilerek değil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('reference');
            $table->string('debit_account');
            $table->string('credit_account');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('description')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            // Aynı olay iki kez (webhook + callback) gelse bile tek kayıt olur.
            $table->unique(['workspace_id', 'reference']);
            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['workspace_id', 'debit_account']);
            $table->index(['workspace_id', 'credit_account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
