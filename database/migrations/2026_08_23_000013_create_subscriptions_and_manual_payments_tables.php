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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('state');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        Schema::create('manual_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->foreignId('plan_id')->constrained('plans');
            $table->dateTime('ends_at');
            $table->text('payment_note');
            $table->string('document_reference');
            $table->uuid('idempotency_key');
            $table->timestamps();

            $table->unique(['workspace_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_payments');
        Schema::dropIfExists('subscriptions');
    }
};
