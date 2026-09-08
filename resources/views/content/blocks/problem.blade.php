{{-- KULLANICI PROBLEMİ — anlatının ilk yarısı.

     "Çözüm" ile birlikte sayfanın tek anlatı dönüşünü kurar ve bu yüzden
     ikisi AYNI tipografik ritmi paylaşır: ayrı bir düzen icat etmek okuyucuyu
     yavaşlatırdı. Ayrımı ton taşıyor — burası misafirin bugünkü dünyası,
     sessiz anlatılır; başlık bile ikincil mürekkeple durur. --}}
<section class="site-doc-block site-doc-prose" data-block="problem">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    @foreach ($block->entries as $entry)
        <p class="site-doc-paragraph">{{ $entry->text }}</p>
    @endforeach
</section>
