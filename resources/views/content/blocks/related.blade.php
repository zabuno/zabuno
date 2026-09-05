{{-- İLGİLİ SAYFALAR — yalnız GERÇEKTEN AÇILAN sayfalar.

     Süzgeç denetleyicide çalıştı: yayınlanmamış bir sayfa hiçbir yerden iç
     bağlantı almaz (`docs/105` §2.2(3)). Süzgeçten hiçbir şey geçmediyse
     bölüm HİÇ çizilmez — boş bir başlık, sayfayı uzatıp hiçbir soruya cevap
     vermeyen ince içeriktir. --}}
@if ($relatedLinks !== [])
    <section class="flex flex-col gap-3 border-t border-border pt-6">
        <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
        <ul class="flex flex-col gap-2">
            @foreach ($relatedLinks as $link)
                <li>
                    <a href="{{ $link['path'] }}" class="inline-flex min-h-[44px] items-center text-fg-link underline">{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
