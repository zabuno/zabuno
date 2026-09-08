{{-- KURUMSAL KABUĞUN ÜST ÇUBUĞU — TEK tanım (`docs/100` §2, `docs/136`).

     Bu dosya deponun tek kurumsal `<header>`'ıdır ve bir test onu böyle
     donduruyor (`SHELL-SINGLE-SOURCE-01`). Burada yapılan bir değişiklik,
     kütükten çizilen sayfalar dahil her kurumsal adreste görünür.

     ── daisyUI (`docs/136`) ─────────────────────────────────────────────

     Sahibin kararı (2026-09-08): *"daisyUI kullan, baştan yarat."* Çubuk
     `dz-navbar`, menü `dz-dropdown` + `dz-menu` üzerine yeniden yazıldı.
     Önek `dz-` zorunludur ve gerekçesi `resources/css/daisy-theme.css`te
     ölçülmüş olarak duruyor: öneksiz daisyUI, panelin `flowbite-react`
     sınıflarıyla aynı isim uzayını paylaşırdı.

     ── DAR EKRAN TABANDIR (`docs/118` E1) ───────────────────────────────

     320 pikselde bir marka adı, beş gezinti bağlantısı, bir mega menü ve iki
     hesap düğmesi yan yana sığmaz; sararak dizildiklerinde ilk ekranın
     üçte birini içerikten ÖNCE doldururlar. Bu yüzden çubukta yalnız iki
     şey durur — marka ve menü — gerisi bir açılır bölmededir. Kırılma
     noktası jetonu YOK (`MP-05`): düzen 320'den akışkan.

     ── TABAN HTML, TAVAN SERBEST (`docs/118` E8) ────────────────────────

     Eski kural "kabukta betik yok" diyordu. Sahip 2026-09-08'de gevşetti:
     *"gibi bir sınır koymak yanlış."* Yeni sözleşme şu:

         Her gezinti hedefi SUNUCU HTML'inde `<a href>` olarak BULUNUR.
         JavaScript bunun ÜSTÜNE serbestçe ekler.

     `<summary>` bu yüzden hâlâ tabandır: tarayıcının kendi açılır kapanır
     düğmesidir, klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betik
     olmadan açılır. Yarın bir mega menü, arama ya da hareket eklemek
     serbesttir — o eklemeler bu tabanı SİLEMEZ.
     Kapı: `SHELL-SINGLE-SOURCE-04` sayfayı betiksiz çizip her hedefi arar. --}}
<header class="site-header"{!! $lang->chromeAttributes() !!}>
    <div class="dz-navbar site-shell-inner site-header-bar">
        <a href="/" class="site-brand">{{ $st['brand'] }}</a>

        <details class="dz-dropdown site-menu">
            {{-- İkon + sözcük birlikte (`docs/118` E6): ikon tek başına bir
                 etiket değildir, sözcük tek başına bir hedef göstergesi
                 değildir. Açık/kapalı ayrımı iki ayrı Phosphor glifiyle
                 anlatılır ve hangisinin görüneceğini CSS `[open]` seçer —
                 betik gerekmez. --}}
            <summary class="dz-btn site-menu-toggle">
                <x-phosphor name="list" class="site-icon-closed" />
                <x-phosphor name="x" class="site-icon-open" />
                <span>{{ $st['navMenu'] }}</span>
            </summary>

            <div class="dz-dropdown-content site-menu-panel">
                {{-- Gezinti verisi tek kaynaktan gelir (`SiteNavigation`):
                     yayınlanmamış ya da metni yazılmamış bir sayfa buraya HİÇ
                     ulaşmaz, dolayısıyla menüde 404'e giden bir bağlantı
                     bulunamaz (`docs/129` §3 ile aynı karar nesnesi). --}}
                @foreach ($nav['header'] as $group)
                    <nav aria-label="{{ $group['label'] }}" data-nav-group="{{ $group['id'] }}">
                        {{-- `dz-menu` bir LİSTE bekler ve gezinti gerçekten bir
                             listedir: ekran okuyucu "5 öğe" der, klavye
                             kullanıcısı kaçının kaldığını bilir. Eskiden
                             düz `<a>` yığınıydı. --}}
                        <ul class="dz-menu site-menu-group">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a
                                        href="{{ $item['href'] }}"
                                        class="site-menu-link"
                                        @if ($item['emphasis']) data-emphasis="true" @endif
                                    >{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endforeach
            </div>
        </details>
    </div>
</header>
