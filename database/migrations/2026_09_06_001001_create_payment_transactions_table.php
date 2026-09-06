<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kendi kendine abonelik işlemleri — kipten bağımsız (docs/107 Faz 1.1).
 *
 * `iyzico_sandbox_transactions` KALDIRILMADI: o tablo eski "aktif planı
 * sandbox'ta ücretlendir" yüzeyinin kaydıdır ve o yüzey dondurulmuştur. Bu
 * tablo, kiracının plan seçip abone olduğu yeni yolun kaydıdır ve `mode`
 * sütunu işlemin hangi geçitten geçtiğini söyler — geç gelen bir webhook,
 * anahtar o arada değişmiş olsa bile, kendi kipinin geçidiyle doğrulanır.
 *
 * Durumlar: reserved → initiated → succeeded | failed; succeeded → refunded.
 * `subscription_ends_at_before/after`: bu ödemenin aboneliği nereden nereye
 * taşıdığı — iade politikası bu dönemi düşer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('mode', 16);
            $table->uuid('idempotency_key')->unique();
            $table->uuid('conversation_id')->unique();
            $table->string('token')->nullable();
            $table->string('redirect_url', 2048)->nullable();
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('period_days');
            $table->string('state', 16);
            $table->string('reference_code')->nullable()->unique();
            $table->string('payment_id')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->dateTime('subscription_ends_at_before')->nullable();
            $table->dateTime('subscription_ends_at_after')->nullable();
            $table->string('refund_reason', 500)->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
