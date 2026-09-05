{{-- SINIRLAMALAR — sayfanın en dürüst bölümü ve gizlenmez.

     Yönerge §1 madde 18: ürünün desteklemediği şey iddia edilmez. Bunu
     yalnız "yazmamak" yetmez; okuyan kişi eksik olanı SORAR ve cevabı
     sayfada bulamazsa varsayar. Bu yüzden eksikler açıkça yazılır. --}}
<section class="flex flex-col gap-3">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    <ul class="flex flex-col gap-4">
        @foreach ($block->entries as $entry)
            <li class="flex flex-col gap-1 border-t border-border pt-3">
                <span class="font-semibold text-fg">{{ $entry->term }}</span>
                <span class="leading-relaxed text-fg-secondary">{{ $entry->text }}</span>
            </li>
        @endforeach
    </ul>
</section>
