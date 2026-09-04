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
    <style nonce="{{ $cspNonce ?? '' }}">
        :root {
            color-scheme: light dark;
            --qr-bg: #ffffff;
            --qr-fg: #1f2937;
            --qr-muted: #6b7280;
            --qr-border: rgba(107, 114, 128, 0.25);
            --qr-chip-bg: rgba(107, 114, 128, 0.12);
        }

        :root.dark,
        :root[data-theme="dark"] {
            --qr-bg: #111827;
            --qr-fg: #f9fafb;
            --qr-muted: #9ca3af;
            --qr-border: rgba(156, 163, 175, 0.3);
            --qr-chip-bg: rgba(156, 163, 175, 0.16);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: clamp(0.75rem, 4vw, 1.5rem);
            background: var(--qr-bg);
            color: var(--qr-fg);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .qr-item-back,
        .qr-item-forward {
            display: inline-block;
            font-size: 0.9rem;
            color: inherit;
            /* Dokunma hedefi: 44 px'in altındaki bir bağlantı parmakla
               vurulamaz. */
            min-height: 44px;
            line-height: 44px;
        }

        .qr-item-category {
            margin: 0;
            font-size: 0.85rem;
            color: var(--qr-muted);
        }

        .qr-item-name {
            font-size: clamp(1.4rem, 6vw, 2rem);
            margin: 0.25rem 0 0.5rem;
        }

        .qr-item-image {
            width: 100%;
            max-width: 32rem;
            height: auto;
            border-radius: 0.75rem;
            background: var(--qr-chip-bg);
        }

        .qr-item-price {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0.75rem 0 0;
        }

        .qr-item-description {
            margin: 0.75rem 0 0;
            max-width: 38rem;
            line-height: 1.6;
        }

        .qr-item-sold-out {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            color: var(--qr-muted);
        }

        .qr-item-allergens {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin: 0.75rem 0 0;
            padding: 0;
            list-style: none;
        }

        .qr-item-allergens li {
            font-size: 0.75rem;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: var(--qr-chip-bg);
            color: var(--qr-muted);
        }

        .qr-item-footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--qr-border);
        }
    </style>
</head>
<body>
<main role="main" data-menu-key="{{ $menuKey }}">
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
        <ul class="qr-item-allergens">
            @foreach ($item['allergens'] as $allergen)
                <li>{{ $allergen }}</li>
            @endforeach
        </ul>
    @endif

    <div class="qr-item-footer">
        {{-- Ürün sayfası bir ÇIKMAZ SOKAK OLMAZ: misafir tam menüye
             dönebilmeli ve arama motoru menüye bir bağlantı görmeli. --}}
        <a class="qr-item-forward" href="{{ $menuPath }}">{{ $guestText['categoriesLabel'] }}</a>
    </div>
</main>
</body>
</html>
