{{-- MİSAFİR YÜZEYİNİN ORTAK STİL KÖKÜ — FF-175, `docs/113` §6.3.

     NEDEN TEK DOSYA. `--qr-bg`, `--qr-fg`, `--qr-border` ve kardeşleri bugüne
     kadar misafirin gördüğü BEŞ ayrı şablonda ayrı ayrı tanımlıydı. Kopya
     ilk gün aynı görünür; ayrışması ancak biri düzeltilip diğeri unutulduğunda
     fark edilir. `docs/37` §2.1'in merkezîlik testi — bir tonu tek yerde
     değiştirmek onu tüketen her yüzeyi değiştirmelidir — misafir yüzeyi için
     bugüne kadar BAŞARISIZDI. Bu dosya o testi geçirir.

     NEDEN HÂLÂ SATIR İÇİ. Ayrı bir `.css` dosyası bir AĞ İSTEĞİ demektir ve
     misafir sayfasının bugünkü tamamı TEK istekte iniyor (`docs/113` §8:
     `@vite` bu sayfada hiç geçmiyor, JS paketi sıfır bayt). İkinci bir istek,
     masadaki zayıf hücresel bağlantıda menünün açılma anını geciktirirdi.
     Çerçevenin ölçülebilir sözü budur ve `GuestMenuDesignLanguageTest` onu
     dondurur.

     NEDEN `--qr-*` ADI KALDI. Bu paket token zincirini AEP'e bağlamıyor;
     `docs/113` §12'de o ayrı bir adımdır ve kontrast kapısını (§5.2)
     bekliyor. Adı bugün değiştirmek, yarınki bağlamayı iki kez yapmak
     olurdu. Roller yine de kaynağın rolleriyle (§2.2 eşlemesi) hizalandı:
     `surface` / `surface-2` / `sunken` / `fg-2` / `border-strong` /
     `accent-tint` artık VAR ve kaynağın kart dili bunlara yaslanıyor. --}}
