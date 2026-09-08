{{-- GEREKSİNİM TABLOSU — yönerge §13.3.

     Gerçek bir `table`, biçimlendirilmiş bir liste değil: "neye ihtiyacım
     var" sorusunun cevabı iki sütunludur ve cevap sistemleri de tabloyu
     tablo olarak okur.

     ÇİZİM DEĞİŞTİ, ANLAM DEĞİŞMEDİ. Ölçüldü (320×568): iki kolon olarak
     çizildiğinde etiket 110, değer 170 piksele düşüyordu — satır başına ~19
     karakter. Satır artık bir ızgara ve dar ekranda etiket değerin ÜSTÜNE
     geçiyor; ikisi de ekranın tamamını kullanıyor. Yatay kaydırma kabı da
     kalktı, çünkü kaydıracak bir şey kalmadı.

     Roller AÇIKÇA yazılı: `display` değiştiği an tarayıcı tablo rollerini
     düşürür ve bunu fark etmek zordur, çünkü ekranda hiçbir şey olmaz.
     Yazmasaydık yukarıdaki "tabloyu tablo olarak okur" cümlesi sessizce
     yalan olurdu (CONTENT-TEMPLATE-04). --}}
<section class="site-doc-block" data-block="requirements">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <table class="site-doc-table" role="table">
        <tbody role="rowgroup">
            @foreach ($block->entries as $entry)
                <tr class="site-doc-table-row" role="row">
                    <th scope="row" role="rowheader" class="site-doc-table-term">{{ $entry->term }}</th>
                    <td role="cell" class="site-doc-table-text">{{ $entry->text }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
