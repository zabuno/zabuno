{{-- KURUMSAL KABUĞUN ALT ÇUBUĞU — TEK tanım (`docs/100` §2).

     Bağlantılar üst çubukla AYNI kaynaktan gelir (`SiteNavigation`). Ayrı
     listeler olsaydı, bir sayfa yayına alındığında biri güncellenir diğeri
     unutulurdu — altbilgi tam olarak unutulan yerdir.

     Her bağlantı GERÇEK bir sayfadır (`docs/64` §4): bağlantının varlığı,
     arkasında çalışan bir sayfa olduğu iddiasıdır. --}}
<footer class="site-footer">
    <div class="site-shell-inner site-footer-inner">
        <div class="site-footer-brand">
            <span class="site-footer-brand-name">{{ $st['brand'] }}</span>
            <span>{{ $st['footerTagline'] }}</span>
            <span>&copy; {{ now()->year }} {{ $st['brand'] }}</span>
        </div>

        @foreach ($nav['footer'] as $group)
            <nav
                aria-label="{{ $group['label'] }}"
                data-nav-group="{{ $group['id'] }}"
                class="site-footer-group"
            >
                <span class="site-footer-heading">{{ $group['label'] }}</span>
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['href'] }}" class="site-footer-link">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endforeach
    </div>
</footer>
