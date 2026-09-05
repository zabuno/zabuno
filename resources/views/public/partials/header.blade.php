{{-- KURUMSAL KABUĞUN ÜST ÇUBUĞU — TEK tanım (`docs/100` §2).

     Bu dosya deponun tek kurumsal `<header>`'ıdır ve bir test onu böyle
     donduruyor (`SHELL-SINGLE-SOURCE-01`). Sahibin talebi buydu: burada
     yapılan bir değişiklik, kütükten çizilen sayfalar dahil her kurumsal
     adreste görünür.

     ── DAR EKRAN TABANDIR (`docs/118` E1) ───────────────────────────────

     320 pikselde bir marka adı, beş gezinti bağlantısı, bir mega menü ve iki
     hesap düğmesi yan yana sığmaz; sararak dizildiklerinde ilk ekranın
     üçte birini içerikten ÖNCE doldururlar. Bu yüzden çubukta yalnız iki
     şey durur — marka ve menü — gerisi bir açılır bölmededir.

     ── NEDEN `<details>`, NEDEN BETİK YOK ───────────────────────────────

     `<summary>` tarayıcının kendi açılır kapanır düğmesidir: klavyeyle
     çalışır, ekran okuyucuya durumunu söyler, betik olmadan açılır. Aynı
     davranışı JavaScript ile yazmak, betiği engellenmiş bir ziyaretçide
     gezinmeyi tamamen öldürürdü — ve kurumsal sitenin en çok okunduğu an,
     betiklerin en çok engellendiği andır.

     Aynı bileşim GENİŞ ekranda da geçerlidir: tek kod yolu, `hover` yok
     (`docs/118` E2 — dokunmada imleç durumu YOKTUR), medya sorgusuyla
     gizlenen ikinci bir kopya yok.

     ── İKON YOK (`docs/118` E6) ─────────────────────────────────────────

     Açık/kapalı durumu bir ikonla değil, iki çizgiyle anlatılıyor: kapalıyken
     artı, açıkken eksi. Çizgi geometridir, ikon kütüphanesi değil. --}}
<header class="site-header">
    <div class="site-shell-inner site-header-bar">
        <a href="/" class="site-brand">{{ $st['brand'] }}</a>

        <details class="site-menu">
            <summary class="site-menu-toggle">{{ $st['navMenu'] }}</summary>

            <div class="site-menu-panel">
                {{-- Gezinti verisi tek kaynaktan gelir (`SiteNavigation`):
                     yayınlanmamış bir sayfa buraya HİÇ ulaşmaz, dolayısıyla
                     menüde 404'e giden bir bağlantı bulunamaz. --}}
                @foreach ($nav['header'] as $group)
                    <nav
                        aria-label="{{ $group['label'] }}"
                        data-nav-group="{{ $group['id'] }}"
                        class="site-menu-group"
                    >
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                class="site-menu-link"
                                @if ($item['emphasis']) data-emphasis="true" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endforeach
            </div>
        </details>
    </div>
</header>
