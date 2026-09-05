{{-- SSS — açık soru-cevap yapısı (yönerge §13.3).

     Sorular GÖRÜNÜR başlıklardır, katlanmış değil: `FAQPage` işaretlemesi
     yalnız görünür içerik için üretilir (§14) ve gizlenmiş bir cevap
     ziyaretçi için de bir tık daha uzaktır. --}}
<section class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    @foreach ($block->entries as $entry)
        <div class="flex flex-col gap-1 border-t border-border pt-3">
            <h3 class="font-semibold text-fg">{{ $entry->term }}</h3>
            <p class="leading-relaxed text-fg-secondary">{{ $entry->text }}</p>
        </div>
    @endforeach
</section>
