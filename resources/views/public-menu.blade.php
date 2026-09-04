<!DOCTYPE html>
@php
    /* Restoran kimliği YAYIN SNAPSHOT'INDAN okunur (`docs/75`, P0-03).
       Canlı sorgudan okunsaydı, şubenin adı değiştiği gün geçmiş bir yayın
       da sessizce değişirdi.

       Kimlik alanı EKLENMEDEN önce yayınlanmış menüler hâlâ vardır; onlar
       için sunucunun canlı olarak bildiği ada düşülür. Donmuş bir değer
       YOKSA donmuşluk ihlali de yoktur. */
    $identity = isset($snapshot['identity']) && is_array($snapshot['identity'])
        ? \App\Domain\Publication\MenuIdentity::fromSnapshot($snapshot['identity'])
        : null;

    /* `srcset` metnini PHP kurar: öznitelik içine kaçırılan bir döngü,
       şablonu okunmaz ve kırılgan yapar. */
    $srcset = static fn (array $sources): string => implode(', ', array_map(
        static fn (array $source): string => $source['url'].' '.$source['width'].'w',
        $sources,
    ));

    /* Paylaşım önizlemesinin açıklaması. WhatsApp'ta bağlantıyı gören kişi
       ÖNCE bunu okur; "yayınlanmış sürüm" orada hiçbir şey anlatmaz.
       Kimlik biliniyorsa şube ve adres okunur. */
    $documentDescription = static function (?\App\Domain\Publication\MenuIdentity $identity): string {
        if ($identity === null || $identity->brandName === '') {
            return 'Yayınlanan menü — güncel yayınlanmış sürüm.';
        }

        $parts = array_values(array_filter([
            $identity->locationName,
            $identity->addressLine,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === []
            ? $identity->brandName.' — menü'
            : $identity->brandName.' · '.implode(' · ', $parts);
    };

    /* "Bugün tükendi" — donmuş menünün üstündeki tebeşir notu (`docs/82`).
       Yayından bağımsız okunur; snapshot değişmez. */
    $soldOut = array_flip(array_map('intval', $outOfStockItemIds ?? []));

    $categories = $snapshot['categories'] ?? [];
    $categoryCount = count($categories);
    $itemCount = 0;

    foreach ($categories as $category) {
        $itemCount += count($category['menuItems'] ?? []);
    }

    /* Metin ŞABLONDA değil KATALOGDA yaşar (`docs/85`): Blade'e yazılan bir
       cümleyi sahip hiçbir PO dosyasından çeviremez.
   
       Harita verilmediyse görünüm ONU KENDİ ÇÖZER. Aksi hâlde haritayı
       geçirmeyi unutan bir çağıran, sayfayı sessizce BOŞ ETİKETLERLE
       basardı — ve bu, ekranda görülene kadar fark edilmezdi. */
    $gt = $guestText ?? app(\App\Support\Localization\GuestText::class)->all(
        $guestLocale ?? $contentLocale ?? 'tr',
        $categoryCount,
        $itemCount,
    );

    $text = static fn (string $key): string => (string) ($gt[$key] ?? '');

    $headline = $identity?->brandName ?: trim((string) ($fallbackBrandName ?? ''));
    $documentTitle = $headline !== '' ? $headline : 'Menü';
@endphp
{{-- Dil, UYGULAMANIN değil MENÜNÜN dilidir: ürün adlarını restoran kendi
     dilinde yazar. Yanlış `lang`, ekran okuyucunun metni yanlış telaffuz
     etmesine ve arama motorunun sayfayı yanlış dile atamasına yol açar. --}}
{{-- `lang` ARAYÜZ dilidir; menü içeriği kendi dilini ayrıca taşır
     (aşağıdaki kategori listesinde). İkisini tek etikete sıkıştırmak, ekran
     okuyucunun ya arayüzü ya ürün adlarını yanlış telaffuz etmesi demekti
     (`docs/85`). --}}
<html lang="{{ $guestLocale ?? $contentLocale ?? \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ isset($guestLocale) ? \App\Support\Localization\GuestLocale::direction($guestLocale) : \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>
    @isset($canonicalUrl)
        {{-- Aynı menü izleme parametresiyle, farklı sorgu sırasıyla veya
             sondaki slash ile birden çok adresten açılabilir. Kanonik
             etiket olmadan arama motoru hangisini indeksleyeceğine kendi
             karar verir. --}}
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endisset
    {{-- Paylaşım önizlemesi: bu sayfa çoğunlukla WhatsApp'ta paylaşılır ve
         oradaki bot JavaScript çalıştırmaz; etiketler sunucuda basılır. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $documentTitle }}">
    <meta property="og:site_name" content="Zabuno">
    @php
        // Blade'in tek satırlık `@php(...)` biçimi İÇ İÇE parantezi doğru
        // kapatmıyor ve geri kalan şablonu PHP olarak yutuyor; blok biçimi
        // kullanılır.
        $pageDescription = $documentDescription($identity);
    @endphp
    <meta name="description" content="{{ $pageDescription }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    @isset($structuredData)
        {{-- Sayfada olmayan hiçbir şey işaretlenmez: bu veri sayfayı
             render eden anlık görüntünün TA KENDİSİNDEN türetilir. --}}
        <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! $structuredData !!}</script>
    @endisset
    <link rel="manifest" href="/public-menu.webmanifest">
    <meta name="theme-color" content="#1f2937">
    <link rel="apple-touch-icon" href="/icons/zabuno-menu-192.svg" sizes="192x192">
    <link rel="icon" href="/icons/zabuno-menu-512.svg" sizes="512x512" type="image/svg+xml">
    @include('partials.theme-bootstrap')
    {{-- Misafirin gördüğü sayfa. Tenant kimliği olmadan bu trafik tek bir
         yığın olurdu ve "hangi restoranın menüsü kaç kez açıldı" sorusu
         tarayıcı tarafında cevapsız kalırdı. --}}
    @include('partials.analytics', ['analyticsContext' => $analyticsContext ?? ['zabuno_surface' => 'menu']])
    @if ($identity?->primaryColor !== null || $identity?->secondaryColor !== null)
        {{-- MARKA RENGİ yayından okunur (FF-89), canlı markadan değil: renk
             yarın değişirse dünkü yayın değişmez. Renk yalnız DEKORASYONDUR
             (üst şerit ve kategori altı çizgisi); metin ya da metin arkası
             olarak kullanılmaz, çünkü restoranın seçtiği açık sarı bir renk
             beyaz üstünde okunmaz hâle gelirdi ve kontrastı biz garanti
             edemeyiz. --}}
        <style nonce="{{ $cspNonce ?? '' }}">
            :root {
                @if ($identity->primaryColor !== null) --qr-brand: {{ $identity->primaryColor }}; @endif
                @if ($identity->secondaryColor !== null) --qr-brand-secondary: {{ $identity->secondaryColor }}; @endif
            }
        </style>
    @endif
    <style nonce="{{ $cspNonce ?? '' }}">
        :root {
            color-scheme: light dark;
            --qr-bg: #ffffff;
            --qr-fg: #1f2937;
            --qr-muted: #6b7280;
            --qr-border: rgba(107, 114, 128, 0.25);
            --qr-accent: #1f2937;
            --qr-chip-bg: rgba(107, 114, 128, 0.12);
        }

        :root.dark,
        :root[data-theme="dark"] {
            --qr-bg: #111827;
            --qr-fg: #f9fafb;
            --qr-muted: #9ca3af;
            --qr-border: rgba(156, 163, 175, 0.3);
            --qr-accent: #f9fafb;
            --qr-chip-bg: rgba(156, 163, 175, 0.16);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: clamp(0.75rem, 4vw, 1.5rem);
            width: 100%;
            max-width: 100%;
            background: var(--qr-bg);
            color: var(--qr-fg);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        /* Marka şeridi: renk seçilmemişse `--qr-brand` tanımsızdır ve şerit
           yüksekliği sıfır kalır — seçmeyen restoran, seçmiş gibi
           gösterilmez. */
        .qr-brand-bar {
            height: 0;
        }

        @supports (height: 4px) {
            .qr-brand-bar {
                height: 4px;
                margin: calc(-1 * clamp(0.75rem, 4vw, 1.5rem)) calc(-1 * clamp(0.75rem, 4vw, 1.5rem)) 1rem;
                background: var(--qr-brand, transparent);
            }
        }

        .qr-menu-header {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }

        .qr-menu-title {
            font-size: clamp(1.25rem, 5vw, 1.75rem);
            margin: 0;
        }

        .qr-menu-subtitle {
            margin: 0;
            font-size: 0.9rem;
            color: var(--qr-muted);
        }

        .qr-menu-language {
            display: flex;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .qr-menu-language a {
            color: inherit;
        }

        .qr-menu-language [aria-current='true'] {
            font-weight: 600;
        }

        .qr-menu-content-notice {
            margin: 0;
            font-size: 0.8rem;
            color: var(--qr-muted);
        }

        .qr-menu-location {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }

        /* Uzun bir cadde adı 320 px'te satırı taşırmaz: kelime kırılır. */
        .qr-menu-address,
        .qr-menu-phone {
            margin: 0;
            font-size: 0.9rem;
            color: var(--qr-muted);
            overflow-wrap: anywhere;
        }

        .qr-menu-phone a {
            color: inherit;
        }

        .qr-menu-summary {
            margin: 0;
            font-size: 0.85rem;
            color: var(--qr-muted);
        }

        .qr-menu-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
            padding: 0;
            list-style: none;
        }

        .qr-menu-nav a {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            color: inherit;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .qr-menu-search {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            margin: 1rem 0;
        }

        .qr-menu-search label {
            font-size: 0.85rem;
            font-weight: 600;
        }

        #menu-search {
            font: inherit;
            width: 100%;
            padding: 0.6rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--qr-border);
            background: var(--qr-bg);
            color: var(--qr-fg);
        }

        #menu-search-status {
            font-size: 0.8rem;
            color: var(--qr-muted);
            min-height: 1.1em;
        }

        .qr-menu-category {
            margin-bottom: clamp(1rem, 4vw, 1.75rem);
            scroll-margin-top: 1rem;
        }

        .qr-menu-category-name {
            font-size: 1.1rem;
            margin: 0 0 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 2px solid var(--qr-brand-secondary, var(--qr-border));
        }

        .qr-menu-category-empty {
            font-size: 0.85rem;
            color: var(--qr-muted);
        }

        .qr-menu-item-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .qr-menu-item {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.5rem 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--qr-border);
        }

        .qr-menu-item-name {
            font-weight: 600;
        }

        /* Görsel satırın SOLUNDA sabit bir kutu: her satır aynı hizada
           başlar, liste taranabilir kalır. */
        .qr-menu-item-image {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 0.5rem;
            background: var(--qr-chip-bg);
            flex: 0 0 auto;
        }

        /* Solukluk YARDIMCIDIR, tek başına anlatmaz: durumu satırdaki
           metnin kendisi söylüyor. */
        .qr-menu-item-sold-out .qr-menu-item-name,
        .qr-menu-item-sold-out .qr-menu-item-price {
            opacity: 0.65;
        }

        .qr-menu-item-sold-out-note {
            font-size: 0.75rem;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            color: var(--qr-muted);
            white-space: nowrap;
        }

        .qr-menu-item-description {
            width: 100%;
            font-size: 0.85rem;
            color: var(--qr-muted);
        }

        .qr-menu-logo {
            width: 96px;
            height: auto;
            max-width: 40vw;
            align-self: flex-start;
            margin-bottom: 0.25rem;
        }

        .qr-menu-item-price {
            white-space: nowrap;
        }

        .qr-menu-item-allergens {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .qr-menu-item-allergen-chip {
            font-size: 0.75rem;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: var(--qr-chip-bg);
            color: var(--qr-muted);
        }

        .qr-menu-empty-state {
            padding: 1rem 0;
            color: var(--qr-muted);
            font-size: 0.9rem;
        }

        .pwa-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        #pwa-install-button {
            font: inherit;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid currentColor;
            background: transparent;
            color: inherit;
            cursor: pointer;
        }

        #pwa-install-button[hidden] {
            display: none;
        }

        #pwa-install-status,
        #pwa-offline-status {
            font-size: 0.85rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="pwa-bar">
    <button type="button" id="pwa-install-button" hidden>{{ $text('installButton') }}</button>
    <span id="pwa-install-status" role="status" aria-live="polite"></span>
    <span id="pwa-offline-status" role="status" aria-live="polite"></span>
