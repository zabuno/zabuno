{{-- KURUMSAL KABUĞUN ÜST ÇUBUĞU — TEK tanım (`docs/100` §2, `docs/136`, `docs/141`).

     Bu dosya deponun tek kurumsal `<header>`'ıdır ve bir test onu böyle
     donduruyor (`SHELL-SINGLE-SOURCE-01`). Burada yapılan bir değişiklik,
     kütükten çizilen sayfalar dahil her kurumsal adreste görünür.

     ── ÜST ÇUBUK ÜÇ ŞEY TAŞIR (FF-237) ──────────────────────────────────

     Sahibin isteği (2026-09-08): *"Üst çubuk da olgunlaşsın: marka, gezinti,
     hesap eylemleri."*

     FF-232'de çubukta iki şey vardı — marka ve menü — ve hesap eylemleri
     menünün İÇİNDEYDİ. Ölçülebilir sonucu şuydu: kaydolmaya karar vermiş bir
     ziyaretçi, o kararı uygulamak için önce bir menü açmak zorundaydı. Bir
     dönüşüm eyleminin önüne bir dokunuş koymak, onu bir seçenek olmaktan
     çıkarıp bir arayışa çevirir.

     Şimdi çubukta marka, birincil hesap eylemi ve menü var; menü ise
     gezintiyi ve ikincil hesap eylemini (`Log in`) taşır.

     ── DAR EKRAN TABANDIR (`docs/118` E1) ───────────────────────────────

     ÖLÇÜLDÜ (320×480, gerçek Chrome): marka 62px + iki hesap düğmesi 211px
     + menü düğmesi 95px = 384px, kullanılabilir genişlik 296px. Çubuk ÜÇ
     satıra çıkıyor ve 157 piksel — ilk ekranın üçte biri — içerikten ÖNCE
     doluyordu.

     Bu yüzden çubuğa yalnız BİR eylem çıktı (`/register`), öteki bölmede
     kaldı; dolgu da `--space-3`ten `--space-2`ye indi. Sonuç 52 piksel, tek
     satır. Küçülen şey HEDEF değil ÖLÜ ALAN: yükseklik 44 pikselde kaldı.

     Çubuk yine de `flex-wrap` taşır: daha uzun bir dildeki etiket sığmazsa
     sarar — taşmaz. Kırılma noktası jetonu YOK (`MP-05`).

     ── TABAN HTML, TAVAN SERBEST (`docs/118` E8) ────────────────────────

         Her gezinti hedefi SUNUCU HTML'inde `<a href>` olarak BULUNUR.
         JavaScript bunun ÜSTÜNE serbestçe ekler.

     `<summary>` bu yüzden hâlâ tabandır: tarayıcının kendi açılır kapanır
     düğmesidir, klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betik
     olmadan açılır.
     Kapı: `SHELL-SINGLE-SOURCE-04` sayfayı betiksiz çizip her hedefi arar. --}}
<header class="site-header"{!! $lang->chromeAttributes() !!}>
    <div class="dz-navbar site-shell-inner site-header-bar">
        <a href="/" class="site-brand">{{ $st['brand'] }}</a>

        <div class="site-header-actions">
            {{-- BİRİNCİL EYLEM ÇUBUKTA, İKİNCİSİ BÖLMEDE.

                 Hangisinin çubukta kalacağı bir zevk değil bir sıra sorusu:
                 tanıtım sitesini ilk kez açan kişinin henüz hesabı yoktur.
                 Aynı adres bölmede TEKRAR edilmiyor — iki bağlantı aynı yere
                 giderse ziyaretçi hangisinin doğru olduğunu bilemez ve ekran
                 okuyucu aynı hedefi iki kez okur (`HEADER-ACTIONS-02`). --}}
            @foreach ($nav['headerActions'] as $group)
                <nav aria-label="{{ $group['label'] }}" data-nav-group="{{ $group['id'] }}" class="site-header-cta-group">
                    @foreach ($group['items'] as $item)
                        <a
                            href="{{ $item['href'] }}"
                            class="dz-btn site-header-cta"
                            @if ($item['emphasis']) data-emphasis="true" @endif
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @endforeach

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
                            {{-- BAŞLIK GÖRÜNÜR OLDU (FF-237).

                                 FF-232'de grup adı yalnız `aria-label`daydı:
                                 ekran okuyucu "Primary" ve "Account" diye iki
                                 bölge duyuyordu, GÖZLE bakan biri ise ayrımı
                                 hiç görmüyordu — sekiz bağlantı tek bir yığın
                                 gibi akıyordu. Aynı bilgiyi iki duyuya birden
                                 vermek, ikisinden birini seçmekten dürüsttür. --}}
                            <h2 class="site-menu-heading">{{ $group['label'] }}</h2>

                            {{-- `dz-menu` bir LİSTE bekler ve gezinti gerçekten bir
                                 listedir: ekran okuyucu "5 öğe" der, klavye
                                 kullanıcısı kaçının kaldığını bilir. --}}
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
    </div>
</header>
