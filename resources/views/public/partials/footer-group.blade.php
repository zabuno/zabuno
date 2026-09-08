{{-- ALTBİLGİDEKİ BİR BAĞLANTI GRUBU — TEK çizim (FF-237, `docs/141` §3).

     Altbilgi altı satır taşıyor ve üç ayrı satırda bağlantı grubu çiziliyor:
     ürün/şirket/hesap satırı, yasal satır ve kütükten türeyen pSEO bandı.
     Üçünü üç ayrı yerde yazmak, birine eklenen bir ölçünün ötekilere
     eklenmemesiyle biterdi — altbilginin tamamının tek bir dosyadan gelmesinin
     sebebiyle aynı sebep (`SHELL-SINGLE-SOURCE-01`).

     ── HER GRUP BİR `<details>` ─────────────────────────────────────────

     Açık ya da kapalı başlaması `collapsed` bayrağından gelir ve o karar
     `SiteNavigation::OPEN_ITEM_CEILING`de yaşar: dörtten çok maddesi olan
     grup kapalı başlar. Karar GENİŞLİĞE bağlı DEĞİLDİR (`MP-05`); aynı grup
     her ekranda aynı davranır.

     Katlamak GİZLEMEK DEĞİLDİR: `<details>` içeriği sunucu HTML'inde durur,
     arama motoru ve betiği engellenmiş ziyaretçi hepsini görür
     (`docs/118` E8). `max-*` ile bastırılan ya da "mobilde gizlenen" tek bir
     şey yok — gizlenen şey yine indirilir, yine odaklanılabilir.

     Başlık `<summary>`nin İÇİNDE bir `<h2>`dir: HTML `summary`nin başlık
     içeriği taşımasına izin verir, dolayısıyla belge ana hattı katlanmış
     hâlde de ayakta kalır. Başlığı dışarı almak, açılır kapanır düğmeyi
     başlıksız bırakırdı. --}}
<nav aria-label="{{ $group['label'] }}" data-nav-group="{{ $group['id'] }}" class="site-footer-group">
    <details class="site-footer-fold" @if (! $group['collapsed']) open @endif>
        <summary class="site-footer-fold-toggle">
            <h2 class="site-footer-heading">{{ $group['label'] }}</h2>
            <x-phosphor name="caret-down" />
        </summary>

        <ul class="site-footer-list">
            @foreach ($group['items'] as $item)
                <li>
                    <a
                        href="{{ $item['href'] }}"
                        class="site-footer-link"
                        @if ($item['emphasis']) data-emphasis="true" @endif
                    >{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </details>
</nav>
