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
        Schema::create('iyzico_sandbox_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->foreignId('plan_id')->constrained('plans');
            $table->uuid('idempotency_key')->unique();
            $table->uuid('conversation_id')->unique();
            $table->string('token')->nullable();
            $table->string('redirect_url')->nullable();
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('state');
            $table->string('reference_code')->nullable()->unique();
            $table->string('payment_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iyzico_sandbox_transactions');
    }
};
