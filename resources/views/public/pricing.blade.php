@extends('public.layout')

{{-- Sekme ve paylaşım adı: bu sayfa tam olarak PAYLAŞILMAK için var —
     fiyatı biri arkadaşına gönderir ve "— Zabuno" hangi sayfa olduğunu
     söylemez (`docs/89`).

     SAHNE (`docs/146` §10). Döngü 1 sahneyi yalnız ana sayfaya kurmuştu;
     bu sayfa artık aynı motoru, aynı dağarcıkla kullanıyor. Başlık ve giriş
     cümlesi ÖNSÖZ bandına taşındı: fiyat parçası kendi sayfasında bir `h1`
     basıyordu ve o başlık düz bir metin satırıydı — sitenin geri kalanı bir
     uzay sahnesiyken.

     ÖNSÖZ `grid` (tel kafes) yüzünü taşıyor. Gerekçe kompozisyon: fiyat bir
     ZEMİN sorusudur ("bu ne kadar tutar"), yörünge ya da veri hattı değil.
     Sahne bandın içinde kalıyor ve fiyatın KENDİSİNE hiç bulaşmıyor —
     rakamın okunduğu yerde tuval, parallax ve hareket YOK. --}}
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
            @include('public.partials.pricing', ['pricingHeadingTag' => 'none'])
        </div>
    </main>
@endsection
