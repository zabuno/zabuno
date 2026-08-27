<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Tema, ilk boyamadan ÖNCE uygulanmalı; aksi hâlde koyu tema seçmiş
         bir kullanıcı her sayfada bir an beyaz ekran görür. Bu yüzden
         herhangi bir stil bağlantısından önce gelir. --}}
    @include('partials.theme-bootstrap')
    @include('partials.analytics', ['analyticsContext' => ['zabuno_surface' => 'marketing']])
    <title>@yield('title') — Zabuno</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Zabuno">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-surface text-fg">
{{-- Bu sayfalar SUNUCUDA üretilir ve React paketini hiç yüklemez.
     Sebep ölçüldü: istemcide üretildiklerinde bir tarayıcı botu 1.736 baytlık
     boş bir kabuk görüyordu — yani ürünün kendi tanıtımı arama motorunda ve
     JavaScript çalıştırmayan AI botlarında görünmüyordu. --}}
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-action focus:px-4 focus:py-2 focus:text-white">
    Skip to main content
</a>

<nav aria-label="Primary" class="border-b border-border bg-surface px-2 py-2.5">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4">
        <a href="/" class="text-xl font-semibold text-fg">Zabuno</a>
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            {{-- Bunlar aynı belgedeki başlıklara giden GERÇEK çıpalardır —
                 fragment'ın meşru kullanımı (docs/38 §4). --}}
            <a href="{{ $anchorPrefix }}#features" class="text-fg-secondary hover:underline">Features</a>
            <a href="{{ $anchorPrefix }}#how-it-works" class="text-fg-secondary hover:underline">How it works</a>
            <a href="{{ $anchorPrefix }}#pricing" class="text-fg-secondary hover:underline">Pricing</a>
            <a href="{{ $anchorPrefix }}#faq" class="text-fg-secondary hover:underline">FAQ</a>
            @if ($anchorPrefix === '')
                <a href="#contact" class="text-fg-secondary hover:underline">Contact</a>
            @endif
        </div>
    </div>
</nav>

<p class="mx-auto max-w-5xl px-4 py-2 text-xs text-fg-muted">{{ $coreModuleCount }}/16 modules registered</p>

@yield('content')

<footer class="border-t border-border">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-fg-secondary">
        <span>&copy; {{ now()->year }} Zabuno</span>
        <nav aria-label="Legal" class="flex flex-wrap gap-x-4 gap-y-2">
            <a href="/terms" class="hover:underline">Terms</a>
            <a href="/privacy" class="hover:underline">Privacy</a>
            <a href="/kvkk" class="hover:underline">KVKK</a>
        </nav>
        <a href="/#contact" class="hover:underline">Contact</a>
    </div>
</footer>
</body>
</html>
