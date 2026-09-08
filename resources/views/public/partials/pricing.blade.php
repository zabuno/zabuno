{{--
    FİYAT VERİDİR, KOD DEĞİL — `docs/88` (P1-01).

    Bu bölüm plan kataloğundan okur. Rakamı sahibi girer; sayfaya elle
    yazmak, fiyat değiştiği gün ikinci bir gerçek kaynak yaratır ve ikisi
    ayrıştığında hangisinin doğru olduğunu kimse bilemez.

    Metin de şablonda DEĞİL katalogda yaşar (`docs/85` ile aynı gerekçe).
    Bu paket TEK BİR yeni görünür dize yazmadı: `lang/untranslatable-debt.json`
    hâlâ sıfır.

    ── DÖNGÜ 2: KURUMSAL YÜZEY DİLİ (`docs/146` §9 madde 2) ──────────────

    Döngü 1'in kendi eksik listesi bu dosyayı adıyla suçluyordu: *"bugünkü
    hâli 'sade' değil FAKİR: partial'ın kutuları kurumsal yüzey dilini
    (`site-panel`) hiç kullanmıyor."*

    Suçlama doğruydu ve sebebi ölçülebilir: kutular `rounded-lg border
    border-border p-4` ile çiziliyordu — yani panelin Tailwind yardımcı
    sınıflarıyla, sitenin KENDİ jetonlarıyla değil. Aynı sayfada iki farklı
    yüzey dili vardı: kahraman bir uzay sahnesi, fiyat bir yönetim paneli
    formu. Ziyaretçi bunu "iki ayrı ürün" diye okur.

    Artık kutular `site-panel` (parıltı + kenar + renkli gölge) ve ölçüler
    `--zc-*` jetonlarından geliyor. Ham renk, ham boşluk ve ham yarıçap YOK.

    ── SAHNE HÂLÂ SUSUYOR ────────────────────────────────────────────────

    Kurumsal YÜZEY dili ile HAREKET ayrı şeylerdir (`site-identity.css` §6:
    *"Hiçbiri hareket etmez."*). Burada tuval, yörünge, veri hattı ya da
    parallax yok ve olmayacak: bir fiyatın okunduğu yer, dikkatin
    bölünmemesi gereken yerdir. Değişen şey kutuların KALINLIĞI, hareketi
    değil.

    ── DEĞİŞKENLER ───────────────────────────────────────────────────────

    `$pricingHeadingTag`   — `h2` (ana sayfada) ya da `'none'`. `'none'`, başlığın
                             ÇAĞIRANDA olduğunu söyler: `/pricing` başlığı
                             önsöz bandında `h1` olarak basar ve bu bölüm onu
                             tekrar etmez. Aynı sözcüğü alt alta iki kez
                             yazmak, Döngü 1'in göz izinde ölçtüğü kusurun ta
                             kendisiydi (`docs/146` §5).
    `$pricingLabelledBy`   — bölümü adlandıran başlığın `id`si.
    `$pricingLead`         — isteğe bağlı giriş cümlesi.
    `$pricingHeadingClass` — çağıran bir ölçek dayatmaz; başlık ölçeği
                             etikete göre KURUMSAL ölçekten seçilir.
    `$pricingShowAudience`  — kartın "kime uygun" cümlesini basar mı
                             (`docs/139`). Ana sayfada `false`: orada bölüm
                             bir ÖZETTİR ve üç kartın her birine bir cümle
                             daha eklemek, özeti sayfanın kendisine
                             çevirirdi. `/pricing` `true` geçer, çünkü karar
                             orada veriliyor ve "Pro" bir şey anlatmaz.
--}}
@php
    /* Bu bölüm hem ana sayfada (bir alt başlık olarak) hem de kendi
       sayfasında (SAYFANIN başlığı olarak) görünür. Etiket çağırana
       bırakılır: `/pricing` üzerinde ayrı bir `<h1>` basmak "Pricing"i üst
       üste iki kez yazardı ve ekran okuyucuda iki ayrı bölüm gibi okunurdu
       (`docs/89`). */
    $pricingHeadingTag = $pricingHeadingTag ?? 'h2';
    $pricingLabelledBy = $pricingLabelledBy ?? 'pricing-heading';

    /* Ölçek KURUMSAL tipografiden (`site-identity.css` §3), Tailwind'in
       `text-3xl`inden değil: iki ölçek bir arada, aynı sayfada iki farklı
       başlık boyu üretiyordu. */
    $pricingHeadingClass = $pricingHeadingTag === 'h1' ? 'site-display-2' : 'site-display-3';

    /* Giriş cümlesi BAŞLIĞIN ALTINDA durur. Sayfa başlığı parçaya
       devredilince cümle yukarıda kalmıştı: okuyucu neyin açıklamasını
       okuduğunu, ancak sonraki satırda öğreniyordu. */
    $pricingLead = $pricingLead ?? null;

    /* Kitle cümlesi VARSAYILAN OLARAK SUSAR. Bir bayrağın açık doğması,
       bölümü giyen her yeni sayfanın onu istemeden basması demekti; kapalı
       doğması ise en fazla bir cümlenin görünmemesi. İkisinin bedeli eşit
       değil. */
    $pricingShowAudience = $pricingShowAudience ?? false;
