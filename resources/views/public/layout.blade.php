<!DOCTYPE html>
{{-- `lang` SAYFANIN dilidir, uygulamanın değil (`docs/89`).

     Yardım makalesi okuyucunun dilinde geliyor; `lang` sabit kalsaydı ekran
     okuyucu Türkçe metni İngilizce telaffuz ederdi. Sayfa bir dil bildirmezse
     uygulamanınkine düşülür. --}}
<html lang="{{ $pageLocale ?? \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Tema, ilk boyamadan ÖNCE uygulanmalı; aksi hâlde koyu tema seçmiş
         bir kullanıcı her sayfada bir an beyaz ekran görür. Bu yüzden
         herhangi bir stil bağlantısından önce gelir. --}}
    @include('partials.theme-bootstrap')
    {{--
        HANGİ SAYFA ölçüme geçer (`docs/100` Faz 3).

        Bugüne kadar bütün kamu sayfaları tek bir yüzey olarak ("marketing")
        akıyordu: "fiyatı kaç kişi okudu, sonra kaçı iletişime geçti"
        sorusunun cevabı raporda yoktu, çünkü sayfalar birbirinden ayrılmıyordu.

        Kimlik SUNUCUDAN gelir, adres çubuğundan türetilmez: adres yarın
        değişebilir ve o gün geçmiş raporlar sessizce ikiye bölünürdü.
        Bilinmeyen bir sayfa `unknown` olarak akar — ölçüme hiç girmemekten
        iyidir, çünkü eksik satır fark edilmez, `unknown` fark edilir.
    --}}
    @include('partials.analytics', ['analyticsContext' => [
        'zabuno_surface' => 'marketing',
        'zabuno_page' => $pageKey ?? 'unknown',
    ]])
    @include('public.partials.measurement')
    <title>@yield('title') — {{ $st['titleSuffix'] }}</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $st['brand'] }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    {{-- Mühendislik kaydı: kayıt sayısı ziyaretçiye DEĞİL, kayıt sözleşmesine
         hitap eder (`docs/100` MP-04). Eskiden gezintinin altında görünür bir
         paragraftı — bir kebapçı "16/16 modules registered" okuyordu. --}}
    <meta name="zabuno-build" content="{{ $coreModuleCount }}/16 modules registered">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-surface text-fg">
{{-- Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez.
     Sebep ölçüldü: istemcide üretildiklerinde bir tarayıcı botu 1.736 baytlık
     boş bir kabuk görüyordu — yani ürünün kendi tanıtımı arama motorunda ve
     JavaScript çalıştırmayan AI botlarında görünmüyordu. --}}
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-action focus:px-4 focus:py-2 focus:text-white">
    {{ $st['skipToContent'] }}
</a>

{{-- Header ve footer MASTERPAGE parçalarıdır (`docs/100` §2): sayfa
     şablonları yalnız `@section` doldurur, gezintiye dokunamaz. --}}
@include('public.partials.header')

@yield('content')

@include('public.partials.footer')
</body>
</html>
