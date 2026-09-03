<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İletişim mesajları — `docs/88` (P1-01).
 *
 * Mesaj ÖNCE SAKLANIR, sonra (yapılandırılmışsa) e-postayla gönderilir.
 * Gereksinim iletişimi gerçek e-postaya bağlıyor ve o madde sağlayıcı
 * hesabını bekliyor; ama "ulaşmak" için e-posta şart değil — saklanan bir
 * mesaj kaybolmaz. E-postaya bağlamak, sağlayıcı gelene kadar formu ölü
 * tutardı, yani sorunun kendisi devam ederdi.
 *
 * IP ve tarayıcı bilgisi SAKLANMAZ. Bir restoran sahibinin fiyat sorması,
 * hakkında iz tutulmasını gerektirmez (`docs/68` ile aynı ilke).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            // Hangi dilde yazıldığı, cevabın hangi dilde yazılacağını söyler.
            $table->string('locale', 10)->nullable();
            // Gönderim denendi mi ve sonucu ne oldu: sağlayıcı yokken de
            // mesaj duruyor, ama "gönderilmedi" bilgisi kaybolmamalı.
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_failure')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
