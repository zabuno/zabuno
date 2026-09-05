<!DOCTYPE html>
{{-- TEK ÜRÜNÜN SAYFASI — FF-116, `docs/105` §4.3.

     Ayrı bir şablon, çünkü menü sayfasının gövdesini kopyalayıp yalnız
     başlığı değiştirmek yüzlerce NEREDEYSE AYNI sayfa üretirdi; programatik
     SEO'nun tam olarak yapmaması gereken şey budur. Bu sayfanın asıl içeriği
     ürünün kendisidir: adı, görseli, açıklaması, fiyatı ve alerjenleri.

     Çıkmaz sokak yok: sayfanın altında tam menüye dönüş var. --}}
<html lang="{{ $guestLocale }}" dir="{{ \App\Support\Localization\GuestLocale::direction($guestLocale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $item['productName'] }}@if ($brandName !== '') — {{ $brandName }}@endif</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $item['productName'] }}">
    <meta property="og:site_name" content="Zabuno">
    @if (! empty($item['description']))
        <meta name="description" content="{{ \Illuminate\Support\Str::limit((string) $item['description'], 155) }}">
        <meta property="og:description" content="{{ \Illuminate\Support\Str::limit((string) $item['description'], 155) }}">
    @endif
    {{-- Sayfada olmayan hiçbir şey işaretlenmez. --}}
    <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! $structuredData !!}</script>
    <link rel="icon" href="/icons/zabuno-menu-512.svg" sizes="512x512" type="image/svg+xml">
    @include('partials.theme-bootstrap')
    {{-- AYNI TOKEN KÖKÜ (`docs/113` §6.3). Bu blok eskiden `--qr-*`
         değerlerini menü sayfasından KOPYALAYARAK taşıyordu; kopya ilk gün
         aynı görünür, ancak biri düzeltilip diğeri unutulduğunda ayrışır. --}}
    @include('partials.guest-surface-style')
    <style nonce="{{ $cspNonce ?? '' }}">
        .qr-page {
            padding: var(--qr-gutter);
            max-width: 44rem;
            margin-inline: auto;
        }

        /* Dokunma hedefi: 44 px'in altındaki bir bağlantı parmakla vurulamaz.
           Geri dönüş bir ÇİP biçimindedir — kaynağın gezinme dili budur ve
           metin bağlantısından daha kolay vurulur. */
        .qr-item-back,
        .qr-item-forward {
            display: inline-flex;
            align-items: center;
            min-height: var(--qr-tap);
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            background: var(--qr-surface);
            font-size: 0.9375rem;
            font-weight: 600;
            text-decoration: none;
        }

        .qr-item-category {
            margin: 12px 0 0;
            font-size: 0.875rem;
            color: var(--qr-muted);
        }

        .qr-item-name {
            font-size: clamp(1.5rem, 6vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.15;
            margin: 4px 0 12px;
        }

        .qr-item-image {
            width: 100%;
            height: auto;
            border-radius: var(--qr-radius);
            background: var(--qr-sunken);
            object-fit: cover;
        }

        .qr-item-price {
            font-size: 1.375rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            margin: 12px 0 0;
        }

        .qr-item-description {
            margin: 12px 0 0;
            line-height: 1.6;
            color: var(--qr-fg-2);
            text-wrap: pretty;
        }

        /* Tükendi METİNLE söylenir; solukluk ya da renk tek başına anlatmaz
           (WCAG 1.4.1). Kendi satırında durur — bir rozet gibi fiyata rakip
           olsaydı 320'de ikisi de kırpılırdı (`docs/113` §7.2.2). */
        .qr-item-sold-out {
            display: block;
            margin-top: 8px;
            font-weight: 700;
            color: var(--qr-warn);
        }

        /* ALERJEN BÖLÜMÜ — kaynağın ürün sayfasındaki bölümü (`docs/113`
           §1.1 no.10). Başlık, çıplak bir çip listesinin ne olduğunu ekran
           okuyucuya da söyler.

           BÖLÜM YALNIZ BİLDİRİLENİ SAYAR. "Alerjensizdir", "vegan
           sertifikalıdır" gibi bir cümle burada HİÇBİR koşulda kurulmaz;
           `ArtifactSchemaValidator` bu alan adlarını ada göre reddediyor ve
           gerekçesi kodda yazılı: yanlış bir alerjensizlik iddiası bir
           SAĞLIK OLAYIDIR. Bu, bir kapsam kararı değil bir güvenlik
           kararıdır ve sonraki paketlerde de yerinde kalır. */
        .qr-item-allergens-label {
            margin: 24px 0 8px;
            font-size: 1rem;
            font-weight: 700;
        }

        .qr-item-allergens {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .qr-item-allergens li {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--qr-warn-tint);
            color: var(--qr-warn);
        }

        .qr-item-footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid var(--qr-border);
        }
    </style>
