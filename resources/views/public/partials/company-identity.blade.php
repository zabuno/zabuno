{{-- ŞİRKET KİMLİĞİNİN TEK ÇİZİMİ — FF-216, FF-240.

     `/about` ve `/contact` aynı olguları gösterir ve bu parçadan gösterir.
     İki sayfaya iki liste yazılsaydı, sahip bir alanı `.env`'e eklediğinde
     birinde görünür diğerinde görünmezdi — altbilgi ile üst çubuğun tek
     kaynaktan gelmesiyle aynı gerekçe (`SiteNavigation`).

     Satırlar `CompanyIdentity::rows()`ten gelir; alan → etiket eşlemesi
     ORADA yaşar. Bu şablonda tek bir sabit kullanıcı metni yok
     (I18N-SSR-RATCHET-16).

     GİRİLMEMİŞ ALAN ATLANMAZ, "girilmedi" diye YAZILIR: atlanan bir satır o
     alanın hiç istenmediği izlenimi verirdi; oysa hepsi kanunun saydığı
     alanlar. Uydurma bir değer ise sözleşmenin tarafını yanlış gösterirdi.

     ── DOKUNULABİLİR İKİ SATIR (FF-240) ─────────────────────────────────

     E-posta ve telefon artık `mailto:` / `tel:` bağlantısıdır ve hedefleri
     `--control-height` (44 piksel) taşır. Bir iletişim sayfasında en sık
     yapılan iş aramaktır; numarayı elle kopyalatmak onu iki adıma çıkarırdı.

     Bağlantı kararı ŞABLONDA VERİLMEZ, `CompanyIdentity::actionFor()`ta
     verilir: hangi alanın çevrilebilir olduğu bir veri sorusudur, bir çizim
     sorusu değil. Girilmemiş ya da çevrilemez bir değer düz metin kalır —
     hiçbir yere gitmeyen bir bağlantı, olmayan bir bağlantıdan kötüdür. --}}
<dl class="site-company-identity" data-company-identity="{{ $companyComplete ? 'complete' : 'incomplete' }}">
    @foreach ($companyRows as $row)
        <div data-company-field="{{ $row['field'] }}">
            <dt>{{ $row['label'] }}</dt>
            @if ($row['value'] === null)
                <dd data-missing="true">{{ $st['companyValueMissing'] }}</dd>
            @elseif ($row['href'] !== null)
                <dd>
                    <a href="{{ $row['href'] }}" class="site-company-identity-action">{{ $row['value'] }}</a>
                </dd>
            @else
                <dd>{{ $row['value'] }}</dd>
            @endif
        </div>
    @endforeach
</dl>
