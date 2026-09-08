@extends('public.layout')

{{--
    FİYAT SAYFASI — `docs/107` Faz 2.7, `docs/139` (FF-239).

    ═══ KİME YAZILDI ═══

    Teknoloji bilmeyen, acelesi olan, TELEFONDAN bakan bir restoran sahibi.
    Fiyat sayfası satın alma kararının verildiği yerdir ve tek bir kural onu
    yönetir: **hangi planı alacağını anlayamayan kimse almaz.**

    ═══ ÖNCEKİ HÂLİ ═══

    Sayfa plan kataloğunu okuyordu (`docs/88`) ama gövdesi bir bölümden
    ibaretti: ad, tutar ve "Adds" listesi. Üç soru cevapsızdı ve üçü de satın
    alma kararının kendisidir:

      1. "Bunlardan hangisi BENİM?" — kademe adı bunu söylemez.
      2. "Parasını ödeyince masada ne DEĞİŞMEYECEK?" — tik dolu bir tablo
         yalnız pahalı sütunun neye sahip olduğunu söyler.
      3. "Çıkmak istersem ne olur?" — cevabı sözleşmenin içindeydi, yani
         ödeme adımından sonra öğrenilirdi.

    ═══ RAKAM YAZILMADI ═══

    Bu şablonda bir tek tutar, bir tek plan adı ve bir tek hak adı yoktur.
    Hepsi `plans` tablosundan gelir (`FoundationStatusController`) ve sahibin
    panelden yaptığı düzenleme sayfayı kendiliğinden doğru tutar. Elle yazmak,
    fiyat değiştiği gün ikinci bir gerçek kaynak yaratırdı — ve ikisi
    ayrıştığında hangisinin doğru olduğunu kimse bilemezdi.

    ═══ ANA SAYFANIN BÖLÜMÜ AYRI DURUYOR ═══

    Ana sayfa `public.partials.pricing` bölümünü giyer ve o bölüm bir ÖZETTİR.
    Bu sayfa onu içermez; sayfanın kendi gövdesi burada. İkisi aynı veriyi
    (`$plans`) okur, yani rakamlar tek kaynaktan gelmeye devam eder; ayrı olan
    yalnız anlatım derinliği. Özeti bu sayfanın ihtiyaçlarına göre şişirmek,
    ana sayfayı da onunla birlikte şişirirdi.

    ═══ 320 PİKSEL ═══

    Fiyat tablosu dar ekranda en zor çizilen şeydir. Burada TABLO YOK: planlar
    tek sütunlu bir KART YIĞINI, ekran genişledikçe `auto-fit` çoğaltır
    (`site-pricing.css`). Yatay kaydırılan bir fiyat tablosu, ikinci sütununu
    kimsenin görmediği bir tablodur. SSS `<details>` içinde ve kapalı başlar:
    yedi soru açık hâlde fiyatın altını bir duvara çevirirdi.

    Yazım sırası da mobil: `max-*` bastırma yok, "mobilde gizle" yok, tek kod
    yolu. Kırılma noktası sınıfı hiç yok (`MP-05`).
--}}
@section('title', $st['pricingHeading'])
@section('description', $st['pricingLead'])

