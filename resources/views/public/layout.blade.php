<!DOCTYPE html>
{{-- KURUMSAL SİTENİN TEK KABUĞU (`docs/100` §2, `docs/118` E1).

     Sahibin talebi (2026-09-05): *"hepsi aynı masterpage shell'e bağlı olsun.
     masterpage shell (header footer) tüm frontpages'da aynı olsun,
     güncellendiğinde her yer güncellensin."*

     Önceden İKİ kabuk vardı: bu dosya (yaşayan `/`, `/pricing`, `/help`,
     `/contact`, yasal sayfalar) ve `content/*` (kütükten çizilen kurumsal
     sayfalar) kendi `<html>` belgesini kuruyordu. Üst çubuğa eklenen bir
     bağlantı ikincisinde görünmüyordu; ziyaretçi aynı sitenin iki farklı
     hâlini geziyordu. Artık tek kabuk var ve bir test ikinci bir tanımı
     imkânsız kılıyor (`SHELL-SINGLE-SOURCE-01`).

     `lang` SAYFANIN dilidir, uygulamanın değil (`docs/89`, `docs/118` E4):
     yardım makalesi okuyucunun dilinde gelir, kurumsal sayfanın dili ise
     ADRESİNDEN türer. Sayfa bir dil bildirmezse uygulamanınkine düşülür. --}}
{{-- YÖN de SAYFANIN dilinden türer, uygulamanınkinden değil (`docs/120` §5
     madde 9). Bugüne kadar `dir` uygulamanın locale'ini okuyordu: dokuz dilin
     ikisi sağdan sola (`ar`, `fa`) ve Arapça bir kurumsal sayfa, arayüzü
     İngilizce olan bir tarayıcıda soldan sağa çizilirdi — yani metin doğru,
     düzen ters. `lang` bir satır yukarıda zaten sayfadan türüyordu; ikisinin
     ayrı kaynaktan gelmesi tek başına bir kusurdu. --}}
<html lang="{{ $pageLocale ?? \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction($pageLocale ?? null) }}">
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
    {{-- Açıklama YOKSA boş bir etiket basılmaz: boş bir `description`,
         arama sonucunda sayfanın ne olduğunu söylemeyen bir satırdır ve
         hiç etiket olmamasından kötüdür. --}}
    @hasSection('description')
        <meta name="description" content="@yield('description')">
        <meta property="og:description" content="@yield('description')">
    @endif
    <link rel="canonical" href="{{ $canonicalUrl }}">
    {{-- DİL KARŞILIKLARI (`docs/119` §10.4).

         Liste `ResolveLocaleAlternates`ten gelir ve YALNIZ o dilde gerçekten
         açılan sayfaları taşır. Yarım çevrilmiş bir sayfayı burada ilan etmek,
         arama motoruna çalışmayan bir adres göstermek olurdu; tek dil kaldığında
         liste boş döner ve hiçbir şey yazılmaz. Karşılığı olmayan sayfalarda
         (fiyat, yardım, iletişim) değişken hiç tanımlı değildir ve bu blok
         sessizce atlanır. --}}
    @foreach ($localeAlternates ?? [] as $alternateLocale => $alternateUrl)
        <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}">
    @endforeach
    @isset($xDefaultUrl)
        {{-- Dili kümedeki hiçbiriyle eşleşmeyen ziyaretçinin düşeceği yer:
             ürünün asıl yazıldığı dil (`config/i18n.php` `source_locale`). --}}
        <link rel="alternate" hreflang="x-default" href="{{ $xDefaultUrl }}">
    @endisset
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $st['brand'] }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    {{-- Mühendislik kaydı: kayıt sayısı ziyaretçiye DEĞİL, kayıt sözleşmesine
         hitap eder (`docs/100` MP-04). Eskiden gezintinin altında görünür bir
         paragraftı — bir kebapçı "16/16 modules registered" okuyordu. --}}
    <meta name="zabuno-build" content="{{ $coreModuleCount }}/16 modules registered">
    @include('partials.font-preload')
    @vite(['resources/css/app.css'])
</head>
<body class="site-shell min-h-screen bg-surface text-fg">
{{-- Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez.
     Sebep ölçüldü: istemcide üretildiklerinde bir tarayıcı botu 1.736 baytlık
     boş bir kabuk görüyordu — yani ürünün kendi tanıtımı arama motorunda ve
     JavaScript çalıştırmayan AI botlarında görünmüyordu. --}}
<a href="#main-content"
   {{-- Sarı üstüne beyaz metin ~1.75:1 idi; marka sarısının tek doğru
       mürekkebi `--color-action-fg`. Panel tarafındaki SkipLink ile aynı
       kural (FF-125). --}}
   class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded focus:bg-action focus:px-4 focus:py-2 focus:text-action-fg">
    {{ $st['skipToContent'] }}
</a>

{{-- Header ve footer MASTERPAGE parçalarıdır (`docs/100` §2): sayfa
     şablonları yalnız `@section` doldurur, gezintiye dokunamaz. --}}
@include('public.partials.header')

@yield('content')

@include('public.partials.footer')
</body>
</html>
