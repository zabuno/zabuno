{{-- GEREKSİNİM TABLOSU — yönerge §13.3.

     Gerçek bir `table`, biçimlendirilmiş bir liste değil: "neye ihtiyacım
     var" sorusunun cevabı iki sütunludur ve cevap sistemleri de tabloyu
     tablo olarak okur. Dar ekranda kendi içinde kayar; sayfa gövdesi yatay
     kaymaz. --}}
<section class="flex flex-col gap-3">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-start">
            <tbody>
                @foreach ($block->entries as $entry)
                    <tr class="border-t border-border align-top">
                        <th scope="row" class="py-3 pe-4 text-start font-semibold text-fg">{{ $entry->term }}</th>
                        <td class="py-3 leading-relaxed text-fg-secondary">{{ $entry->text }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
