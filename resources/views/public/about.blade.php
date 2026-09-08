@extends('public.layout')

{{-- HAKKIMIZDA — satıcı kim? (FF-216)

     Ödeme kuruluşunun üye iş yeri incelemesi bu başlığı sitede ADIYLA arar:
     kimden alışveriş yapıldığı, ürünün ne olduğu ve nasıl ulaşılacağı.
     Sayfa hiçbir şey UYDURMAZ — şirket bilgisi girilmemişken her satır
     "girilmedi" der ve sayfa `noindex` döner (`ShowAboutController`). --}}

@section('title', $st['aboutHeading'])
@section('description', $st['aboutLead'])

@section('content')
    {{-- SAHNE (`docs/146` §10). Önsöz bandı `orbit` yüzünü taşıyor: "satıcı
         kim" sorusunun cevabı bir SİSTEMDİR ve yörünge tam olarak onu çizer —
         merkezde bir şey, çevresinde dönen işler.

         BANT UYARININ ÜSTÜNDE DEĞİL, ALTINDA DEĞİL — ÖNÜNDE.

         Eksik satıcı kimliği bandı (`role="alert"`) sahnenin ALTINDA kalıyor
         ve bu bilerek: bir uyarı, bir dekorun arkasında duramaz. Ama sayfanın
         kimliğini de bandın taşıması gerekiyordu; sıra bu yüzden önsöz →
         uyarı → gövde. Ekran okuyucu uyarıyı yine belge sırasında ikinci
         öğede bulur, üstelik başlıktan hemen sonra. --}}
    <main id="main-content" class="site-page site-company-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['aboutHeading'],
            'prologueLead' => $st['aboutLead'],
            'prologueVariant' => 'orbit',
        ])

        <div class="site-measure-page site-page-body site-company-body">
            @if ($sellerIdentityMissing)
                <p class="site-legal-alert" role="alert" data-legal-alert="seller-identity">
                    <strong class="site-legal-alert-title">{{ $st['aboutIncompleteHeading'] }}</strong>
                    <span>{{ $st['aboutIncompleteBody'] }}</span>
                </p>
            @endif

            {{-- Ürün önce anlatılır; satıcı aynı kanonik kimlikten okunur. --}}
            <div class="site-company-intro">
                <section aria-labelledby="about-service-heading" class="site-legal-section site-company-service site-panel site-lit">
                    <h2 id="about-service-heading">{{ $st['aboutServiceHeading'] }}</h2>
                    <p>{{ $st['aboutServiceBody'] }}</p>
                    <p>{{ $st['aboutServiceScope'] }}</p>
                </section>

                <section aria-labelledby="about-seller-heading" class="site-legal-section site-company-seller">
                    <h2 id="about-seller-heading">{{ $st['aboutSellerHeading'] }}</h2>
                    <p>{{ $st['aboutSellerBody'] }}</p>
                    @include('public.partials.company-identity')
                </section>
            </div>

            <div class="site-company-details">
                <section aria-labelledby="about-payment-heading" class="site-legal-section">
                    {{-- KABUL EDİLEN ÖDEME YÖNTEMLERİ. Banka ya da kart logosu YOK:
                         hangi kartın kabul edildiği ödeme sağlayıcısının kendi
                         yapılandırmasından gelir ve bu depoda böyle bir liste
                         yapılandırılmamıştır. Sağlayıcının adı ise ölçülmüş bir
                         olgudur (`IyzipayGateway`). --}}
                    <h2 id="about-payment-heading">{{ $st['aboutPaymentHeading'] }}</h2>
                    <p>{{ $st['aboutPaymentBody'] }}</p>
                </section>

                <section aria-labelledby="about-reach-heading" class="site-legal-section">
                    <h2 id="about-reach-heading">{{ $st['aboutReachHeading'] }}</h2>
                    <p>{{ $st['aboutReachBody'] }}</p>
                    <p>
                        <a href="/contact" class="site-action site-cta">
                            {{ $st['aboutReachCta'] }}
                        </a>
                    </p>
                </section>
            </div>
        </div>
    </main>
@endsection
