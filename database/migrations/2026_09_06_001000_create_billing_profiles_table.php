<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura profili — alıcı bilgisi UYDURULMAZ (docs/107 Faz 1.1, docs/09 §5).
 *
 * Bugüne kadar tek alıcı `.env`'deki sandbox personasıydı. Gerçek bir
 * tahsilatın karşılığında kesilecek belge gerçek bir unvan, vergi numarası
 * ve adres ister; bunlar çalışma alanı başına TEK satırdır ve yalnız tam
 * hâliyle yazılır — yarım bir profil "var" görünüp ilk ödemede patlamaz.
 *
 * Kart verisi burada YOKTUR ve olmayacaktır: kart yalnız Iyzico'nun
 * barındırdığı ödeme sayfasına girilir (docs/09 §5, PCI kapsamı).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->string('legal_name', 200);
            $table->string('tax_number', 32);
            $table->string('tax_office', 120);
            $table->text('address');
            $table->string('city', 120);
            // ISO 3166-1 alpha-2; ülke adı serbest metin olarak saklanmaz.
            $table->char('country', 2);
            $table->string('email', 200);
            $table->string('phone', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
