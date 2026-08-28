<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saat dilimi MARKAYA değil ŞUBEYE aittir (`docs/62`).
 *
 * Aynı markanın İstanbul, Dubai ve Berlin şubeleri olabilir; üçünün saat
 * dilimi farklıdır. Alan markada durduğu sürece ikinci şube açılır açılmaz
 * yanlış olur — ve yanlışlığı görünmez, çünkü tek şubeli işletmede doğru
 * görünmeye devam eder.
 *
 * Göç İKİ ADIMDA yapılır. Bu ilk adım şubeye sütunu ekler ve markadaki
 * değerden doldurur; `brands.timezone` YERİNDE KALIR. Sütunu aynı anda
 * düşürmek, hâlâ okuyan her kod yolunu tek pakette değiştirmeyi zorunlu
 * kılardı; okunmayı bıraktığı kanıtlanmadan düşürmek geri dönüşü olmayan
 * bir bahis olurdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            // Nullable EKLENİR: mevcut satırlar doldurulmadan NOT NULL
            // konulamaz. Doldurduktan sonra aşağıda sıkılaştırılır.
            $table->string('timezone')->nullable()->after('country_code');
        });

        // Her şube kendi markasının saat dilimini devralır: bugünkü davranış
        // aynen korunur, sahiplik değişir.
        foreach (DB::table('brands')->select('id', 'timezone')->cursor() as $brand) {
            DB::table('locations')
                ->where('brand_id', $brand->id)
                ->update(['timezone' => $brand->timezone]);
        }

        // Markası bulunamayan bir şube olmamalı; yine de UTC'ye düşülür,
        // çünkü boş bir saat dilimi sessizce yanlış saat gösterir.
        DB::table('locations')->whereNull('timezone')->update(['timezone' => 'UTC']);

        Schema::table('locations', function (Blueprint $table): void {
            $table->string('timezone')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
