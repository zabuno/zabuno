@extends('public.layout')

{{-- BİLGİ TOPLUMU HİZMETLERİ (C3).

     Sayfanın tamamı BU DEPODA GERÇEKTEN VAR OLAN olgulardan kurulur:
     şirket kimliği ortamdan (`CompanyProfile`), yasal belge listesi
     gezintiden (`SiteNavigation` yasal bölgesi — hepsinin rotası ve metni
     var), iletişim yolu ise çalışan `/contact` formundan. Uydurma bir
     sicil kaydı, bir üyelik ya da bir uyum iddiası YOKTUR ve olmayacaktır:
     böyle bir cümle, sahibinin hukuki incelemesinden gelmediği sürece
     sayfayı yanlış yapar. --}}

@section('title', $st['issHeading'])
@section('description', $st['issLead'])

@section('content')
    <main id="main-content" class="site-page site-company-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['issHeading'],
            'prologueLead' => $st['issLead'],
            'prologueVariant' => 'grid',
        ])

        <div class="site-measure-page site-page-body site-company-body">
            @if ($sellerIdentityMissing)
                {{-- Eksik kimlik SESSİZ GEÇİLMEZ. Bandın `role="alert"`
                     olması bir süs değil: sayfanın konusu satıcının kim
                     olduğu ve o soru bugün eksik yanıtlanıyor. --}}
                <p class="site-legal-alert" role="alert" data-legal-alert="seller-identity">
                    <strong class="site-legal-alert-title">{{ $st['aboutIncompleteHeading'] }}</strong>
                    <span>{{ $st['aboutIncompleteBody'] }}</span>
                </p>
            @endif

            <section aria-labelledby="iss-identity-heading" class="site-legal-section site-panel site-lit">
                <h2 id="iss-identity-heading">{{ $st['issIdentityHeading'] }}</h2>
                <p>{{ $st['issIdentityBody'] }}</p>
                {{-- ALTBİLGİDEN TAŞINAN TABLO — aynı parça, aynı kaynak.
                     `/about` ve `/contact` de aynı `CompanyIdentity::rows()`
                     çağrısını çiziyor; üçü ayrışamaz. --}}
                @include('public.partials.company-identity')
            </section>

            <section aria-labelledby="iss-documents-heading" class="site-legal-section">
                <h2 id="iss-documents-heading">{{ $st['issDocumentsHeading'] }}</h2>
                <p>{{ $st['issDocumentsBody'] }}</p>

                {{-- BELGE LİSTESİ GEZİNTİDEN GELİR (`SiteNavigation` yasal
                     bölgesi): burada elle yazılmış bir belge adı olsaydı,
                     bir belge eklendiği gün bu sayfa eksik kalırdı ve kimse
                     fark etmezdi. Listedeki her adres bugün 200 döner. --}}
                @foreach ($nav['legal'] as $group)
                    <ul class="site-iss-documents" data-iss-documents="{{ $group['id'] }}">
                        @foreach ($group['items'] as $item)
                            <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endforeach
            </section>

            <section aria-labelledby="iss-contact-heading" class="site-legal-section">
                <h2 id="iss-contact-heading">{{ $st['issContactHeading'] }}</h2>
                <p>{{ $st['issContactBody'] }}</p>

                <p>
                    <a href="/contact" class="site-action site-cta">{{ $st['aboutReachCta'] }}</a>
                </p>

                {{-- VERİ HAKLARININ YOLU — yasal sayfalardaki BİRE BİR aynı
                     etiketler ve aynı yapılandırma değeri (FF-169). İkinci
                     bir metin yazmak, aynı hakkı iki farklı şekilde anlatmak
                     olurdu. --}}
                <p class="site-iss-data-request">
                    <span class="site-iss-data-request-label">{{ $st['dataRequestAddressLabel'] }}</span>
                    @if ($dataRequestAddress === null)
                        {{ $st['dataRequestAddressMissing'] }}
                    @else
                        {{ $dataRequestAddress }}
                    @endif
                </p>
            </section>
        </div>
    </main>
@endsection
