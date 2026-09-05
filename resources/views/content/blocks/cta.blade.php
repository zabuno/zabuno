{{-- TEK birincil eylem. İkinci bir eşit ağırlıklı düğme, kararı böler.

     Hedef BUGÜN yayında olan bir yoldur (`/pricing`); yayınlanmamış bir
     sayfaya CTA vermek, ziyaretçiyi 404'e göndermek olurdu. --}}
<section class="flex flex-col items-start gap-3 border-t border-border pt-6">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    <p class="leading-relaxed text-fg-secondary">{{ $block->entries[0]->text }}</p>
    {{-- Dokunma hedefi 44 pikselin altına inmez; dar ekran tabandır. --}}
    <a href="{{ $block->entries[0]->href }}"
       class="inline-flex min-h-[44px] items-center rounded bg-action px-4 font-medium text-action-fg">{{ $block->entries[0]->term }}</a>
</section>
