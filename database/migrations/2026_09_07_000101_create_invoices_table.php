<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura kaydı — tahsilatın karşılığındaki BELGE (docs/107 Faz 1.4, docs/130).
 *
 * `updated_at` KASTEN yoktur, `ledger_entries` ile aynı gerekçeyle: bir
 * faturanın güncellenebildiği yer fatura değildir. Düzeltmenin yolu belgeyi
 * silmek ya da değiştirmek değil, KARŞI BELGE (iade/iptal faturası)
 * kesmektir — `kind = credit_note` ve `counter_of_invoice_id` o bağı kurar.
 *
 * Satıcı ve alıcı bilgisi belgeye MÜHÜRLENİR (anlık kopya), canlı
 * yapılandırmadan okunmaz: kesilmiş bir belgenin üstündeki ünvan, sahibin
 * altı ay sonra `.env`'i doldurmasıyla geriye dönük değişemez. Satıcı
 * alanları NULL olabilir — bugün `.env` boş (FF-198) ve boş bir alan
 * belgede "not yet provided" görünür; uydurulmaz.
 *
 * Alıcı alanları NULL DEĞİLDİR: alıcısı olmayan bir fatura kesilmez.
 *
 * `unique(series, number)` bu tablonun asıl güvencesidir: uygulama ne
 * yaparsa yapsın veritabanı aynı numarayı bir seride ikinci kez kabul etmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions');

            // 'invoice' | 'credit_note' (App\Domain\Billing\InvoiceKind).
            $table->string('kind', 16);
            $table->foreignId('counter_of_invoice_id')->nullable()->constrained('invoices');

            $table->string('series', 16);
            $table->unsignedInteger('number');
            $table->string('document_number', 64);
            $table->dateTime('issued_at');

            $table->char('currency', 3);
            $table->unsignedInteger('amount_minor');
            // KDV yapılandırılmamışsa üçü de NULL kalır — sıfır DEĞİL: sıfır
            // "KDV yok" demektir, NULL "bilinmiyor" demektir.
            $table->unsignedInteger('vat_rate_basis_points')->nullable();
            $table->unsignedInteger('net_minor')->nullable();
            $table->unsignedInteger('vat_minor')->nullable();

            $table->string('plan_name', 200);
            $table->unsignedInteger('period_days');

            // SATICI — `config/legal.php#company`'nin anlık kopyası.
            $table->string('seller_legal_name', 200)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_mersis', 64)->nullable();
            $table->string('seller_tax_office', 120)->nullable();
            $table->string('seller_tax_number', 32)->nullable();
            $table->string('seller_email', 200)->nullable();
            $table->string('seller_phone', 32)->nullable();

            // ALICI — `billing_profiles`'ın anlık kopyası.
            $table->string('buyer_legal_name', 200);
            $table->string('buyer_tax_number', 32);
            $table->string('buyer_tax_office', 120);
            $table->text('buyer_address');
            $table->string('buyer_city', 120);
            $table->char('buyer_country', 2);
            $table->string('buyer_email', 200);
            $table->string('buyer_phone', 32);

            $table->timestamp('created_at')->nullable();

            $table->unique(['series', 'number']);
            $table->unique('document_number');
            // Bir ödemenin bir faturası ve en fazla bir karşı belgesi olur;
            // geç gelen webhook ile tarayıcı geri dönüşü ikinci belge yaratamaz.
            $table->unique(['payment_transaction_id', 'kind']);
            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