@endphp
<section id="pricing" aria-labelledby="{{ $pricingLabelledBy }}" class="site-pricing">
    @if ($pricingHeadingTag !== 'none')
        <div class="site-pricing-head">
            <{{ $pricingHeadingTag }} id="pricing-heading" class="{{ $pricingHeadingClass }}">{{ $st['pricingHeading'] }}</{{ $pricingHeadingTag }}>

            @if ($pricingLead)
                <p class="site-lede">{{ $pricingLead }}</p>
            @endif
        </div>
    @endif

    @if (empty($plans))
        {{--
            Boş bir fiyat tablosu, ziyaretçiye "bu ürün hazır değil"
            dedirtir. Sayfa DURUMU söyler ve bir ÇIKIŞ YOLU bırakır: boş bir
            hâl bir hata değildir, ama bir çıkmaz da olmamalıdır (`docs/66`).
        --}}
        <p class="site-pricing-note">
            {{ $st['pricingEmpty'] }}
            <a class="site-inline-action" href="/contact">{{ $st['pricingEmptyCta'] }}</a>
        </p>
    @else
        {{--
            HER PLANDA OLAN, bir kez söylenir.

            Yetenek listesi EK yetkileri anlatır; temel zinciri değil. Yalnız
            onları göstermek, ücretsiz kademeyi "hiçbir şey içermiyor" gibi
            gösterirdi — oysa menü, yayın, karekod ve misafir sayfası her
            planda var (`docs/90`).

            Kutu `site-panel` DEĞİL `site-rule`: bu bir plan değil, bütün
            planların ALTINDAKİ zemin. Aynı kalınlıkta çizilseydi beşinci bir
            plan gibi okunurdu.
        --}}
        <div class="site-pricing-included">
            <p class="site-eyebrow">{{ $st['includedHeading'] }}</p>
            <p class="site-pricing-note">{{ $st['includedBody'] }}</p>
        </div>

        {{-- Izgara: 320 pikselde tek sütun, geniş ekranda sığdığı kadar.
             64rem üzerinde ortak satırlar fiyat karşılaştırmasını hizalar. --}}
        <ul class="site-pricing-grid">
            @foreach ($plans as $plan)
                <li class="site-panel site-lit site-pricing-plan">
                    <span class="site-pricing-plan-name">{{ $plan['name'] }}</span>

                    <div class="site-pricing-price-block">
                    @if (! empty($plan['free']))
                        {{-- `0,00 TRY` teknik olarak doğru ama insan onu
                             "ücretsiz" diye okumaz, bir hata sanır. --}}
                        <span class="site-pricing-amount">{{ $st['free'] }}</span>
                    @elseif ($plan['price'] === null)
                        {{--
                            Tutarı girilmemiş bir planı "0" ya da "ücretsiz"
                            göstermek, tutulmayacak bir söz vermek olurdu.
                        --}}
                        <span class="site-pricing-note">
                            {{ $st['perRestaurant'] }}
                            <a class="site-inline-action" href="/contact">{{ $st['perRestaurantCta'] }}</a>
                        </span>
                    @else
                        {{-- `tabular-nums`: rakamlar eşit genişlikte olmazsa
                             planlar arasında fiyat karşılaştırması gözle
                             yapılamaz. --}}
                        <span class="site-pricing-amount">
                            <span class="tabular-nums">{{ $plan['price'] }}</span>
                            <span class="site-pricing-period">{{ $st['perMonth'] }}</span>
                        </span>
                    @endif

                    </div>

                    <div class="site-pricing-audience-block">
                    @if ($pricingShowAudience && ! empty($plan['audience']))
                        {{--
                            KİME UYGUN — "Pro" bir şey anlatmaz (`docs/139`).

                            Cümle katalogdan gelir ve plan KODUNA bağlıdır;
                            tanınmayan bir kod hiç cümle üretmez. Sahibin
                            panelden açtığı yeni bir plana uydurulmuş bir
                            kitle yakıştırmak, bu satırın engellemek için var
                            olduğu şey olurdu.
                        --}}
                        <p class="site-eyebrow">{{ $st['audienceLabel'] }}</p>
                        <p class="site-pricing-note">{{ $plan['audience'] }}</p>
                    @endif

                    </div>

                    <div class="site-pricing-features-block">
                    @if (! empty($plan['entitlements']))
                        <p class="site-eyebrow">{{ $st['adds'] }}</p>
                        <ul class="site-pricing-entitlements">
                            @foreach ($plan['entitlements'] as $entitlement)
                                <li>{{ $entitlement }}</li>
                            @endforeach
                        </ul>
                    @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="site-pricing-note">
            {{ $st['unsure'] }}
            <a class="site-inline-action" href="/contact">{{ $st['unsureCta'] }}</a>
        </p>

        {{-- KABUL EDİLEN ÖDEME YÖNTEMİ, FİYATIN YANINDA (FF-216).

             Fiyatı okuyan kişi "nasıl ödeyeceğim?" sorusunu tam orada
             sorar; cevabı sözleşmenin on birinci bölümünde bırakmak, onu
             ödeme adımında öğrenmesi demekti.

             BANKA YA DA KART LOGOSU YOK: hangi kartların kabul edildiği
             ödeme sağlayıcısının kendi yapılandırmasından türer ve bu
             depoda öyle bir liste yapılandırılmamıştır. Sağlayıcının adı
             ise ölçülmüş bir olgudur; uydurulmuş bir logo ise kabul
             edilmeyen bir kartı kabul ediliyor göstermek olurdu. --}}
        <div class="site-pricing-payment" data-payment-methods>
            <p>{{ $st['paymentMethods'] }}</p>
            {{-- Bağlantı CÜMLENİN İÇİNDE değil, kendi satırında ve 44
                 piksel: satır içi bir bağlantı dar ekranda 18-42 piksel
                 yüksekliğinde kalıyor ve parmakla ıskalanıyor (`docs/117`).
                 Bu sayfadaki eski satır içi bağlantılar #279'un borcuydu ve
                 o borç Döngü 3'te kapandı (`.site-inline-action`, `docs/146`
                 §12.8): satır içi kalan üç bağlantı da artık 44 piksel. --}}
            <a class="site-action self-start underline underline-offset-2"
               href="/pre-information">{{ $st['paymentMethodsCta'] }}</a>
        </div>
    @endif
</section>
