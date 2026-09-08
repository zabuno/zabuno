{{-- SINIRLAMALAR — sayfanın en dürüst bölümü ve gizlenmez.

     Yönerge §1 madde 18: ürünün desteklemediği şey iddia edilmez. Bunu
     yalnız "yazmamak" yetmez; okuyan kişi eksik olanı SORAR ve cevabı
     sayfada bulamazsa varsayar — genellikle yanlış varsayar. Bu yüzden
     eksikler açıkça yazılır.

     İKİ KOLAY HATADAN DA KAÇINILDI:

     (1) GİZLEMEK. Bölüm katlanmaz, sona sürülmez, küçük punto almaz. Bir
         tık ardına konan dürüstlük dürüstlük değildir (CONTENT-TEMPLATE-05).

     (2) ALARM ÇALMAK. Kırmızı bir kutu "burada bir arıza var" der; oysa
         burada yazan şey ürünün bilinçli kapsamıdır. Bölüm hata/uyarı
         jetonlarına hiç dokunmaz (CONTENT-TEMPLATE-06); ayrımını çukur
         yüzey ve kenarlık kurar — göz onu ayırır ama irkilmez.

     Satır başındaki kısa çizgi CSS'te çiziliyor. Bir ikon değil, bir
     ARİTMETİK işareti: burada bir şey çıkarılıyor. --}}
<section class="site-doc-block site-doc-limits" data-block="limitations">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <ul class="site-doc-limits-list" role="list">
        @foreach ($block->entries as $entry)
            <li class="site-doc-limits-item">
                <span class="site-doc-limits-term">{{ $entry->term }}</span>
                <span class="site-doc-limits-text">{{ $entry->text }}</span>
            </li>
        @endforeach
    </ul>
</section>
