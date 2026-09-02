<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Bugün tükendi" — `docs/82` (P1-04).
 *
 * Akşam servisinde balık bitti. Sahibin tek seçeneği ürünü GİZLEMEKTİ; o
 * zaman ürün menüden tamamen kayboluyor, misafir "bugün balık var mı?" diye
 * soruyor ve garson "vardı, bitti" diyor — dijital menünün çözmesi gereken
 * sürtünme aynen kalıyordu. Ertesi sabah sahip altı ürünü tek tek geri
 * açmak zorundaydı.
 *
 * GÖRÜNÜRLÜKTEN AYRI bir eksen: gizli bir ürün menüde YOKTUR; tükenmiş bir
 * ürün menüde VARDIR ama bugün alınamaz.
 *
 * ZAMAN DAMGASI, bayrak değil. "Bugün tükendi" ifadesindeki BUGÜN, şubenin
 * kendi saat diliminde bir gündür. Damga sayesinde işaret ertesi iş
 * gününde kendiliğinden düşer ve sahip altı ürünü tek tek geri açmaz —
 * hiçbir zamanlanmış görev gerekmeden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->timestamp('out_of_stock_since')->nullable()->after('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('out_of_stock_since');
        });
    }
};
