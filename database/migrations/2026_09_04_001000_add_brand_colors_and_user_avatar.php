<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MARKA RENKLERİ ve PROFİL AVATARI — sahibin isteği (2026-09-04).
 *
 * Restoran sahibi kendi markasının birincil/ikincil rengini seçer ve kendi
 * profil fotoğrafını yükler. İki karar:
 *
 *   1. Renkler MARKADA durur, kullanıcıda değil: aynı çalışma alanındaki
 *      herkes aynı menüyü yayınlar, renk kişisel bir tercih değil kurumsal
 *      bir karardır. Kişisel tercih (açık/koyu tema) ayrı yerdedir.
 *   2. Avatar bir MEDYA VARLIĞIDIR, ayrı bir dosya yolu değil: tarama,
 *      türev üretimi, kota ve silme etkisi zaten o boru hattında
 *      (`docs/49`). Sütun yalnız o varlığa işaret eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            // Depolanan biçim `#rrggbb`; doğrulama istekte yapılır.
            $table->string('primary_color', 7)->nullable()->after('currency');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('avatar_media_asset_id')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_media_asset_id');
        });
    }
};
