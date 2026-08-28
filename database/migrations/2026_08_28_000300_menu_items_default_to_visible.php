<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yeni ürün GÖRÜNÜR doğar — `docs/74` (P0-02).
 *
 * Sütun varsayılanı `false` idi ve sessiz bir aktivasyon duvarı kuruyordu:
 * 40 ürün giren sahip "Yayınla"ya basıyor, "gösterilecek hiçbir şey yok"
 * hatası alıyor ve kırkının görünürlüğünü tek tek açması gerektiğini hiçbir
 * yerde okumuyordu.
 *
 * Uygulama kodu artık `true` yazıyor. Bu göç ŞEMAYI da hizalar: aksi hâlde
 * doğrudan `INSERT` yapan bir tohum ya da ileride yazılacak bir kod yolu
 * duvarı sessizce geri kurardı.
 *
 * MEVCUT SATIRLAR DEĞİŞMEZ. Bugün gizli olan bir ürünü görünür yapmak,
 * sahibin bilerek sakladığı bir şeyi misafire açmak olurdu — ve bu göç
 * geri alınamaz bir ürün kararı verirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_visible')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_visible')->default(false)->change();
        });
    }
};
