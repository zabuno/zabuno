<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Menüye kalıcı, herkese açık bir adres verir.
 *
 * Owner kararı (2026-08-27): menüler arama motorunda görünsün. Bunun bir
 * mimari sonucu var: **QR token'ı bu adres olamaz.** Token, basılmış bir
 * kodun anahtarıdır ve `/q/` yüzeyi bilerek hız sınırlıdır; onu bir
 * sitemap'te yayımlamak, taranmasını engellemeye çalıştığımız uzayı
 * toplu hâlde teslim etmek olurdu.
 *
 * Bu yüzden menünün kimliği (`public_key`) ile basılı kodun kimliği (token)
 * ayrılır. Anahtar sıralı DEĞİLDİR: sıralı bir kimlik, platformdaki toplam
 * işletme sayısını herkese açık biçimde ilan eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('public_key', 12)->nullable()->unique()->after('id');

            // Yayınlanmış ve içeriği olan menüler indekslenir. Varsayılan
            // kapalıdır: bir menüyü aramaya açmak, boş veya deneme bir
            // menüyü de açma riskidir.
            $table->boolean('is_indexable')->default(false)->after('state');
        });

        foreach (DB::table('menus')->whereNull('public_key')->pluck('id') as $id) {
            DB::table('menus')->where('id', $id)->update([
                'public_key' => Str::lower(Str::random(10)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'is_indexable']);
        });
    }
};
