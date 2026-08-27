<!DOCTYPE html>
{{-- Dil, UYGULAMANIN değil MENÜNÜN dilidir: ürün adlarını restoran kendi
     dilinde yazar. Yanlış `lang`, ekran okuyucunun metni yanlış telaffuz
     etmesine ve arama motorunun sayfayı yanlış dile atamasına yol açar. --}}
<html lang="{{ $contentLocale ?? \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menü</title>
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
    <meta property="og:title" content="Menü">
    <meta property="og:site_name" content="Zabuno">
    <meta name="description" content="Yayınlanan menü — güncel yayınlanmış sürüm.">
    <meta property="og:description" content="Yayınlanan menü — güncel yayınlanmış sürüm.">
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
    <button type="button" id="pwa-install-button" hidden>Uygulamayı yükle</button>
    <span id="pwa-install-status" role="status" aria-live="polite"></span>
    <span id="pwa-offline-status" role="status" aria-live="polite"></span>
</div>
<main role="main">
    @php
        $categories = $snapshot['categories'] ?? [];
        $categoryCount = count($categories);
        $itemCount = 0;
        foreach ($categories as $category) {
            $itemCount += count($category['menuItems'] ?? []);
        }
    @endphp

    <header class="qr-menu-header">
        <h1 class="qr-menu-title">Menü</h1>
        <p class="qr-menu-subtitle">Yayınlanan menü — güncel yayınlanmış sürüm gösteriliyor.</p>
        <p class="qr-menu-summary">{{ $categoryCount }} kategori, {{ $itemCount }} ürün</p>
    </header>

    @if ($categoryCount > 0)
        <nav class="qr-menu-nav" aria-label="Kategoriler">
            @foreach ($categories as $navIndex => $category)
                <a href="#category-{{ $navIndex }}">{{ $category['name'] }}</a>
            @endforeach
        </nav>
    @endif

    <div class="qr-menu-search">
        <label for="menu-search">Menüde ara</label>
        <input type="search" id="menu-search" name="menu-search" autocomplete="off" placeholder="Ürün adı yazın">
        <p id="menu-search-status" role="status" aria-live="polite"></p>
    </div>

    @if ($categoryCount === 0)
        <p class="qr-menu-empty-state">Bu menüde henüz kategori yok.</p>
    @else
        @foreach ($categories as $categoryIndex => $category)
            <section class="qr-menu-category" id="category-{{ $categoryIndex }}" data-category>
                <h2 class="qr-menu-category-name">{{ $category['name'] }}</h2>

                @if (empty($category['menuItems']))
                    <p class="qr-menu-category-empty">Bu kategoride henüz ürün yok.</p>
                @else
                    <ul class="qr-menu-item-list">
                        @foreach ($category['menuItems'] as $item)
                            <li class="qr-menu-item" data-item data-item-name="{{ $item['productName'] }}">
                                <span class="qr-menu-item-name">{{ $item['productName'] }}</span>
                                @php($priceLabel = \App\Support\Money\PriceLabel::for((int) $item['priceMinorAmount'], (string) $item['currencyCode']))
                                @if ($priceLabel !== null)
                                    <span class="qr-menu-item-price">{{ $priceLabel }}</span>
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
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
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
                installStatus.textContent = choice.outcome === 'accepted' ? 'Yükleme kabul edildi.' : 'Yükleme reddedildi.';
                deferredInstallPrompt = null;
                installButton.hidden = true;
            });
        });

        window.addEventListener('appinstalled', function () {
            installStatus.textContent = 'Uygulama yüklendi.';
            installButton.hidden = true;
        });

        function updateOfflineStatus() {
            offlineStatus.textContent = navigator.onLine ? '' : 'Çevrimdışısınız, son görüntülenen menü gösteriliyor.';
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
                    searchStatus.textContent = 'Eşleşen ürün bulunamadı.';
                } else {
                    searchStatus.textContent = visibleCount + ' ürün eşleşti.';
                }
            });
        }
    })();
</script>
</body>
</html>
