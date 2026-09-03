<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ai_artifacts.subject_id` — bu taslak HANGİ kayıt için üretildi.
 *
 * `menu.extract` buna ihtiyaç duymuyordu: bir workspace'in bugün tek menüsü
 * var, controller onu workspace'ten türetiyor. Ama `product.description`
 * gibi kayıt-başına yetenekler için (bir workspace'te onlarca ürün olabilir)
 * "bu taslak hangi ürün/menü öğesi içindi" sorusunun cevabı taslağın
 * KENDİSİNDE durmalı — apply anında URL parametresine körü körüne
 * güvenmek, bir istemci hatasında yanlış ürünün üstüne yazardı.
 *
 * Anlamı yeteneğe göre değişir (bugün `product.description` için menu_item
 * id'si); bu yüzden foreign key değil, düz nullable tam sayı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_artifacts', function (Blueprint $table): void {
            $table->unsignedBigInteger('subject_id')->nullable()->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_artifacts', function (Blueprint $table): void {
            $table->dropColumn('subject_id');
        });
    }
};