</div>
<main role="main" @isset($menuKey) data-menu-key="{{ $menuKey }}" @endisset>
    <div class="qr-brand-bar" aria-hidden="true"></div>
    <header class="qr-menu-header">
        {{-- Misafirin gördüğü ilk kelime "Menü" değil, gittiği yerin adıdır.
             Ad bilinmiyorsa başlık yine de basılır: boş bir <h1> sayfayı
             ekran okuyucu için başlıksız bırakırdı. --}}
        @php($logo = $identity !== null && isset($snapshot['identity']['logo']) && is_array($snapshot['identity']['logo'])
            ? $snapshot['identity']['logo']
            : null)

        @if ($logo)
            {{-- Ölçüler ATTRIBUTE olarak basılır: görsel inerken sayfa
                 zıplamasın, misafir okuduğu satırı kaybetmesin. --}}
            <img class="qr-menu-logo"
                 src="{{ $logo['sources'][count($logo['sources']) - 1]['url'] }}"
                 srcset="{{ $srcset($logo['sources']) }}"
                 sizes="96px"
                 width="{{ $logo['width'] }}" height="{{ $logo['height'] }}"
                 alt="{{ $logo['altText'] }}"
                 decoding="async">
        @endif

        <h1 class="qr-menu-title">{{ $documentTitle }}</h1>

        @if ($identity !== null && $identity->locationName !== '' && $identity->locationName !== $headline)
            <p class="qr-menu-location">{{ $identity->locationName }}</p>
        @endif

        @if ($identity?->addressLine)
            <p class="qr-menu-address">{{ $identity->addressLine }}</p>
        @endif

        @if ($identity?->telHref())
            {{-- Misafir masada numarayı elle yazmaz. Görünen metin insan
                 için, bağlantı makine içindir. --}}
            <p class="qr-menu-phone">
                <a href="{{ $identity->telHref() }}">{{ $identity->phone }}</a>
            </p>
        @endif

        {{-- Bu cümle ürün-İÇİ bir cümledir: "yayınlanmış sürüm" misafirin
             sorduğu bir soru değil, bizim kavramımız. Sayfa kendi kimliğini
             söyleyebiliyorsa gereksizdir; söyleyemiyorsa misafire hiç
             değilse ne baktığını anlatır (`docs/79`). --}}
        @isset($guestLocale)
            {{-- Dil seçimi düz BAĞLANTIDIR: JavaScript çalışmasa da çalışır ve
                 seçim sunucuda hatırlanır (çerez), böylece sayfa daha ilk
                 boyamada doğru dilde gelir.

                 Başlıktan BAĞIMSIZ: restoranın adı bilinsin bilinmesin,
                 misafirin dili değiştirebilmesi gerekir. --}}
            <nav class="qr-menu-language" aria-label="{{ $text('languageLabel') }}">
                @foreach (\App\Support\Localization\GuestLocale::SUPPORTED as $option)
                    @if ($option === $guestLocale)
                        <span aria-current="true">{{ strtoupper($option) }}</span>
                    @else
                        <a href="?lang={{ $option }}" rel="nofollow">{{ strtoupper($option) }}</a>
                    @endif
                @endforeach
            </nav>

            @if ($guestLocale !== ($contentLocale ?? $guestLocale))
                {{-- İÇERİK çevirisi ARAYÜZ çevirisi değildir: ürün adlarını
                     restoran kendi dilinde yazar. Bunu söylememek,
                     tutulmayacak bir söz vermek olurdu. --}}
                <p class="qr-menu-content-notice">{{ $text('contentNotice') }}</p>
            @endif
        @endisset

        @if ($headline === '')
            <p class="qr-menu-subtitle">{{ $text('subtitle') }}</p>
        @endif
        <p class="qr-menu-summary">{{ $text('summary') }}</p>
    </header>

    @if ($categoryCount > 0)
        <nav class="qr-menu-nav" aria-label="{{ $text('categoriesLabel') }}">
            @foreach ($categories as $navIndex => $category)
                <a href="#category-{{ $navIndex }}">{{ $category['name'] }}</a>
            @endforeach
        </nav>
    @endif

    <div class="qr-menu-search">
        <label for="menu-search">{{ $text('searchLabel') }}</label>
        <input type="search" id="menu-search" name="menu-search" autocomplete="off" placeholder="{{ $text('searchPlaceholder') }}">
        <p id="menu-search-status" role="status" aria-live="polite"></p>
    </div>

    {{-- MENÜ İÇERİĞİ kendi dilini taşır: arayüz İngilizce olsa da ürün
         adları restoranın dilindedir ve ekran okuyucu onları o dilde
         telaffuz etmeli (`docs/85`). --}}
    @if ($categoryCount === 0)
        <p class="qr-menu-empty-state">{{ $text('menuEmpty') }}</p>
    @else
        @foreach ($categories as $categoryIndex => $category)
            <section class="qr-menu-category" id="category-{{ $categoryIndex }}" data-category @isset($contentLocale) lang="{{ $contentLocale }}" @endisset>
                <h2 class="qr-menu-category-name">{{ $category['name'] }}</h2>

                @if (empty($category['menuItems']))
                    <p class="qr-menu-category-empty">{{ $text('categoryEmpty') }}</p>
                @else
                    <ul class="qr-menu-item-list">
                        @foreach ($category['menuItems'] as $item)
                            @php($isSoldOut = isset($item['menuItemId']) && isset($soldOut[(int) $item['menuItemId']]))
                            <li class="qr-menu-item{{ $isSoldOut ? ' qr-menu-item-sold-out' : '' }}" data-item data-item-name="{{ $item['productName'] }}" @isset($item['menuItemId']) data-menu-item-id="{{ $item['menuItemId'] }}" @endisset>
                                @if (! empty($item['image']['sources']))
                                    @php($image = $item['image'])
                                    {{-- `loading="lazy"`: kırk ürünlük bir menüde
                                         misafir ilk ekranı görmek için kırk
                                         fotoğraf beklemez. --}}
                                    <img class="qr-menu-item-image"
                                         @if (! empty($image['lqip'])) style="background: url({{ $image['lqip'] }}) center / cover no-repeat" @endif
                                         src="{{ $image['sources'][0]['url'] }}"
                                         srcset="{{ $srcset($image['sources']) }}"
                                         sizes="(max-width: 480px) 96px, 128px"
                                         width="{{ $image['width'] }}" height="{{ $image['height'] }}"
                                         alt="{{ $image['altText'] }}"
                                         loading="lazy" decoding="async">
                                @endif
                                <span class="qr-menu-item-name">{{ $item['productName'] }}</span>
                                @php($priceLabel = \App\Support\Money\PriceLabel::for((int) $item['priceMinorAmount'], (string) $item['currencyCode']))
                                @if ($priceLabel !== null)
                                    <span class="qr-menu-item-price">{{ $priceLabel }}</span>
                                @endif
                                @if ($isSoldOut)
                                    {{-- Tükendi METİNLE söylenir. Yalnız renk
                                         ya da soluklukla anlatmak, renk
                                         göremeyen misafir için hiçbir şey
                                         anlatmaz (WCAG 1.4.1). --}}
                                    <span class="qr-menu-item-sold-out-note">{{ $text('soldOut') }}</span>
                                @endif
                                @if (! empty($item['description']))
                                    <span class="qr-menu-item-description">{{ $item['description'] }}</span>
                                @endif
                                @if (! empty($item['allergens']))
                                    <span class="qr-menu-item-allergens">
                                        @foreach ($item['allergens'] as $allergen)
                                            <span class="qr-menu-item-allergen-chip">{{ $allergen }}</span>
                                        @endforeach
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    @endif
</main>
{{-- Betik gövdesindeki sabitler de KULLANICI METNİDİR (`docs/85`).
     Harita JSON olarak basılır; betik onu okur ve tek bir cümle bile
     şablonda kalmaz. --}}