</head>
<body>
{{-- ZABUNO ÇERÇEVESİ — menü sayfasıyla AYNI sınır (`docs/113` §6.2).
     Ürün sayfası da misafirin gördüğü bir yüzeydir; zabuno başlığı geldiğinde
     iki şablona ayrı ayrı değil, çerçeveye bir kez girer. --}}
<x-zabuno surface="menu-item">
<main role="main" class="qr-page" data-menu-key="{{ $menuKey }}">
    {{-- ŞUBE ŞU ANDA KAPALI (FF-143) — menü sayfasındakiyle AYNI şerit, AYNI
         karardan.

         Bu sayfa bir DERİN BAĞLANTIDIR: aramadan, paylaşılan bir bağlantıdan
         ya da menüdeki bir dokunuştan gelinir. Şeridi yalnız menü sayfasına
         koymak, gece 23:30'da doğrudan "Adana Kebap" sayfasına düşen kişiye
         restoranın açık olduğunu söylemek olurdu — hiçbir şey demeden.

         Ürün GİZLENMEZ: kapalı olmak "yemek yok" demek değildir ve yarını
         planlayan misafirin fiyatı görmeye hakkı vardır. --}}
    @include('partials.guest-closed-notice', ['closedNotice' => $closedNotice ?? null])

    {{-- Geri dönüş EN ÜSTTE de var: misafir yanlış ürüne girdiyse sayfanın
         sonuna kadar kaydırmak zorunda kalmamalı. --}}
    <a class="qr-item-back" href="{{ $menuPath }}">{{ $brandName !== '' ? $brandName : $guestText['subtitle'] }}</a>

    @if ($categoryName !== '')
        <p class="qr-item-category" @isset($contentLocale) lang="{{ $contentLocale }}" @endisset>{{ $categoryName }}</p>
    @endif

    <h1 class="qr-item-name" @isset($contentLocale) lang="{{ $contentLocale }}" @endisset>{{ $item['productName'] }}</h1>

    @if (! empty($item['image']['sources']))
        @php($image = $item['image'])
        <img class="qr-item-image"
             src="{{ $image['sources'][0]['url'] }}"
             width="{{ $image['width'] }}" height="{{ $image['height'] }}"
             alt="{{ $image['altText'] }}"
             decoding="async">
    @endif

    @php($priceLabel = \App\Support\Money\PriceLabel::for((int) $item['priceMinorAmount'], (string) $item['currencyCode']))
    @if ($priceLabel !== null)
        <p class="qr-item-price">{{ $priceLabel }}</p>
    @endif

    @if ($soldOut)
        {{-- Tükendi METİNLE söylenir; yalnız renk ya da soluklukla anlatmak
             renk göremeyen misafir için hiçbir şey anlatmaz (WCAG 1.4.1). --}}
        <p><span class="qr-item-sold-out">{{ $guestText['soldOut'] }}</span></p>
    @endif

    @if (! empty($item['description']))
        <p class="qr-item-description" @isset($contentLocale) lang="{{ $contentLocale }}" @endisset>{{ $item['description'] }}</p>
    @endif

    @if (! empty($item['allergens']))
        {{-- Başlık BİLDİRİLENİ tanıtır, bir tamlık iddiası taşımaz: cümle
             "alerjen bilgisi" der, "bunlar dışında alerjen yoktur" demez. --}}
        <h2 class="qr-item-allergens-label">{{ $guestText['allergensLabel'] }}</h2>
        <ul class="qr-item-allergens">
            @foreach ($item['allergens'] as $allergen)
                <li>{{ $allergen }}</li>
            @endforeach
        </ul>
    @endif

    <div class="qr-item-footer">
        {{-- Ürün sayfası bir ÇIKMAZ SOKAK OLMAZ: misafir tam menüye
             dönebilmeli ve arama motoru menüye bir bağlantı görmeli.

             Kaynağın ürün sayfasında burada bir alt eylem çubuğu var (adet
             denetimi + "Sepete ekle"). O çubuğun arka ucu ayrı bir pakette
             geliyor; geldiğinde bu altbilgi onu kaldırır ve `docs/113`
             §7.2.2'nin kararı geçerli olur: 320'de adet denetimi ile eylem
             düğmesi AYNI satırı paylaşmaz, çünkü sabitler eylem metnine
             153 px bırakıp onu kesiyor. --}}
        <a class="qr-item-forward" href="{{ $menuPath }}">{{ $guestText['categoriesLabel'] }}</a>
    </div>
</main>
</x-zabuno>
</body>
</html>
