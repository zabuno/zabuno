{{-- ADIM LİSTESİ — yönerge §13.3.

     Gerçekten SIRALI bir liste: adımların sırası bilginin kendisidir ve
     `ol` bunu ekran okuyucuya da söyler. Numarayı elle yazmak, listeyi
     yeniden sıralayan kişinin numaraları güncellemeyi unutmasına açık
     olurdu. --}}
<section class="flex flex-col gap-3">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    <ol class="flex list-decimal flex-col gap-3 ps-5">
        @foreach ($block->entries as $entry)
            <li class="leading-relaxed text-fg-secondary">
                <strong class="font-semibold text-fg">{{ $entry->term }}</strong>
                <span>{{ $entry->text }}</span>
            </li>
        @endforeach
    </ol>
</section>
