<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZAMANLANMIŞ YAYIN — "Planla" (kanonik kaynak `panel.dc.html`, Yayınlama
 * ekranı; sahibin 2026-09-05 kararı).
 *
 * Restoran sahibinin yolculuğu: zam kararı öğlen alınır ama akşam servisi
 * sürerken masadaki misafirin menüsünün fiyatı değişsin istemez. "Bu gece
 * 03:00" der ve uyur. Sabah menü yeni fiyatlarla açılmıştır; QR aynı, kart
 * aynı, yalnız sürüm numarası bir artmıştır.
 *
 * SNAPSHOT BURADA DONAR ve bu bilinçli bir karardır. Yayın anında taslaktan
 * yeniden üretilseydi, sahibin onayladığı içerik ile gece yayınlanan içerik
 * farklı olabilirdi: akşam yarım bıraktığı bir düzenleme, o saatte kimse
 * bakmıyorken misafirin önüne çıkardı. Donmuş snapshot ayrıca gece 03:00'te
 * "taslak hazır değil" hatasını imkânsız kılar — hazır olmama, planın
 * KURULDUĞU an reddedilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_publication_schedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('location_id');

            // UTC'de saklanır, ekranda `Europe/Istanbul` gösterilir. Yerel
            // saati saklamak, yaz saati değişiminde bir saatlik sessiz bir
            // kayma demekti.
            $table->timestamp('scheduled_for');

            // `pending` → `publishing` → `published` | `failed`, ya da
            // `pending` → `cancelled`. Ara `publishing` hâli İKİ KEZ
            // ÇALIŞMAYI imkânsız kılar: komut kaydı yalnız `pending` iken
            // sahiplenebilir ve sahiplenme tek bir atomik güncellemedir.
            $table->string('state', 16)->default('pending');

            $table->longText('snapshot');

            // Yayın anında görsel kullanımını yazabilmek için (`docs/76`).
            $table->longText('visible_item_ids');
            $table->unsignedBigInteger('brand_id')->nullable();

            $table->unsignedBigInteger('scheduled_by');
            $table->unsignedBigInteger('publication_id')->nullable();

            $table->timestamps();

            // Vakti gelen kayıtları bulmanın tek sorgusu bu iki sütundur.
            $table->index(['state', 'scheduled_for']);
            $table->index(['menu_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_publication_schedules');
    }
};
