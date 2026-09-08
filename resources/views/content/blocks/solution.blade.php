{{-- ZABUNO ÇÖZÜMÜ — anlatının ikinci yarısı.

     Problemle aynı biçim, farklı ton: tam mürekkep ve başlığın altında kısa
     bir marka çizgisi. Sayfada "burada bir şey değişiyor" diyen tek işaret
     budur ve bir kutuya ihtiyaç duymaz — dar ekranda bir kutu iki yanından
     32 piksel yer, bir ton sıfır.

     Probleme yakın durur (`--space-fluid-md`, bölüm aralığı değil): birbirine
     yakın duran iki şey, aynı şeyin parçası olarak okunur. --}}
<section class="site-doc-block site-doc-prose" data-block="solution">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    @foreach ($block->entries as $entry)
        <p class="site-doc-paragraph">{{ $entry->text }}</p>
    @endforeach
</section>
