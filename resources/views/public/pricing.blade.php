@extends('public.layout')

{{--
    FİYAT SAYFASI — `docs/107` Faz 2.7, `docs/139` (FF-239), `docs/146` §10.

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

    ═══ PLAN KARTLARI ORTAK BÖLÜMDEN GELİR ═══

    Kartların kendisi `public.partials.pricing` içinde yaşar ve ana sayfa da
    aynı bölümü giyer. Bu sayfa onu KOPYALAMAZ, `pricingShowAudience` ile
    derinleştirir: karar burada veriliyor, dolayısıyla "kime uygun" cümlesi
    yalnız burada basılır. İki ayrı kart çizimi yazmak, bir gün ikisinin
    ayrışmasıyla biterdi.

    ═══ SAHNE, BANDIN İÇİNDE KALIR (`docs/146` §10) ═══

    Başlık ve giriş cümlesi ÖNSÖZ bandında; band `grid` (tel kafes) yüzünü
    taşıyor, çünkü fiyat bir ZEMİN sorusudur ("bu ne kadar tutar"), yörünge
    ya da veri hattı değil. Bandın ALTINDA hareket YOK: rakamın, yokluk
    listesinin ve SSS'in okunduğu yer, dikkatin bölünmemesi gereken yerdir.
    Bu yüzden gövdede `scene-reveal` hiç yazmıyor — eksiklik değil, karar.

    ═══ 320 PİKSEL ═══

    Fiyat tablosu dar ekranda en zor çizilen şeydir. Burada TABLO YOK: planlar
    tek sütunlu bir KART YIĞINI, ekran genişledikçe `auto-fit` çoğaltır
    (`site-pages.css` §3). Yatay kaydırılan bir fiyat tablosu, ikinci sütununu
    kimsenin görmediği bir tablodur. SSS `<details>` içinde ve kapalı başlar:
    yedi soru açık hâlde fiyatın altını bir duvara çevirirdi.

    Yazım sırası da mobil: `max-*` bastırma yok, "mobilde gizle" yok, tek kod
    yolu. Kırılma noktası sınıfı hiç yok (`MP-05`).

    Sekme ve paylaşım adı ayrıca yazılır: bu sayfa tam olarak PAYLAŞILMAK için
    var — fiyatı biri arkadaşına gönderir ve "— Zabuno" hangi sayfa olduğunu
    söylemez (`docs/89`).
--}}
@section('title', $st['pricingHeading'])
@section('description', $st['pricingLead'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['pricingHeading'],
            'prologueLead' => $st['pricingLead'],
            'prologueVariant' => 'grid',
            'prologueId' => 'pricing-heading',
        ])

        {{-- BAŞLIK ÖNSÖZDE, GÖVDEDE DEĞİL.

             Parça kendi `h1`ini basıyordu; artık `'none'` ile susuyor ve
             bölüm `aria-labelledby` ile bandın başlığına işaret ediyor. Aynı
             sözcüğü alt alta iki kez yazmak, Döngü 1'in göz izinde ölçtüğü
             kusurun ta kendisiydi (`docs/146` §5) — ve ekran okuyucuda iki
             ayrı bölüm gibi okunurdu (`docs/89`). --}}
        <div class="site-measure-page site-page-body">
            @include('public.partials.pricing', [
                'pricingHeadingTag' => 'none',
                'pricingShowAudience' => true,
            ])

            {{--
                BOŞ KATALOGDA AŞAĞISI HİÇ ÇİZİLMEZ.

                Bölümün kendisi boş hâli zaten söylüyor ve bir çıkış yolu
                bırakıyor (`docs/66`). Fiyatı olmayan bir sayfada "ne dahil
                değil" ve "iptal nasıl olur" okumak, olmayan bir şeyin
                şartlarını okumaktır. Katalog okunamadığında da buraya
                düşülür (PUBLIC-PRICING-SURVIVES-CATALOG-FAILURE-01).
            --}}
            @if (! empty($plans))
                {{--
                    NE DAHİL DEĞİL — ve bu bir kademe farkı değil.

                    Bir fiyat sayfasının en pahalı sessizliği burasıdır: tik
                    dolu bir tablo, parası ödendikten sonra masada hâlâ
                    olmayacak şeyi söylemez. Altı satırın altısı da ölçülmüş
                    bir yokluk ve dili sipariş sayfasıyla (`OrderingPage`) ve
                    kurumsal fiyat sayfasıyla (`PricingPage`) ORTAK — aynı
                    yokluğu iki yüzeyde iki ayrı cümleyle anlatmak, bir gün
                    hangisinin doğru olduğunu bilinmez yapardı (`docs/137`
                    §2a).

                    Başlık "hiçbir plan" diyor, "ucuz plan" değil.
                --}}
                <section class="site-pricing" aria-labelledby="pricing-excluded-heading">
                    <div class="site-pricing-head">
                        <h2 id="pricing-excluded-heading" class="site-display-3">{{ $st['excludedHeading'] }}</h2>
                        <p class="site-lede">{{ $st['excludedLead'] }}</p>
                    </div>

                    <ul class="site-pricing-absences" data-pricing-excluded>
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
                    "ne değildir" listeleri (deneme süresi, şube/kişi başı
                    fiyat) ve YAYINLANMIŞ yasal metin (iptal, yürürlük, iade,
                    plan değiştirme, ödemesiz süre). Destek talebi yüzeyi
                    BİLEREK kullanılmadı: `support_requests` bir konu
                    taksonomisi taşımıyor ve depoda gerçek bir talep kütüğü
                    yok — oradan soru "türetmek", uydurmanın kaynak göstermiş
                    hâli olurdu (`docs/139` §4).

                    `<details>` BETİKSİZ çalışır ve her cevap HTML'de zaten
                    durur; arama motoru ve JavaScript çalıştırmayan bot için
                    gövde budur (`docs/118` E2). Ana sayfanın SSS'iyle aynı
                    karar, aynı gerekçe.
                --}}
                <section class="site-pricing" aria-labelledby="pricing-faq-heading">
                    <div class="site-pricing-head">
                        <h2 id="pricing-faq-heading" class="site-display-3">{{ $st['pricingFaqHeading'] }}</h2>
                    </div>

                    <div class="site-pricing-faq">
                        @foreach ([
                            ['q' => $st['pricingFaqStopQuestion'], 'a' => $st['pricingFaqStopAnswer']],
                            ['q' => $st['pricingFaqCancelQuestion'], 'a' => $st['pricingFaqCancelAnswer']],
                            ['q' => $st['pricingFaqRefundQuestion'], 'a' => $st['pricingFaqRefundAnswer']],
                            ['q' => $st['pricingFaqChangeQuestion'], 'a' => $st['pricingFaqChangeAnswer']],
                            ['q' => $st['pricingFaqTrialQuestion'], 'a' => $st['pricingFaqTrialAnswer']],
                            ['q' => $st['pricingFaqBranchQuestion'], 'a' => $st['pricingFaqBranchAnswer']],
                            ['q' => $st['pricingFaqReprintQuestion'], 'a' => $st['pricingFaqReprintAnswer']],
                        ] as $entry)
                            <details class="site-panel site-pricing-faq-item" data-pricing-faq>
                                <summary class="site-pricing-faq-question">{{ $entry['q'] }}</summary>
                                <p class="site-pricing-faq-answer">{{ $entry['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>

                {{--
                    ÇIKIŞ YOLU, FİYATIN YANINDA (FF-216 ile aynı gerekçe).

                    "Nasıl ödeyeceğim?" sorusunun cevabı bölümün kendi ödeme
                    satırında; "çıkmak istersem ne olur?" ise burada. İkisini
                    de sözleşmenin içinde bırakmak, ödeme adımından SONRA
                    öğrenmek demekti.

                    BANKA YA DA KART LOGOSU YOK: hangi kartların kabul
                    edildiği ödeme sağlayıcısının kendi yapılandırmasından
                    türer ve bu depoda öyle bir liste yapılandırılmamıştır.
                    Uydurulmuş bir logo, kabul edilmeyen bir kartı kabul
                    ediliyor göstermek olurdu.

                    Satır yasal metnin YERİNE GEÇMEZ, ona götürür.
                --}}
                <section class="site-pricing" aria-labelledby="pricing-terms-heading">
                    <div class="site-pricing-head">
                        <h2 id="pricing-terms-heading" class="site-display-3">{{ $st['pricingTermsHeading'] }}</h2>
                    </div>

                    <div class="site-pricing-exit" data-cancellation>
                        <p>{{ $st['cancellation'] }}</p>
                        {{-- Bağlantı CÜMLENİN İÇİNDE değil, kendi satırında ve
                             44 piksel: satır içi bir bağlantı dar ekranda
                             18-42 piksel yüksekliğinde kalıyor ve parmakla
                             ıskalanıyor (`docs/117` K1). --}}
                        <a class="site-action self-start underline underline-offset-2"
                           href="/refund-policy">{{ $st['cancellationCta'] }}</a>
                    </div>
                </section>
            @endif
        </div>
    </main>
@endsection
