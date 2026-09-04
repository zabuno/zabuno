<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurumsal sitenin sayfa kütüğü — FF-117, yönerge §5.
 *
 * Site haritasındaki her canonical yol BURADA tek bir kayıttır. Yüzlerce
 * fiziksel Blade dosyası üretip her birine aynı "hazırlanıyor" satırını
 * kopyalamak, zamanla unutulan dosyalar üretirdi; bir sayfayı açmak için
 * koddan bileşen silinmez, yalnız kontrollü yayın durumu değişir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();

            // Anahtar YOLDAN türer ama yol değişse bile DEĞİŞMEZ: eski anahtar
            // aynı sayfayı gösterir, yeni bir kayıt doğmaz.
            $table->string('page_key')->unique();

            // Yönergenin 5. değiştirilemez kararı: her canonical URL tek bir
            // içerik kaydına karşılık gelir. Benzersizlik dil ile birliktedir;
            // aynı sayfanın Türkçesi ve İngilizcesi farklı yollarda yaşar.
            $table->string('locale', 5);
            $table->string('canonical_path');
            $table->unique(['locale', 'canonical_path']);

            $table->string('content_type');
            $table->string('template_key');
            $table->string('parent_key')->nullable();
            $table->string('title');

            // `P0` ilk yayında bulunmalı, `P1` büyüme, `P2` ürün genişlediğinde.
            $table->string('priority', 2);

            $table->string('publication_status');

            /*
                ŞABLON bir sayfa DEĞİLDİR: `/tr/blog/{slug}/` bir desendir ve
                tek tek yaratılmaz. Dış bağlantı da bu sitede bir sayfa
                değildir; ona hazırlanıyor ekranı göstermek, olmayan bir sayfayı
                yapıyormuş gibi göstermek olurdu.
            */
            $table->boolean('is_template')->default(false);
            $table->boolean('is_external')->default(false);

            /*
                503 yalnız GERÇEKTEN yayınlanmış bir sayfanın kısa bakımı için
                kullanılabilir. Bu bayrak olmadan, hiç yayınlanmamış bir sayfa
                bakıma alınabilir ve arama motoruna var olmayan bir şeyin geri
                geleceği söylenirdi.
            */
            $table->boolean('was_ever_published')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->timestamps();
            // Silme varsayılan olarak yumuşaktır: bir sayfa kaydını gerçekten
            // silmek, onun adres geçmişini de silmek olurdu.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
