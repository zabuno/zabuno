{{-- MASTERPAGE FOOTER (`docs/100` §2): ürün gezintisi, yasal gezinti,
     marka satırı. Her bağlantı GERÇEK bir sayfadır (`docs/64` §4). --}}
<footer class="border-t border-border">
    <div class="mx-auto flex max-w-5xl flex-wrap items-start justify-between gap-6 px-4 py-6 text-sm text-fg-secondary">
        <div class="flex flex-col gap-1">
            <span class="font-semibold text-fg">{{ $st['brand'] }}</span>
            <span>{{ $st['footerTagline'] }}</span>
            <span>&copy; {{ now()->year }} {{ $st['brand'] }}</span>
        </div>
        <nav aria-label="{{ $st['footerProduct'] }}" class="flex flex-col gap-1">
            <span class="font-semibold text-fg">{{ $st['footerProduct'] }}</span>
            <a href="/pricing" class="hover:underline">{{ $st['navPricing'] }}</a>
            <a href="/help" class="hover:underline">{{ $st['navHelp'] }}</a>
            <a href="/contact" class="hover:underline">{{ $st['navContact'] }}</a>
        </nav>
        <nav aria-label="{{ $st['footerLegal'] }}" class="flex flex-col gap-1">
            <span class="font-semibold text-fg">{{ $st['footerLegal'] }}</span>
            <a href="/terms" class="hover:underline">{{ $st['footerTerms'] }}</a>
            <a href="/privacy" class="hover:underline">{{ $st['footerPrivacy'] }}</a>
            <a href="/kvkk" class="hover:underline">{{ $st['footerKvkk'] }}</a>
        </nav>
    </div>
</footer>