<script type="application/json" id="guest-text" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($gt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var TEXT = {};

        try {
            var textNode = document.getElementById('guest-text');
            TEXT = textNode ? JSON.parse(textNode.textContent || '{}') : {};
        } catch (error) {
            // Metin okunamadıysa arayüz sessiz kalır; menü yine görünür.
        }

        function say(key) {
            return TEXT[key] || '';
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/public-diner-sw.js', { scope: '/menu/' });
            navigator.serviceWorker.register('/public-diner-sw.js', { scope: '/q/' });
        }

        var installButton = document.getElementById('pwa-install-button');
        var installStatus = document.getElementById('pwa-install-status');
        var offlineStatus = document.getElementById('pwa-offline-status');
        var deferredInstallPrompt = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredInstallPrompt = event;
            installButton.hidden = false;
        });

        installButton.addEventListener('click', function () {
            if (!deferredInstallPrompt) {
                return;
            }

            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function (choice) {
                installStatus.textContent =
                        choice.outcome === 'accepted' ? say('installAccepted') : say('installDismissed');
                deferredInstallPrompt = null;
                installButton.hidden = true;
            });
        });

        window.addEventListener('appinstalled', function () {
            installStatus.textContent = say('installed');
            installButton.hidden = true;
        });

        function updateOfflineStatus() {
            offlineStatus.textContent = navigator.onLine ? '' : say('offline');
        }

        window.addEventListener('online', updateOfflineStatus);
        window.addEventListener('offline', updateOfflineStatus);
        updateOfflineStatus();

        var searchInput = document.getElementById('menu-search');
        var searchStatus = document.getElementById('menu-search-status');
        var categories = Array.prototype.slice.call(document.querySelectorAll('[data-category]'));

        if (searchInput && searchStatus) {
            searchInput.addEventListener('input', function () {
                var query = searchInput.value.trim().toLocaleLowerCase('tr');
                var visibleCount = 0;

                categories.forEach(function (categorySection) {
                    var items = Array.prototype.slice.call(categorySection.querySelectorAll('[data-item]'));
                    var categoryHasMatch = query === '';

                    items.forEach(function (item) {
                        var name = (item.getAttribute('data-item-name') || '').toLocaleLowerCase('tr');
                        var matches = query === '' || name.indexOf(query) !== -1;
                        item.hidden = !matches;
                        if (matches) {
                            visibleCount += 1;
                            categoryHasMatch = true;
                        }
                    });

                    categorySection.hidden = !categoryHasMatch;
                });

                if (query === '') {
                    searchStatus.textContent = '';
                } else if (visibleCount === 0) {
                    searchStatus.textContent = say('searchNoMatch');
                } else {
                    searchStatus.textContent = say('searchMatched').replace('{count}', String(visibleCount));
                }

                reportNoResults(query, visibleCount);
            });
        }

        /*
            MENÜ MÜHENDİSLİĞİ ÖLÇÜMÜ — `docs/84`.

            Ölçüm misafirin işi DEĞİLDİR: hiçbir hata onun ekranında
            görünmez, hiçbir istek sayfanın açılmasını bekletmez.
        */
        var menuKey = (document.querySelector('main') || {}).dataset
            ? document.querySelector('main').dataset.menuKey
            : null;

        if (!menuKey) {
            return;
        }

        function send(events) {
            if (!events.length) {
                return;
            }

            var body = JSON.stringify({ menuKey: menuKey, events: events });

            /*
                `sendBeacon` sayfa kapanırken bile gider ve sayfayı
                bekletmez. Yoksa `fetch` ile denenir; ikisi de yoksa ölçüm
                sessizce yapılmaz — misafirin menüsü her hâlükârda açılır.
            */
            try {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('/q/events', new Blob([body], { type: 'application/json' }));

                    return;
                }

                fetch('/q/events', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    keepalive: true,
                }).catch(function () {});
            } catch (error) {
                // Ölçüm best-effort.
            }
        }

        var pendingViews = [];
        var reportedItems = {};

        function flushViews() {
            var batch = pendingViews;
            pendingViews = [];
            send(batch);
        }

        if ('IntersectionObserver' in window) {
            var timers = {};

            var observer = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        var id = entry.target.getAttribute('data-menu-item-id');

                        if (!id || reportedItems[id]) {
                            return;
                        }

                        if (!entry.isIntersecting) {
                            // Kaydırırken hızla geçilen satır SAYILMAZ.
                            window.clearTimeout(timers[id]);
                            delete timers[id];

                            return;
                        }

                        timers[id] = window.setTimeout(function () {
                            reportedItems[id] = true;
                            observer.unobserve(entry.target);
                            pendingViews.push({ type: 'item_view', menuItemId: Number(id) });
                        }, 1000);
                    });
                },
                // Satırın en az YARISI görünmeli: listede olması yetmez,
                // yoksa kırk ürünlük menüde kırk görüntülenme sayılırdı ve
                // "hangi ürün ilgi çekiyor" sorusu cevapsız kalırdı.
                { threshold: 0.5 },
            );

            Array.prototype.slice
                .call(document.querySelectorAll('[data-menu-item-id]'))
                .forEach(function (node) {
                    observer.observe(node);
                });

            // Toplu gönderim: kırk ürün için kırk istek atmak, misafirin
            // hücresel bağlantısını menünün kendisinden çok yorardı.
            window.setInterval(flushViews, 5000);
            window.addEventListener('pagehide', flushViews);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    flushViews();
                }
            });
        }

        var searchTimer = null;
        var reportedTerms = {};

        function reportNoResults(query, visibleCount) {
            window.clearTimeout(searchTimer);

            if (query === '' || visibleCount !== 0) {
                return;
            }

            /*
                Yazarken geçilen ara adımlar gönderilmez: "kar", "kari",
                "karid" hepsi sonuçsuzdur ama misafirin aradığı şey
                "karides"tir. Yarım saniye beklemek, niyeti aramadan ayırır.
            */
            searchTimer = window.setTimeout(function () {
                if (reportedTerms[query]) {
                    return;
                }

                reportedTerms[query] = true;
                send([{ type: 'search_no_results', term: query }]);
            }, 500);
        }
    })();
</script>
</body>
</html>
