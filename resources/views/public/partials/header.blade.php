{{-- MASTERPAGE HEADER (`docs/100` §2). Sayfa şablonları buraya dokunmaz.

     Çıpalar ana sayfadaki GERÇEK başlıklara gider (`docs/38` §4); gerçek
     sayfası olan şey (Pricing, Help, Contact) her yerde gerçek yoldur.
     Metin katalogdan: Türkçe tarayıcı Türkçe okur. --}}
<header class="border-b border-border bg-surface">
    <nav aria-label="Primary" class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-2.5">
        <a href="/" class="text-xl font-semibold text-fg">Zabuno</a>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <a href="{{ $anchorPrefix }}#features" class="text-fg-secondary hover:underline">{{ $st['navFeatures'] }}</a>
            <a href="{{ $anchorPrefix }}#how-it-works" class="text-fg-secondary hover:underline">{{ $st['navHowItWorks'] }}</a>
            <a href="/pricing" class="text-fg-secondary hover:underline">{{ $st['navPricing'] }}</a>
            <a href="/help" class="text-fg-secondary hover:underline">{{ $st['navHelp'] }}</a>
            <a href="/contact" class="text-fg-secondary hover:underline">{{ $st['navContact'] }}</a>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="/login" class="inline-flex items-center rounded border border-border px-3 py-1.5 font-medium text-fg hover:underline">{{ $st['navLogin'] }}</a>
            <a href="/register" class="inline-flex items-center rounded bg-action px-3 py-1.5 font-medium text-white hover:underline">{{ $st['navRegister'] }}</a>
        </div>
    </nav>
</header>