@section('content')
    <main id="main-content" class="site-shell-inner site-pricing">
        <div class="site-pricing-section">
            <h1 class="text-3xl font-bold">{{ $st['pricingHeading'] }}</h1>
            <p class="text-fg-secondary">{{ $st['pricingLead'] }}</p>
        </div>

        @if (empty($plans))
            {{--
                Boş bir fiyat tablosu, ziyaretçiye "bu ürün hazır değil"
                dedirtir. Sayfa DURUMU söyler ve bir ÇIKIŞ YOLU bırakır: boş
                bir hâl bir hata değildir, ama bir çıkmaz da olmamalıdır
                (`docs/66`). Katalog okunamadığında da buraya düşülür ve bir
                test bunu donduruyor (PUBLIC-PRICING-SURVIVES-CATALOG-FAILURE-01).

                Boş hâlde AŞAĞISI HİÇ ÇİZİLMEZ: fiyatı olmayan bir sayfada
                "ne dahil değil" ve "iptal nasıl olur" okumak, olmayan bir
                şeyin şartlarını okumaktır.
            --}}
            <p class="site-pricing-note">
                {{ $st['pricingEmpty'] }}
                <a class="site-pricing-link" href="/contact">{{ $st['pricingEmptyCta'] }}</a>
            </p>
        @else
            {{--
                HER PLANDA OLAN, BİR KEZ söylenir ve planlardan ÖNCE gelir.

                Yetenek listesi EK yetkileri anlatır, temel zinciri değil.
                Yalnız onları göstermek, ücretsiz kademeyi "hiçbir şey
                içermiyor" gibi gösterirdi — oysa menü, yayın, karekod ve
                misafir sayfası her planda var (`docs/90`).
            --}}
            <section class="site-pricing-section" aria-labelledby="pricing-included-heading">
                <h2 id="pricing-included-heading" class="text-2xl font-bold">{{ $st['includedHeading'] }}</h2>
                <p class="text-fg-secondary">{{ $st['includedBody'] }}</p>
            </section>

            <section class="site-pricing-section" aria-labelledby="pricing-plans-heading">
                {{-- Başlık ekran okuyucu için ŞART: üç kartın hangi bölüme ait
                     olduğunu söyleyen tek şey bu. Görünürdür, çünkü gizli bir
                     başlık gören kullanıcıya aynı hizmeti vermez. --}}
                <h2 id="pricing-plans-heading" class="text-2xl font-bold">{{ $st['pricingPlansHeading'] }}</h2>

                <ul class="site-pricing-plans">
                    @foreach ($plans as $plan)
                        <li class="dz-card site-pricing-plan" data-plan-card>
                            <div class="dz-card-body site-pricing-plan-body">
                                <h3 class="dz-card-title">{{ $plan['name'] }}</h3>

                                @if (! empty($plan['free']))
                                    {{-- `0,00 TRY` teknik olarak doğru ama insan
                                         onu "ücretsiz" diye okumaz, bir hata
                                         sanır (`docs/90`). --}}
                                    <p class="site-pricing-amount">{{ $st['free'] }}</p>
                                @elseif ($plan['price'] === null)
                                    {{-- Tutarı girilmemiş bir planı "0" ya da
                                         "ücretsiz" göstermek, tutulmayacak bir
                                         söz vermek olurdu. --}}
                                    <p class="site-pricing-note">
                                        {{ $st['perRestaurant'] }}
                                        <a class="site-pricing-link" href="/contact">{{ $st['perRestaurantCta'] }}</a>
                                    </p>
                                @else
                                    <p class="site-pricing-amount">
                                        {{ $plan['price'] }}
                                        <span class="site-pricing-period">{{ $st['perMonth'] }}</span>
                                    </p>
                                @endif

                                @if (! empty($plan['audience']))
                                    {{--
                                        KİME UYGUN — "Pro" bir şey anlatmaz.

                                        Cümle katalogdan gelir ve plan KODUNA
                                        bağlıdır; tanınmayan bir kod hiç cümle
                                        üretmez. Sahibin panelden açtığı yeni
                                        bir plana uydurulmuş bir kitle
                                        yakıştırmak, bu satırın engellemek için
                                        var olduğu şey olurdu (`docs/139`).
                                    --}}
                                    <p class="site-pricing-label">{{ $st['audienceLabel'] }}</p>
                                    <p class="text-fg-secondary">{{ $plan['audience'] }}</p>
                                @endif

                                @if (! empty($plan['entitlements']))
                                    <p class="site-pricing-label">{{ $st['adds'] }}</p>
                                    <ul class="site-pricing-list">
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
                    <a class="site-pricing-link" href="/contact">{{ $st['unsureCta'] }}</a>
                </p>
            </section>

            {{--
                NE DAHİL DEĞİL — ve bu bir kademe farkı değil.

                Bir fiyat sayfasının en pahalı sessizliği burasıdır: tik dolu
                bir tablo, parası ödendikten sonra masada hâlâ olmayacak şeyi
                söylemez. Altı satırın altısı da ölçülmüş bir yokluk ve dili
                sipariş sayfasıyla (`OrderingPage`) ve kurumsal fiyat
                sayfasıyla (`PricingPage`) ORTAK — aynı yokluğu iki yüzeyde
                iki ayrı cümleyle anlatmak, bir gün hangisinin doğru olduğunu
                bilinmez yapardı (`docs/137` §2a).
            --}}
            <section class="site-pricing-section" aria-labelledby="pricing-excluded-heading">
                <h2 id="pricing-excluded-heading" class="text-2xl font-bold">{{ $st['excludedHeading'] }}</h2>
                <p class="text-fg-secondary">{{ $st['excludedLead'] }}</p>
                <ul class="site-pricing-list" data-pricing-excluded>
                    <li>{{ $st['excludedPayment'] }}</li>
                    <li>{{ $st['excludedPos'] }}</li>
                    <li>{{ $st['excludedDelivery'] }}</li>
                    <li>{{ $st['excludedKitchenHardware'] }}</li>
                    <li>{{ $st['excludedCampaign'] }}</li>
                    <li>{{ $st['excludedCurrency'] }}</li>
                </ul>
            </section>

            {{--
                SSS — SORULAR UYDURULMADI.

                Üç kaynak: yardım makalesi (basılı kodun ölmemesi), ürünün
                "ne değildir" listeleri (deneme süresi, şube/kişi başı fiyat)
                ve yayınlanmış yasal metin (iptal, yürürlük, iade, plan
                değiştirme, ödemesiz süre). Destek talebi yüzeyi BİLEREK
                kullanılmadı: `support_requests` bir konu taksonomisi
                taşımıyor ve depoda gerçek bir talep kütüğü yok — oradan soru
                "türetmek", uydurmanın kaynak göstermiş hâli olurdu
                (`docs/139` §4).

                `<details>` BETİKSİZ çalışır ve her cevap HTML'de zaten durur;
                arama motoru ve JavaScript çalıştırmayan bot için gövde budur
                (`docs/118` E2).
            --}}
            <section class="site-pricing-section" aria-labelledby="pricing-faq-heading">
                <h2 id="pricing-faq-heading" class="text-2xl font-bold">{{ $st['pricingFaqHeading'] }}</h2>

                @foreach ([
                    ['q' => $st['pricingFaqStopQuestion'], 'a' => $st['pricingFaqStopAnswer']],
                    ['q' => $st['pricingFaqCancelQuestion'], 'a' => $st['pricingFaqCancelAnswer']],
                    ['q' => $st['pricingFaqRefundQuestion'], 'a' => $st['pricingFaqRefundAnswer']],
                    ['q' => $st['pricingFaqChangeQuestion'], 'a' => $st['pricingFaqChangeAnswer']],
                    ['q' => $st['pricingFaqTrialQuestion'], 'a' => $st['pricingFaqTrialAnswer']],
                    ['q' => $st['pricingFaqBranchQuestion'], 'a' => $st['pricingFaqBranchAnswer']],
                    ['q' => $st['pricingFaqReprintQuestion'], 'a' => $st['pricingFaqReprintAnswer']],
                ] as $entry)
                    <details class="dz-collapse dz-collapse-arrow site-pricing-faq-item" data-pricing-faq>
                        <summary class="dz-collapse-title site-pricing-faq-question">{{ $entry['q'] }}</summary>
                        <div class="dz-collapse-content site-pricing-faq-answer">
                            <p>{{ $entry['a'] }}</p>
                        </div>
                    </details>
                @endforeach
            </section>

            {{--
                ÖDEME VE ÇIKIŞ YOLU, FİYATIN YANINDA (FF-216 ile aynı gerekçe).

                "Nasıl ödeyeceğim?" ve "çıkmak istersem ne olur?" soruları tam
                burada sorulur; cevabı sözleşmenin içinde bırakmak, ikisini de
                ödeme adımından SONRA öğrenmek demekti.

                BANKA YA DA KART LOGOSU YOK: hangi kartların kabul edildiği
                ödeme sağlayıcısının kendi yapılandırmasından türer ve bu
                depoda öyle bir liste yapılandırılmamıştır. Uydurulmuş bir
                logo, kabul edilmeyen bir kartı kabul ediliyor göstermek
                olurdu.

                İki satır da yasal metnin YERİNE GEÇMEZ, ona götürür.
            --}}
            <section class="site-pricing-section" aria-labelledby="pricing-terms-heading">
                <h2 id="pricing-terms-heading" class="text-2xl font-bold">{{ $st['pricingTermsHeading'] }}</h2>

                <div class="site-pricing-note" data-payment-methods>
                    <p>{{ $st['paymentMethods'] }}</p>
                    <a class="site-pricing-link" href="/pre-information">{{ $st['paymentMethodsCta'] }}</a>
                </div>

                <div class="site-pricing-note" data-cancellation>
                    <p>{{ $st['cancellation'] }}</p>
                    <a class="site-pricing-link" href="/refund-policy">{{ $st['cancellationCta'] }}</a>
                </div>
            </section>
        @endif
    </main>
@endsection
