<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKIN'İN BİÇİM EKSENİ — FF-174, `docs/113` §5.3.
 *
 * Marka kimliği iki eksenlidir ve ikisi ayrı şeylerdir: RENK bir tondur
 * (`primary_color`), BİÇİM ise bir seçenektir. İkisini tek sütuna sıkıştırmak
 * ya da biçimi de serbest değer yapmak, ölçülmemiş bir kombinasyonu misafirin
 * ekranına koymak olurdu.
 *
 * Sütun `nullable` ve varsayılansızdır: seçmeyen restoran bugünkü nötr
 * görünümü alır ve "seçmiş" gibi gösterilmez.
 *
 * Uzunluk 8 karakter, tek harf değil: bugün değerler `a`..`f`
 * (`resources/css/aep/tokens/variants.css`) ama PostgreSQL `varchar(1)`
 * sınırını GERÇEKTEN uygular, SQLite uygulamaz. Yarın adlandırılmış bir
 * varyant eklenirse hata yalnız dağıtım motorunda çıkardı — yani yerelde
 * yeşil, canlıda kırmızı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->string('skin_variant', 8)->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('skin_variant');
        });
    }
};