<style nonce="{{ $cspNonce ?? '' }}">
    :root {
        color-scheme: light dark;

        /* Yüzey merdiveni — kaynağın --bg / --surface / --surface2 / --sunken
           rolleri. Kaynak YÜKSEKLİK (gölge) kullanıyor; depo TON kullanıyor
           (`app.css`: "Flat 2.0 gölge yerine TON kullanır"). Kartı zeminden
           ayıran şey burada bir gölge değil, bir kenarlık ve bir ton farkıdır. */
        --qr-bg: #f7f7f8;
        --qr-surface: #ffffff;
        --qr-surface-2: #f1f2f4;
        --qr-sunken: #e8eaed;

        --qr-fg: #1f2937;
        --qr-fg-2: #4b5563;
        --qr-muted: #6b7280;

        --qr-border: rgba(107, 114, 128, 0.25);
        --qr-border-strong: rgba(107, 114, 128, 0.45);

        /* VURGU: BU PAKET ÜRÜNÜN KENDİ MÜREKKEBİNİ KULLANIR.

           Kontrast rampası artık VAR (FF-174): kiracı bir ton veriyor, ürün
           beş rollü rampayı türetip ölçüyor ve kanıtı yayına donduruyor
           (`MenuIdentity::$skin`, `App\Domain\Branding\BrandSkin`). Yani
           kiracı rengini metin ve zemin rolüne bağlamanın önündeki engel
           kalktı; rampayı bu yüzeyde TÜKETMEK bir sonraki adımdır.

           Bu paket onu bilerek yapmıyor: rampa ile misafir menüsünün yeni
           düzeni aynı anda yazıldı ve ikisini tek pakette birleştirmek,
           kırılan bir şeyin hangisinden geldiğini ölçülemez hâle getirirdi.
           O adıma kadar vurgu ürünün kendi mürekkebidir — beyaz üstünde
           14.6:1, koyu temada da aynı — ve rampa geldiğinde değişecek olan
           yer tek bir satırdır, çünkü token burada tanımlı.

           Kaynağın terracotta `--accent` değeri ayrıca bir MARKA kararıdır ve
           sahibinindir; rampadan bağımsız olarak sorulur. */
        --qr-accent: #1f2937;
        --qr-accent-fg: #ffffff;
        --qr-accent-tint: rgba(31, 41, 55, 0.08);

        /* Alerjen UYARI rengini kullanır, HATA rengini değil: alerjen bir
           arıza değil, dikkat edilecek bir bilgidir. */
        --qr-warn: #92400e;
        --qr-warn-tint: #fdf1e3;

        --qr-chip-bg: rgba(107, 114, 128, 0.12);

        /* Kaynağın kimliğini taşıyan iki eksen yarıçap ve yuvarlaklıktır
           (`docs/113` §2.3). Depo tavanı 8 px'tir ve bu paket o tavanı
           DELMİYOR: misafir yüzeyi AEP token zincirini henüz tüketmiyor
           (§2.5). Zincir bağlandığında bu değer bir sabit değil, bir VARYANT
           seçimi olacaktır (`variants.css`, §5.3) — bugün kaynağın değeri
           tek yerde durur ve o gün tek yerden değişir. */
        --qr-radius: 16px;
        --qr-radius-s: 12px;

        /* Kaynağın `--tap` değeri ile deponun `--aep-hit-area` değeri AYNI
           yerde buluşuyor (`docs/113` §2.1). Misafir yüzeyi tek yoğunluktur:
           bu sayı hiçbir varyantta küçülmez (§5.3). */
        --qr-tap: 44px;
        --qr-stick: 12px;
        --qr-gutter: clamp(0.75rem, 4vw, 1.5rem);

        --qr-ease: cubic-bezier(0, 0, 0.2, 1);
        --qr-d1: 120ms;
        --qr-d2: 180ms;
    }

    :root.dark,
    :root[data-theme="dark"] {
        --qr-bg: #111827;
        --qr-surface: #1b2331;
        --qr-surface-2: #232d3d;
        --qr-sunken: #161e2b;

        --qr-fg: #f9fafb;
        --qr-fg-2: #d1d5db;
        --qr-muted: #9ca3af;

        --qr-border: rgba(156, 163, 175, 0.3);
        --qr-border-strong: rgba(156, 163, 175, 0.5);

        --qr-accent: #f9fafb;
        --qr-accent-fg: #111827;
        --qr-accent-tint: rgba(249, 250, 251, 0.12);

        --qr-warn: #fbbf24;
        --qr-warn-tint: #332616;

        --qr-chip-bg: rgba(156, 163, 175, 0.16);
    }

    /* Aşağıdaki sıfır bir SINIR DEĞİL, sınırın kaldırılmasıdır: esnek ve grid
       öğelerinin varsayılan `auto` taban genişliği, uzun bir ürün adının
       satırı taşırmasına yol açıyor. Sıfırlamak, metnin kırpılabilmesini ve
       kutunun kendi payına razı olmasını sağlar — 320'de taşmayı önleyen ilk
       kural budur. */
    * {
        box-sizing: border-box;
        min-width: 0;
        -webkit-tap-highlight-color: transparent;
    }

    html {
        -webkit-text-size-adjust: 100%;
    }

    /* `hidden` GERÇEKTEN GİZLESİN.

       Tarayıcının kendi kuralı `[hidden]{display:none}` YAZAR sayfasının
       kurallarından zayıftır. Kart `display:flex`, liste `display:grid`
       olduğu anda arama betiğinin `item.hidden = true` ataması sessizce
       etkisiz kalır: misafir "karides" yazar, eşleşmeyen satırlar ekranda
       durmaya devam eder ve arama çalışmıyor görünür. Aynı gerekçe kurulum
       düğmesinde de yazılıydı; kural buraya alınıp TEK yerde bırakıldı. */
    [hidden] {
        display: none !important;
    }

    body {
        margin: 0;
        padding: 0;
        width: 100%;
        max-width: 100%;
        background: var(--qr-bg);
        color: var(--qr-fg);
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        font-size: 1rem;
        line-height: 1.45;
    }

    /* ZABUNO ÇERÇEVESİ — `docs/113` §6.

       Görünmezliği bir `display:none` DEĞİLDİR. `display:contents` ile kutu
       hiç OLUŞMAZ: çerçeve ne yer kaplar, ne `z-index` tüketir, ne de içindeki
       yapışkan başlığın `top` hesabına karışır. Yarın buraya zabuno başlığı ve
       altbilgisi girdiğinde, o gün gerçek bir kutu haline gelecek olan yer
       bellidir ve dört misafir yüzeyi için TEK yerdir. */
    [data-zabuno] {
        display: contents;
    }

    /* DOKUNMATİK ÖNCE: hover değil `:active`. Telefonda `:hover` ya hiç
       gelmez ya da dokunuştan SONRA takılı kalır; parmağın altındaki geri
       bildirim bastırma hareketidir. */
    .qr-press {
        transition: transform var(--qr-d1) var(--qr-ease), background var(--qr-d1) var(--qr-ease);
    }

    .qr-press:active {
        transform: scale(0.97);
    }

    @media (hover: hover) and (pointer: fine) {
        /* İşaretçisi olan cihaz farkı SUNUCUDA değil burada çözülür
           (`docs/113` §8): işaretleme her cihazda aynı iner, kural yalnız
           gerçekten faresi olan cihazda uygulanır. Paket bölmenin maliyeti
           misafir yüzeyinde negatiftir. */
        .qr-press:hover {
            background: var(--qr-surface-2);
        }
    }

    a {
        color: inherit;
    }

    button {
        font: inherit;
        color: inherit;
        cursor: pointer;
        touch-action: manipulation;
    }

    /* Odak halkası MARKAYA BAĞLANMAZ (`docs/113` §2.3, `DS-AEP-INK-11`):
       kiracının seçtiği açık bir ton, odak halkasını görünmez yapabilirdi ve
       klavyeyle gezen misafir nerede olduğunu kaybederdi. */
    a:focus-visible,
    button:focus-visible,
    input:focus-visible,
    [tabindex]:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 1ms !important;
            transition-duration: 1ms !important;
        }
    }
</style>
