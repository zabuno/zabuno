{{-- TEK birincil eylem. İkinci bir eşit ağırlıklı düğme, kararı böler.

     Hedef BUGÜN yayında olan bir yoldur (`/pricing`); yayınlanmamış bir
     sayfaya CTA vermek, ziyaretçiyi 404'e göndermek olurdu.

     Üstündeki marka çizgisi bir süs değil bir NOKTALAMA: sayfanın metni
     burada bitiyor, geriye yalnız bir seçim kalıyor. Giriş cümlesindeki
     dikey rayla aynı renk ve aynı kalınlık — açan ve kapatan işaret aynı.

     Düğme daisyUI'nin `dz-btn`i (`docs/136` E9); geometrisi buranın, çünkü
     daisyUI gövde boyutunu 14 piksele sabitliyor ve yoğunluk fontu
     küçülterek sağlanmaz (`docs/118` E3). Dokunma hedefi 44 pikselin altına
     inmez; dar ekran tabandır. --}}
<section class="site-doc-block site-doc-cta" data-block="cta">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <p class="site-doc-paragraph site-doc-prose">{{ $block->entries[0]->text }}</p>
    <a href="{{ $block->entries[0]->href }}" class="dz-btn site-doc-action">{{ $block->entries[0]->term }}</a>
</section>
