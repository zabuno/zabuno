{{-- ŞİRKET KİMLİĞİNİN TEK ÇİZİMİ — FF-216.

     `/about` ve `/contact` aynı olguları gösterir ve bu parçadan gösterir.
     İki sayfaya iki liste yazılsaydı, sahip bir alanı `.env`'e eklediğinde
     birinde görünür diğerinde görünmezdi — altbilgi ile üst çubuğun tek
     kaynaktan gelmesiyle aynı gerekçe (`SiteNavigation`).

     Satırlar `CompanyIdentity::rows()`ten gelir; alan → etiket eşlemesi
     ORADA yaşar. Bu şablonda tek bir sabit kullanıcı metni yok
     (I18N-SSR-RATCHET-16).

     GİRİLMEMİŞ ALAN ATLANMAZ, "girilmedi" diye YAZILIR: atlanan bir satır o
     alanın hiç istenmediği izlenimi verirdi; oysa hepsi kanunun saydığı
     alanlar. Uydurma bir değer ise sözleşmenin tarafını yanlış gösterirdi. --}}
<dl class="site-company-identity" data-company-identity="{{ $companyComplete ? 'complete' : 'incomplete' }}">
    @foreach ($companyRows as $row)
        <div data-company-field="{{ $row['field'] }}">
            <dt>{{ $row['label'] }}</dt>
            @if ($row['value'] === null)
                <dd data-missing="true">{{ $st['companyValueMissing'] }}</dd>
            @else
                <dd>{{ $row['value'] }}</dd>
            @endif
        </div>
    @endforeach
</dl>
