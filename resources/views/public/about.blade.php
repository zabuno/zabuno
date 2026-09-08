@extends('public.layout')

{{-- HAKKIMIZDA — satıcı kim? (FF-216, FF-240)

     Ödeme kuruluşunun üye iş yeri incelemesi bu başlığı sitede ADIYLA arar:
     kimden alışveriş yapıldığı, ürünün ne olduğu ve nasıl ulaşılacağı.
     Sayfa hiçbir şey UYDURMAZ — şirket bilgisi girilmemişken her satır
     "girilmedi" der ve sayfa `noindex` döner (`ShowAboutController`).

     ── FF-240'ta eklenen iki bölüm ve NEDEN şişirme değiller ────────────

     İkisi de tek bir yeni İDDİA taşımıyor; var olan ve okunabilen şeylere
     işaret ediyorlar:

       • SÖZLEŞMELER — liste elle yazılmadı, altbilgideki yasal grubun AYNI
         dizisinden süzüldü (`ShowAboutController::agreements()`), yani her
         satırı bugün 200 dönüyor ve bir belge eklendiğinde burası kod
         değişmeden büyüyor.
       • SONRAKİ ADIM — üç hedefin üçü de bugün yaşayan rota.

     "Türkiye'nin en iyisi", "binlerce restoran" gibi doğrulanamayan tek bir
     cümle bu sayfada yok ve olmayacak. --}}

@section('title', $st['aboutHeading'])
@section('description', $st['aboutLead'])

@section('content')
    <main id="main-content" class="site-legal">
        @if ($sellerIdentityMissing)
            <p class="site-legal-alert" role="alert" data-legal-alert="seller-identity">
                <strong class="site-legal-alert-title">{{ $st['aboutIncompleteHeading'] }}</strong>
                <span>{{ $st['aboutIncompleteBody'] }}</span>
            </p>
        @endif

        <h1 class="site-page-title">{{ $st['aboutHeading'] }}</h1>
        <p class="site-legal-summary">{{ $st['aboutLead'] }}</p>

        <section aria-labelledby="about-seller-heading" class="site-legal-section">
            <h2 id="about-seller-heading">{{ $st['aboutSellerHeading'] }}</h2>
            <p>{{ $st['aboutSellerBody'] }}</p>
            @include('public.partials.company-identity')
        </section>

        <section aria-labelledby="about-service-heading" class="site-legal-section">
            <h2 id="about-service-heading">{{ $st['aboutServiceHeading'] }}</h2>
            <p>{{ $st['aboutServiceBody'] }}</p>
            <p>{{ $st['aboutServiceScope'] }}</p>
        </section>

        <section aria-labelledby="about-payment-heading" class="site-legal-section">
            {{-- KABUL EDİLEN ÖDEME YÖNTEMLERİ. Banka ya da kart logosu YOK:
                 hangi kartın kabul edildiği ödeme sağlayıcısının kendi
                 yapılandırmasından gelir ve bu depoda böyle bir liste
                 yapılandırılmamıştır. Sağlayıcının adı ise ölçülmüş bir
                 olgudur (`IyzipayGateway`). --}}
            <h2 id="about-payment-heading">{{ $st['aboutPaymentHeading'] }}</h2>
            <p>{{ $st['aboutPaymentBody'] }}</p>
        </section>

        @if ($agreements !== [])
            {{-- BU SATIŞI BAĞLAYAN BELGELER (FF-240).

                 Ödemeden ÖNCE okunabilmeleri, mesafeli satışta bir hak; bu
                 bölüm o hakkın sayfadaki karşılığı. Liste altbilgiyle aynı
                 kaynaktan süzülür, dolayısıyla ikisi hiçbir zaman ayrışamaz
                 ve hiçbiri 404'e bağlanamaz.

                 Boş bir grup HİÇ çizilmez: başlığı çizilip altı boş kalan
                 bir bölüm, olmayan bir belgenin sözünü vermektir. --}}
            <section aria-labelledby="about-agreements-heading" class="site-legal-section">
                <h2 id="about-agreements-heading">{{ $st['aboutAgreementsHeading'] }}</h2>
                <p>{{ $st['aboutAgreementsBody'] }}</p>
                {{-- `site-legal-contents` yasal sayfaların bölüm listesiyle
                     AYNI geometri: tam genişlikte, 44 piksel yüksekliğinde
                     satırlar. Bir belge listesi bir bölüm listesiyle aynı
                     şekilde okunur; ikinci bir çizim icat etmek gerekmedi. --}}
                <nav class="site-legal-contents" aria-labelledby="about-agreements-heading">
                    <ol>
                        @foreach ($agreements as $agreement)
                            <li><a href="{{ $agreement['href'] }}">{{ $agreement['label'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </section>
        @endif

        <section aria-labelledby="about-reach-heading" class="site-legal-section">
            <h2 id="about-reach-heading">{{ $st['aboutReachHeading'] }}</h2>
            <p>{{ $st['aboutReachBody'] }}</p>
        </section>

        {{-- SONRAKİ ADIM. Üç hedefin üçü de bugün yaşayan rota; ızgara
             `auto-fit` ile büyür, kırılma noktası jetonu yok (`MP-05`). --}}
        <section aria-labelledby="about-next-heading" class="site-legal-section">
            <h2 id="about-next-heading">{{ $st['aboutNextHeading'] }}</h2>
            <div class="site-next">
                <a href="/pricing" class="site-cta">{{ $st['aboutNextPricing'] }}</a>
                <a href="/help" class="site-cta">{{ $st['aboutNextHelp'] }}</a>
                <a href="/contact" class="site-cta" data-emphasis="true">{{ $st['aboutReachCta'] }}</a>
            </div>
        </section>
    </main>
@endsection
