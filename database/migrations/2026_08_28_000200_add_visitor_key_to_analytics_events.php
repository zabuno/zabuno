<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yaklaşık benzersiz ziyaretçi sayımı için türetilmiş anahtar — `docs/68`.
 *
 * Sütun NULLABLE ve öyle kalacak: bu göçten önce yazılmış her olayın anahtarı
 * yoktur ve olamaz. Geriye dönük bir değer uydurmak, olmayan bir ölçümü varmış
 * gibi göstermek olurdu — eski dönemin benzersiz sayısı bilinmiyor ve öyle
 * kalmalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->string('visitor_key', 64)->nullable()->after('event_type');

            // Benzersiz sayım aralık + kapsam filtreleriyle birlikte çalışır;
            // indeks o sorgunun şeklini izler.
            $table->index(
                ['workspace_id', 'location_id', 'occurred_at', 'visitor_key'],
                'analytics_events_visitor_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex('analytics_events_visitor_index');
            $table->dropColumn('visitor_key');
        });
    }
};
