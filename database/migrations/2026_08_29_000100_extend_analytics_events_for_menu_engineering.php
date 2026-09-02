<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menü mühendisliği olayları — `docs/84` (P1-08).
 *
 * İkinci bir tablo AÇILMAZ: aynı soruların cevapları aynı yerde durmalı,
 * yoksa "toplam" iki kaynaktan toplanır ve ayrışır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            // Kalıcı adresten (`/menu/{key}`) gelen misafirin karekodu YOKTUR
            // ve bu ziyaretler bugüne kadar hiç ölçülemiyordu: sütun zorunlu
            // olduğu için o yol olay yazamıyordu.
            $table->unsignedBigInteger('qr_code_id')->nullable()->change();

            $table->foreignId('menu_item_id')->nullable()->after('menu_id')->constrained('menu_items')->nullOnDelete();

            // YALNIZ sonuçsuz aramalarda dolar ve kırpılır. Sonuçlu bir
            // aramanın terimini saklamanın ürün karşılığı yok — ve
            // saklanmayan veri sızmaz.
            $table->string('search_term', 80)->nullable()->after('menu_item_id');

            $table->index(['workspace_id', 'event_type', 'occurred_at'], 'analytics_events_type_range_index');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex('analytics_events_type_range_index');
            $table->dropColumn(['menu_item_id', 'search_term']);
        });
    }
};
