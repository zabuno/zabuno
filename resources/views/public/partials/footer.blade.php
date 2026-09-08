{{-- KURUMSAL KABUĞUN ALT ÇUBUĞU — TEK tanım (`docs/100` §2, `docs/136` §6).

     ── Sahibin isteği ve ölçülen gerçek ─────────────────────────────────

     İstek (2026-09-08): *"çoook zengin, çok katmanlı, çok row, çok menu
     grubu, pSEO için footer üzerinde content menus."*

     Ölçüm: içeriği yazılmış on altı kurumsal sayfanın **hiçbiri yayında
     değil** ve kütükteki 386 Türkçe satırın da hiçbiri. Elle yazılmış
     zengin bir ızgara BUGÜN yüzlerce 404'e giden bağlantı demekti — üstelik
     altbilgide, yani kimsenin bakmadığı yerde.

     ── Verilen karar ────────────────────────────────────────────────────

     Zenginlik ELLE YAZILMIYOR, KÜTÜKTEN TÜRÜYOR. Aşağıdaki üç kat:

       1. Marka satırı — her zaman var.
       2. Yaşayan gruplar (`nav['footer']`) — Ürün ve Yasal; bugün dolu.
       3. İÇERİK MENÜLERİ (`nav['content']`) — kütükten türeyen pSEO katı;
          bugün BOŞ ve bu yüzden hiç çizilmiyor.

     Üçüncü kat, sahip bir sayfayı yayına aldığı gün tek bir Blade satırı
     değişmeden belirir. Bağlantıların hepsi ziyaretçinin alacağı HTTP kodunu
     üreten AYNI karardan süzülür (`ResolvePageDelivery`, `docs/129` §3):
     kapı tek cümledir — *altbilgideki her bağlantı 200 döner.*

     Boş grup ÇİZİLMEZ. Başlığı çizilip altı boş kalan bir grup, olmayan bir
     bölümün sözünü vermektir.

     ── 320 piksel ───────────────────────────────────────────────────────

     Çok satırlı bir altbilgi dar ekranın en büyük riskidir. Bu yüzden pSEO
     katı bir `<details>` içindedir ve KAPALI başlar: ilk ekranı doldurmaz,
     parmakla açılır, betiksiz çalışır ve içindeki her bağlantı HTML'de zaten
     durur (`SHELL-SINGLE-SOURCE-04`). Tek kod yolu — medya sorgusuyla
     gizlenen ikinci bir kopya yok (`docs/118` E2). --}}
<footer class="site-footer">
    <div class="site-shell-inner site-footer-inner">
        <div class="site-footer-brand">
            <span class="site-footer-brand-name">{{ $st['brand'] }}</span>
            <span>{{ $st['footerTagline'] }}</span>
            <span>&copy; {{ now()->year }} {{ $st['brand'] }}</span>
        </div>

        <div class="dz-footer site-footer-grid">
            @foreach ($nav['footer'] as $group)
                <nav aria-label="{{ $group['label'] }}" data-nav-group="{{ $group['id'] }}">
                    <h2 class="dz-footer-title site-footer-heading">{{ $group['label'] }}</h2>
                    <ul class="site-footer-list">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="site-footer-link">{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>
    </div>

    @if ($nav['content'] !== [])
        <div class="site-shell-inner">
            <details class="site-footer-content">
                <summary class="site-footer-content-toggle">
                    <span>{{ $st['footerContentMenus'] }}</span>
                    <x-phosphor name="caret-down" />
                </summary>

                <div class="dz-footer site-footer-grid">
                    @foreach ($nav['content'] as $group)
                        <nav aria-label="{{ $group['label'] }}" data-nav-group="{{ $group['id'] }}">
                            <h2 class="dz-footer-title site-footer-heading">{{ $group['label'] }}</h2>
                            <ul class="site-footer-list">
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a href="{{ $item['href'] }}" class="site-footer-link">{{ $item['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach
                </div>
            </details>
        </div>
    @endif
</footer>
