{{-- İLGİLİ SAYFALAR — yalnız GERÇEKTEN AÇILAN sayfalar.

     Süzgeç denetleyicide çalıştı: yayınlanmamış bir sayfa hiçbir yerden iç
     bağlantı almaz (`docs/105` §2.2(3)). Süzgeçten hiçbir şey geçmediyse
     bölüm HİÇ çizilmez — boş bir başlık, sayfayı uzatıp hiçbir soruya cevap
     vermeyen ince içeriktir.

     Bunlar cümle içindeki bağlantı değil, GEZİNTİ hedefi: her biri kendi
     satırında, tam dokunma yüksekliğinde, kenarlıklı. Burada okunan bir
     metin yok — dokunulacak bir hedef var ve hedefin sınırı görünür olmalı.
     Geniş ekranda ızgara kendiliğinden çoğalır. --}}
@if ($relatedLinks !== [])
    <section class="site-doc-block" data-block="related">
        <h2 class="site-doc-heading">{{ $block->heading }}</h2>
        <ul class="site-doc-related" role="list">
            @foreach ($relatedLinks as $link)
                <li>
                    <a href="{{ $link['path'] }}" class="site-doc-related-link">{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
