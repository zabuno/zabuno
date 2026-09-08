{{-- SSS — betiksiz KATLANIR (yönerge §13.3, `docs/118` E8).

     Ölçüldü (320×568): altı soru açık hâlde ~1400 piksel yer tutuyordu ve
     eylem çağrısına ulaşmak için altı cevabın tamamını kaydırmak
     gerekiyordu — onları okumayan kişi için tamamen boşa bir kaydırma.

     `details`/`summary` tarayıcının KENDİ açılır kapanır düğmesidir:
     klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betik olmadan
     açılır. Kabuk da aynı ilkeyi kullanıyor; ikinci bir açılır kapanır
     düzenek icat etmek, aynı işi iki farklı yerde farklı yapmak olurdu.

     KATLANAN ŞEY YALNIZ GÖRÜNÜM:

     · Cevap ilk HTML yanıtında zaten var — betik çalıştırmayan bir bot onu
       okuyor ve `FAQPage` işaretlemesi görünmeyen bir bilgi ilan etmiyor
       (§14).
     · Soru gizlenmiyor: hâlâ görünür bir H3, yani ekran okuyucu
       kullanıcısının başlıkla gezinmesi bozulmuyor. Başlık `summary`nin
       İÇİNDE durur — HTML bunu açıkça izin verilen tek istisna olarak
       tanımlar.
     · Açılır kapanır işareti CSS'le çizilir ve ARTI'dır, ok değil: bir okun
       sağdan sola yazılan bir dilde aynalanması gerekir, artının yönü
       yoktur. --}}
<section class="site-doc-block" data-block="faq">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <div class="site-doc-faq">
        @foreach ($block->entries as $entry)
            <details class="site-doc-faq-item">
                <summary class="site-doc-faq-question"><h3 class="site-doc-faq-title">{{ $entry->term }}</h3></summary>
                <p class="site-doc-faq-answer">{{ $entry->text }}</p>
            </details>
        @endforeach
    </div>
</section>
