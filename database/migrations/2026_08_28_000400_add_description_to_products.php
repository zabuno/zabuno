<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün açıklaması — `docs/77` (P0-04).
 *
 * "Adana Kebap · 380,00 TL" bir satırdır. Açıklamalı ve fotoğraflı bir kart
 * satış aracıdır: misafir ne yiyeceğini bilir, garsona sormaz, sipariş
 * hızlanır. Sahibin menüsünü dijitale taşımasının en somut sebebi buydu ve
 * alan yoktu.
 *
 * Açıklama ÜRÜNDE durur, menü satırında değil: aynı ürün iki kategoride
 * görünüyorsa açıklaması ikisinde de aynıdır ve bir kez yazılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
