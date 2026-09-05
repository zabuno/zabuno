<!DOCTYPE html>
@php
    /* Restoran kimliği YAYIN SNAPSHOT'INDAN okunur (`docs/75`, P0-03).
       Canlı sorgudan okunsaydı, şubenin adı değiştiği gün geçmiş bir yayın
       da sessizce değişirdi.

       Kimlik alanı EKLENMEDEN önce yayınlanmış menüler hâlâ vardır; onlar
       için sunucunun canlı olarak bildiği ada düşülür. Donmuş bir değer
       YOKSA donmuşluk ihlali de yoktur. */
    $identity = isset($snapshot['identity']) && is_array($snapshot['identity'])
        ? \App\Domain\Publication\MenuIdentity::fromSnapshot($snapshot['identity'])
        : null;

    /* `srcset` metnini PHP kurar: öznitelik içine kaçırılan bir döngü,
       şablonu okunmaz ve kırılgan yapar. */
    $srcset = static fn (array $sources): string => implode(', ', array_map(
        static fn (array $source): string => $source['url'].' '.$source['width'].'w',
        $sources,
    ));

    /* Paylaşım önizlemesinin açıklaması. WhatsApp'ta bağlantıyı gören kişi
       ÖNCE bunu okur; "yayınlanmış sürüm" orada hiçbir şey anlatmaz.
       Kimlik biliniyorsa şube ve adres okunur. */
    $documentDescription = static function (?\App\Domain\Publication\MenuIdentity $identity): string {
        if ($identity === null || $identity->brandName === '') {
            return 'Yayınlanan menü — güncel yayınlanmış sürüm.';
        }

        $parts = array_values(array_filter([
            $identity->locationName,
            $identity->addressLine,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === []
            ? $identity->brandName.' — menü'
            : $identity->brandName.' · '.implode(' · ', $parts);
    };

    /* "Bugün tükendi" — donmuş menünün üstündeki tebeşir notu (`docs/82`).
       Yayından bağımsız okunur; snapshot değişmez. */
    $soldOut = array_flip(array_map('intval', $outOfStockItemIds ?? []));

    $categories = $snapshot['categories'] ?? [];
    $categoryCount = count($categories);
    $itemCount = 0;

    foreach ($categories as $category) {
        $itemCount += count($category['menuItems'] ?? []);
    }

    /* FİLTRE EKSENLERİ (FF-177) — SABİT LİSTEDEN DEĞİL, YAYINDAN DOĞAR.

       Sabit bir alerjen listesi çizmek, bu menüde hiç geçmeyen bir alerjeni
       misafire seçtirirdi; seçim hiçbir şeyi elemez ve misafir filtrenin
       bozuk olduğunu sanardı. Anahtar küçük harftir (istemci onunla eşleşir),
       gösterilen etiket ise restoranın kendi yazdığı hâlidir. */
    $declaredAllergens = [];

    foreach ($categories as $category) {
        foreach ($category['menuItems'] ?? [] as $item) {
            foreach ($item['allergens'] ?? [] as $allergen) {
                $label = trim((string) $allergen);

                if ($label !== '') {
                    $declaredAllergens[mb_strtolower($label, 'UTF-8')] = $label;
                }
            }
        }
    }

    ksort($declaredAllergens);

    /* Süzülebilir eksenler SATIRIN İÇİNE yazılır ve şablon onları yalnız
       okur.

       HESAP BURADA, DÖNGÜDE DEĞİL — ve bu bir üslup tercihi değil, ölçülmüş
       bir zorunluluk. Blade'in blok biçimli PHP yönergesi ile tek satırlık
       biçimi aynı düzenli ifadeyle eşleşiyor: ürün döngüsüne ikinci bir
       blok koyulduğu anda derleyici, sayfanın başındaki tek satırlık
       kullanımla oradaki blok sonunu eşleştirdi ve aradaki bütün sayfayı
       ham PHP sandı. Sonuç beyaz ekrandı; bir kez yaşandı, yeri burası. */
    foreach ($categories as $categoryKey => $category) {
        foreach ($category['menuItems'] ?? [] as $itemKey => $item) {
            /* Alerjen ekseni. Ayırıcı BAŞTA VE SONDA da bulunur: `|süt|`
               araması `|süt ürünleri|` ile yanlışlıkla eşleşmesin diye. */
            $keys = [];

            foreach ($item['allergens'] ?? [] as $allergen) {
                $label = trim((string) $allergen);

                if ($label !== '') {
                    $keys[] = mb_strtolower($label, 'UTF-8');
                }
            }

            $categories[$categoryKey]['menuItems'][$itemKey]['filterAllergens'] =
                $keys === [] ? '' : '|'.implode('|', array_unique($keys)).'|';

            /* Fiyat ekseni MİSAFİRİN GÖRDÜĞÜ BİRİMDEDİR, kuruşta değil:
               filtre kutusuna "185" yazan misafir 18500 yazmak zorunda
               kalmamalı. Ondalık basamak para biriminin KENDİSİNDEN gelir —
               sabit 100'e bölmek yende ve dinarda yanlış fiyat üretirdi
               (`MoneyFormatter`).

               Para birimi ÇÖZÜLEMİYORSA eksen HİÇ BASILMAZ. O satırın fiyatı
               zaten gösterilmiyor; onu bir aralığın dışında saymak,
               bilmediğimiz bir şeyi biliyormuş gibi davranmak olurdu. */
            $currency = trim((string) ($item['currencyCode'] ?? ''));
            $priceAxis = null;

            if ($currency !== '') {
                try {
                    $digits = \App\Domain\Money\MoneyFormatter::fractionDigitsFor($currency);
                    $priceAxis = number_format(
                        ((int) ($item['priceMinorAmount'] ?? 0)) / (10 ** $digits),
                        $digits,
                        '.',
                        '',
                    );
                } catch (\Throwable) {
                    $priceAxis = null;
                }
            }

            $categories[$categoryKey]['menuItems'][$itemKey]['filterPrice'] = $priceAxis;
        }
    }

    /* Metin ŞABLONDA değil KATALOGDA yaşar (`docs/85`): Blade'e yazılan bir
       cümleyi sahip hiçbir PO dosyasından çeviremez.
   
       Harita verilmediyse görünüm ONU KENDİ ÇÖZER. Aksi hâlde haritayı
       geçirmeyi unutan bir çağıran, sayfayı sessizce BOŞ ETİKETLERLE
       basardı — ve bu, ekranda görülene kadar fark edilmezdi. */
    $gt = $guestText ?? app(\App\Support\Localization\GuestText::class)->all(
        $guestLocale ?? $contentLocale ?? 'tr',
        $categoryCount,
        $itemCount,
    );

    /* SEPET — SUNUCUNUN KARARI, ŞABLONUN DEĞİL (`docs/115` S3, FF-178).

       `$ordering` `null` ise bu sayfada sepetin tek bir düğmesi bile
       çizilmez. Karar denetleyicide veriliyor (hak + şalter + masa + tek
       para birimi) ve buraya YALNIZ sonucu geliyor: şablon "acaba" diye
       sormaz, çünkü sorabilseydi iki yerde iki farklı cevap doğardı.

       Sözlüğü ANA SÖZLÜĞE katmak, betiğin tek bir `say()` ile konuşmasını
       sağlıyor. Sepet çizilmediğinde bu cümleler hiç inmiyor — olmayan bir
       ekranın sözlüğünü misafirin hattından geçirmenin bir karşılığı yok. */
    $ordering = $ordering ?? null;

    if ($ordering !== null) {
        $gt = array_merge($gt, $ordering['text'] ?? []);
    }

    /* PUANLAMA — SUNUCUNUN KARARI (`docs/116` §3/§4, P4–P6).

       `$rating` `null` ise bu sayfada tek bir yıldız, tek bir "henüz
       yeterli değerlendirme yok" cümlesi ve tek bir puan çizilmez. Karar
       denetleyicide veriliyor (masaya bağlı karekod) ve buraya YALNIZ
       sonucu geliyor — sepetle aynı desen, aynı gerekçe.

       Sözlük ana sözlüğe katılıyor ki betik tek bir `say()` ile konuşsun;
       puanlama çizilmediğinde bu cümleler misafirin hattından hiç
       geçmiyor. */
    $rating = $rating ?? null;

    if ($rating !== null) {
        $gt = array_merge($gt, $rating['text'] ?? []);
    }

    /* Kalıptaki `{score}`/`{max}` SUNUCUDA doldurulur: sayı burada hazır ve
       hiç değişmiyor, dolayısıyla onu istemciye taşıyıp orada birleştirmek
       misafire gereksiz bir betik indirtmek olurdu (`ScoreLabel`). */
    $fillScore = static fn (string $pattern, string $score, int $max): string => str_replace(
        ['{score}', '{max}'],
        [$score, (string) $max],
        $pattern,
    );

    $text = static fn (string $key): string => (string) ($gt[$key] ?? '');

    $headline = $identity?->brandName ?: trim((string) ($fallbackBrandName ?? ''));
    $documentTitle = $headline !== '' ? $headline : 'Menü';
@endphp
{{-- Dil, UYGULAMANIN değil MENÜNÜN dilidir: ürün adlarını restoran kendi
     dilinde yazar. Yanlış `lang`, ekran okuyucunun metni yanlış telaffuz
     etmesine ve arama motorunun sayfayı yanlış dile atamasına yol açar. --}}
{{-- `lang` ARAYÜZ dilidir; menü içeriği kendi dilini ayrıca taşır
     (aşağıdaki kategori listesinde). İkisini tek etikete sıkıştırmak, ekran
     okuyucunun ya arayüzü ya ürün adlarını yanlış telaffuz etmesi demekti
     (`docs/85`). --}}
<html lang="{{ $guestLocale ?? $contentLocale ?? \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ isset($guestLocale) ? \App\Support\Localization\GuestLocale::direction($guestLocale) : \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @isset($previewNotice)
        {{-- TASLAK ÖNİZLEMESİ ARANIP BULUNAMAZ. Bu sayfa yayınlanmamış
             fiyatlar taşır; indekslenmesi, restoranın henüz vermediği bir
             kararı arama sonuçlarına düşürürdü. --}}
        <meta name="robots" content="noindex,nofollow">
    @endisset
    <title>{{ $documentTitle }}</title>
    @isset($canonicalUrl)
        {{-- Aynı menü izleme parametresiyle, farklı sorgu sırasıyla veya
             sondaki slash ile birden çok adresten açılabilir. Kanonik
             etiket olmadan arama motoru hangisini indeksleyeceğine kendi
             karar verir. --}}
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endisset
    {{-- Paylaşım önizlemesi: bu sayfa çoğunlukla WhatsApp'ta paylaşılır ve
         oradaki bot JavaScript çalıştırmaz; etiketler sunucuda basılır. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $documentTitle }}">
    <meta property="og:site_name" content="Zabuno">
    @php
        // Blade'in tek satırlık `@php(...)` biçimi İÇ İÇE parantezi doğru
        // kapatmıyor ve geri kalan şablonu PHP olarak yutuyor; blok biçimi
        // kullanılır.
        $pageDescription = $documentDescription($identity);
    @endphp
    <meta name="description" content="{{ $pageDescription }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    @isset($structuredData)
        {{-- Sayfada olmayan hiçbir şey işaretlenmez: bu veri sayfayı
             render eden anlık görüntünün TA KENDİSİNDEN türetilir. --}}
        <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! $structuredData !!}</script>
    @endisset
    <link rel="manifest" href="/public-menu.webmanifest">
    <meta name="theme-color" content="#1f2937">
    <link rel="apple-touch-icon" href="/icons/zabuno-menu-192.svg" sizes="192x192">
    <link rel="icon" href="/icons/zabuno-menu-512.svg" sizes="512x512" type="image/svg+xml">
    @include('partials.theme-bootstrap')
    {{-- Misafirin gördüğü sayfa. Tenant kimliği olmadan bu trafik tek bir
         yığın olurdu ve "hangi restoranın menüsü kaç kez açıldı" sorusu
         tarayıcı tarafında cevapsız kalırdı. --}}
    @include('partials.analytics', ['analyticsContext' => $analyticsContext ?? ['zabuno_surface' => 'menu']])
    @if ($identity?->primaryColor !== null || $identity?->secondaryColor !== null)
        {{-- MARKA RENGİ yayından okunur (FF-89), canlı markadan değil: renk
             yarın değişirse dünkü yayın değişmez. Renk yalnız DEKORASYONDUR
             (üst şerit ve kategori altı çizgisi); metin ya da metin arkası
             olarak kullanılmaz, çünkü restoranın seçtiği açık sarı bir renk
             beyaz üstünde okunmaz hâle gelirdi ve kontrastı biz garanti
             edemeyiz. --}}
        <style nonce="{{ $cspNonce ?? '' }}">
            :root {
                @if ($identity->primaryColor !== null) --qr-brand: {{ $identity->primaryColor }}; @endif
                @if ($identity->secondaryColor !== null) --qr-brand-secondary: {{ $identity->secondaryColor }}; @endif
            }
        </style>
    @endif
    {{-- Ortak token kökü: `--qr-*` artık beş dosyada değil BİR dosyada
         (`docs/113` §6.3). Aşağıdaki blok yalnız BU yüzeyin düzenidir. --}}
    @include('partials.guest-surface-style')
    <style nonce="{{ $cspNonce ?? '' }}">
        /* Yapışkan başlığın yüksekliği İKİ yerde lazım: başlığın kendisinde ve
           ona çarpmadan durması gereken şeylerde (çıpa kaydırma payı, yan
           rayın yapışma noktası). Sayıyı iki kere yazmak, bir gün birini
           değiştirip diğerini unutmaktır. */
        :root {
            --qr-hdr-h: 56px;
        }

        /* Marka şeridi: renk seçilmemişse `--qr-brand` tanımsızdır ve şerit
           yüksekliği sıfır kalır — seçmeyen restoran, seçmiş gibi
           gösterilmez.

           BU ŞERİTTE renk hâlâ yalnız DEKORASYONDUR ve bu paket onu metin ya
           da metin arkası yapmıyor. Sebep artık "garanti edemiyoruz" değil:
           kontrast rampası FF-174 ile geldi ve garantiyi ölçüyle veriyor
           (`MenuIdentity::$skin`). Sebep sıradır — rampayı bu yüzeyde
           tüketmek bir sonraki adımdır ve gerekçesi ortak stil kökünde
           yazılı. Şeridin kendisi o gün de kalır; değişecek olan, rengin
           şeridin DIŞINDA da rol alabilmesidir. */
        .qr-brand-bar {
            height: 0;
        }

        @supports (height: 4px) {
            .qr-brand-bar {
                height: 4px;
                background: var(--qr-brand, transparent);
            }
        }

        /* ---- YAPIŞKAN BAŞLIK — kaynağın kimlik satırı --------------------

           320'DE SIKIŞMAZ ve bu tesadüf değil (`docs/113` §7.2.1). Kaynağın
           filtre çubuğunda sabitler 293 px yiyip sonuç etiketine 3 px
           bırakıyordu. Bu satırda sabit olan TEK şey 34 px'lik logodur:
           34 + 8 boşluk = 42 px. 320'de kullanılabilir 296 px'ten geriye
           kimlik metnine 254 px kalır.

           Sayfadaki TEK yapışkan öğe budur (`docs/113` §7.2 son karar):
           320×480'de üç ayrı yapışkan çubuk dikey alanın yarısını yerdi ve
           `docs/48` §6.5'in "hiçbir denetim içeriğin üstüne kalıcı binmez"
           ölçütünü zorlardı. */
        .qr-hdr {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--qr-surface);
            border-bottom: 1px solid var(--qr-border);
        }

        .qr-hdr-row {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: var(--qr-hdr-h);
            padding: 8px var(--qr-gutter);
            max-width: 1680px;
            margin-inline: auto;
        }

        .qr-hdr-id {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        /* Uzun bir restoran adı satırı taşırmaz, ÜÇ NOKTAYA döner: taşan bir
           başlık 320'de yatay kaydırma üretirdi. */
        .qr-menu-title,
        .qr-menu-location {
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .qr-menu-title {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .qr-menu-location {
            font-size: 0.8125rem;
            color: var(--qr-muted);
        }

        .qr-menu-logo {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            object-fit: contain;
            border-radius: 10px;
            background: var(--qr-surface-2);
        }

        /* ---- SAYFA İSKELETİ --------------------------------------------- */
        .qr-page {
            padding: var(--qr-gutter);
            max-width: 1680px;
            margin-inline: auto;
        }

        .qr-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        /* ---- KATEGORİ RAYI — kaynağın `gm-hs` çipleri ------------------- */
        .qr-menu-nav {
            display: flex;
            gap: 8px;
            margin: 0 0 12px;
            padding: 0 0 2px;
            list-style: none;
            overflow-x: auto;
            overscroll-behavior-x: contain;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }

        .qr-menu-nav::-webkit-scrollbar {
            display: none;
        }

        .qr-menu-nav a {
            flex: none;
            scroll-snap-align: start;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--qr-tap);
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            background: var(--qr-surface);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ---- YARDIMCI ÇUBUK: arama, dil, kurulum ------------------------

           SARMALANIR ve sarmalanması bir tercih değil, kırık 1'in çözümüdür:
           bir DURUM ya da SAYI cümlesi hiçbir zaman sabit genişlikli
           denetimlerin yanında ezilmez, `flex: 1 0 100%` ile kendi satırını
           alır. Yarın buraya bir denetim daha eklense de bu kural tutar. */
        .qr-utility {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin: 0 0 12px;
        }

        .qr-menu-search {
            flex: 1 1 100%;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin: 0;
        }

        .qr-menu-search label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--qr-fg-2);
        }

        /* Arama alanı ile mikrofon AYNI SATIRI paylaşır ve mikrofon dokunma
           hedefi kadar yer tutar. 320'de kalan her piksel arama alanına
           gider: `flex: 1 1 auto` ile ortak stil kökündeki taban genişlik
           sıfırlaması birlikte, uzun bir yer tutucunun satırı taşırmasını
           imkânsız kılar. */
        .qr-menu-search-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #menu-search {
            font: inherit;
            flex: 1 1 auto;
            min-height: var(--qr-tap);
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            background: var(--qr-surface-2);
            color: var(--qr-fg);
        }

        /* MİKROFON DÜĞMESİ — işaretlemesi `<template>` içinde iner, DOM'a
           yalnız tarayıcı konuşma tanımayı destekliyorsa girer. Bu yüzden
           kural burada durur ama çoğu zaman hiçbir şeye uygulanmaz. */
        .qr-voice {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--qr-tap);
            min-height: var(--qr-tap);
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface);
        }

        /* Dinleme durumu RENKLE ANLATILMAZ: `aria-pressed` onu ekran
           okuyucuya söyler, yanındaki canlı bölge de cümleyle yazar
           (WCAG 1.4.1). Aşağıdaki ton yalnız yardımcıdır. */
        .qr-voice[aria-pressed='true'] {
            background: var(--qr-accent-tint);
            border-color: currentColor;
        }

        #menu-search-status,
        #menu-voice-status,
        .qr-menu-content-notice {
            flex: 1 0 100%;
            margin: 0;
            font-size: 0.8125rem;
            color: var(--qr-muted);
        }

        #menu-search-status {
            min-height: 1.1em;
        }

        /* ---- FİLTRELER — kaynağın alt sayfası yerine bir AÇILIR BÖLÜM ---

           Kaynak 320'de tam ekran bir alt sayfa (bottom sheet) çiziyor;
           o düzen JavaScript'siz hiç açılmaz ve `docs/48` §6.5'in "hiçbir
           denetim içeriğin üstüne kalıcı binmez" ölçütünü de zorlar.
           `<details>` aynı işi tarayıcının kendi yeteneğiyle yapar: açılıp
           kapanması hiçbir bayt JavaScript istemez.

           Panelin KENDİSİ yine de sunucuda gizli iner ve onu betik açar —
           süzme JavaScript ister ve basıldığında hiçbir şey elemeyen bir
           denetim, çalışmayan bir mikrofon düğmesiyle aynı yalanı söyler. */
        .qr-filters {
            flex: 1 0 100%;
            border: 1px solid var(--qr-border);
            border-radius: var(--qr-radius-s);
            background: var(--qr-surface);
        }

        /* Tarayıcının KENDİ üçgen imi bilerek yerinde bırakılır: `display`
           değiştirilseydi im kaybolur ve "Filtreler" satırı açılır bir
           denetim değil, bir başlık gibi okunurdu. Açık/kapalı durumu da
           yalnız renge değil, o ime ve `<details>`in kendi semantiğine
           yaslanır. */
        .qr-filters-summary {
            min-height: var(--qr-tap);
            padding: 11px 14px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
        }

        .qr-filters-body {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 4px 14px 14px;
        }

        .qr-filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .qr-filter-title {
            margin: 0;
            font-size: 0.9375rem;
        }

        /* Alerjen ekseninin sınırı bir DİPNOT değil, panelin bir parçasıdır:
           küçültülmez, gizlenmez ve açılır bir ipucunun arkasına konmaz. */
        .qr-filter-hint {
            margin: 0;
            font-size: 0.8125rem;
            color: var(--qr-fg-2);
            text-wrap: pretty;
        }

        .qr-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .qr-filter-chip {
            min-height: var(--qr-tap);
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            background: var(--qr-surface-2);
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* HARİÇ TUTULAN çipin ÜSTÜ ÇİZİLİR. Seçili olduğunu yalnız bir ton
           farkıyla söylemek, rengi göremeyen misafir için hiçbir şey
           anlatmaz; üstü çizili metin eleme yönünü de gösterir. */
        .qr-filter-chip[aria-pressed='true'] {
            border-color: currentColor;
            background: var(--qr-accent-tint);
            text-decoration: line-through;
        }

        .qr-filter-prices {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Taban genişlik KARAKTERLE verilir, pikselle değil: alan yazının
           kendi ölçüsüne göre daralır ve 320'de iki kutu yan yana sığar. */
        .qr-filter-price {
            flex: 1 1 8ch;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.8125rem;
            color: var(--qr-fg-2);
        }

        .qr-filter-price input {
            font: inherit;
            min-height: var(--qr-tap);
            padding: 0 12px;
            border-radius: var(--qr-radius-s);
            border: 1px solid var(--qr-border);
            background: var(--qr-surface-2);
            color: var(--qr-fg);
        }

        .qr-filter-clear {
            align-self: flex-start;
            min-height: var(--qr-tap);
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface);
        }

        .qr-menu-language {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin: 0;
        }

        /* Seçili dil YALNIZ kalınlıkla değil `aria-current` ile de söylenir;
           renk ya da kalınlık tek başına anlatmaz (WCAG 1.4.1). */
        .qr-menu-language a,
        .qr-menu-language span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--qr-tap);
            min-inline-size: var(--qr-tap);
            padding: 0 10px;
            border-radius: 999px;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .qr-menu-language [aria-current='true'] {
            font-weight: 700;
            background: var(--qr-accent-tint);
        }

        .pwa-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        #pwa-install-button {
            font: inherit;
            min-height: var(--qr-tap);
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface);
            color: inherit;
            cursor: pointer;
        }

        /* Durum cümleleri de kendi satırlarını alır: "çevrimdışısın" bir
           rozet değil, bir cümledir ve kırpılırsa hiçbir şey anlatmaz. */
        #pwa-install-status,
        #pwa-offline-status {
            flex: 1 0 100%;
            font-size: 0.8125rem;
            color: var(--qr-muted);
        }

        /* ---- LİSTE BAŞI: sonuç sayısı ----------------------------------

           KIRIK 1'İN ÇÖZÜMÜ (`docs/113` §7.2.1). Sayı, sabit denetimlerin
           yanından çıkıp listenin başına kendi satırına alındı. Burada bir
           esnek çubuk olması bilerek: yarın buraya bir sıralama denetimi
           gelse bile özet 100% tabanıyla kendi satırında kalır. */
        .qr-listhead {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 8px;
            margin: 0 0 12px;
        }

        .qr-menu-summary {
            flex: 1 0 100%;
            margin: 0;
            font-size: 0.875rem;
            color: var(--qr-muted);
        }

        /* ---- KATEGORİ VE KARTLAR ---------------------------------------- */
        .qr-menu-category {
            margin: 0 0 24px;
            /* Çıpaya atlayan misafirin başlığı yapışkan başlığın ALTINDA
               kalmasın: kaydırma payı başlığın kendi yüksekliğinden çıkar. */
            scroll-margin-top: calc(var(--qr-hdr-h) + var(--qr-stick));
        }

        .qr-menu-category-name {
            font-size: 1.25rem;
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--qr-brand-secondary, var(--qr-border));
        }

        /* SÜTUN SAYISI EŞİKTEN DEĞİL, TABAN GENİŞLİKTEN ÇIKAR.
           Kaynağın 600 / 1280 / 1600 eşiklerinin üçü de burada kayboluyor
           (`docs/113` §7.1): `auto-fit` sütunu yer olduğunda açar ve kart dar
           bir sütunun içine konsa da doğru davranır. `min(100%, …)` 320'de
           taşmayı imkânsız kılar. */
        .qr-menu-item-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr));
            gap: 10px;
        }

        /* Kart kendi KAPSAYICISIDIR: içindeki kararlar ekranı değil kartın
           kendi genişliğini dinler (`docs/48` §3, 2. araç). */
        .qr-menu-item {
            container-type: inline-size;
            display: flex;
            align-items: stretch;
            background: var(--qr-surface);
            border: 1px solid var(--qr-border);
            border-radius: var(--qr-radius);
            overflow: hidden;
            transition: transform var(--qr-d2) var(--qr-ease);
        }

        .qr-menu-item:active {
            transform: scale(0.985);
        }

        .qr-menu-item-image {
            flex: 0 0 96px;
            width: 100%;
            height: auto;
            aspect-ratio: 1;
            object-fit: cover;
            background: var(--qr-sunken);
        }

        /* Kaynağın 375 ve 430 eşikleri BUNLARDI ve ikisi de kartın kendi
           kararıydı, ekranın değil. Kapsayıcı sorgusu ikisini de doğru
           yapar: dar bir sütuna konan kart küçük görselde kalır. */
        @container (min-width: 22rem) {
            .qr-menu-item-image {
                flex-basis: 108px;
            }
        }

        @container (min-width: 26rem) {
            .qr-menu-item-image {
                flex-basis: 120px;
            }
        }

        /* KIRIK 2'NİN ÇÖZÜMÜ (`docs/113` §7.2.2).

           Kaynakta ürün sayfasının alt çubuğunda sabitler 143 px yiyip eylem
           düğmesine 153 px bırakıyor ve metni kesiyordu. Aynı aritmetik
           deponun BUGÜN çizdiği satırda da vardı: 320'de kullanılabilir
           ~294 px'ten görsel 96 + fiyat ~58 + "Bugün tükendi" rozeti ~104 +
           boşluklar 36 çıkınca ürün ADINA ~0 px kalıyordu.

           Çözüm eşik değil yerleşim: ad ile fiyat sarmalanan bir satırı
           paylaşır (ad 12ch tabanıyla, yer kalmayınca fiyat alta düşer),
           açıklama / durum / alerjen ise CÜMLEDİR ve her biri kendi tam
           satırını alır. Ad hiçbir genişlikte sıfıra inmez. */
        .qr-menu-item-body {
            flex: 1 1 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 8px;
            padding: 10px 12px;
        }

        .qr-menu-item-name {
            flex: 1 1 12ch;
            font-size: 1.0625rem;
            font-weight: 600;
            line-height: 1.25;
        }

        /* Bağlantı olan ad, RENKLE DEĞİL altı çizgiyle ayrılır: renk tek
           başına anlatmaz (WCAG 1.4.1) ve vurgu rengi burada mürekkeple
           aynı tondadır. */
        a.qr-menu-item-name {
            text-decoration: underline;
            text-decoration-color: var(--qr-border-strong);
            text-underline-offset: 3px;
        }

        .qr-menu-item-price {
            flex: 0 0 auto;
            white-space: nowrap;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .qr-menu-item-description {
            flex: 1 0 100%;
            font-size: 0.875rem;
            color: var(--qr-fg-2);
        }

        /* Solukluk YARDIMCIDIR, tek başına anlatmaz: durumu satırdaki metnin
           kendisi söylüyor (WCAG 1.4.1). */
        .qr-menu-item-sold-out .qr-menu-item-name,
        .qr-menu-item-sold-out .qr-menu-item-price,
        .qr-menu-item-sold-out .qr-menu-item-image {
            opacity: 0.65;
        }

        .qr-menu-item-sold-out-note {
            flex: 1 0 100%;
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--qr-warn);
        }

        .qr-menu-item-allergens {
            flex: 1 0 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 2px;
        }

        /* Alerjen UYARI tonundadır, HATA tonunda değil: bir arıza değil,
           dikkat edilecek bir bilgidir. Liste yalnız BİLDİRİLENİ gösterir ve
           hiçbir yerde "alerjensizdir" demez — yanlış bir alerjensizlik
           iddiası bir sağlık olayıdır (`docs/113` §4.2). */
        .qr-menu-item-allergen-chip {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--qr-warn-tint);
            color: var(--qr-warn);
        }

        @isset($ordering)
        /* ---- SEPET (FF-178) ---------------------------------------------

           BU KURALLAR DA YALNIZ SEPET ÇİZİLDİĞİNDE İNER. Sipariş almayan bir
           restoranın menüsü, hiçbir zaman çizilmeyecek düğmelerin stilini
           taşımaz: sayfanın maliyeti, sahip olmadığı yetenekle artmamalı
           (`docs/113` §8).

           YENİ BİR KIRILMA NOKTASI YOK ve olmayacak: sepetin bütün kararları
           içsel düzenden çıkıyor (`docs/113` §7.1). Panelin genişliği bir
           eşikten değil `min()`ten geliyor, satırları sarmalanan bir
           satırdan.

           SABİT ALT ÇUBUK DA YOK: sayfadaki tek yapışkan öğe kimlik
           başlığıdır ve 320×480'de ikinci bir sabit çubuk dikey alanın
           önemli bir kısmını yerdi (`docs/48` §6.5). */
        .qr-cart-btn {
            flex: 0 0 auto;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--qr-tap);
            min-height: var(--qr-tap);
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface);
            color: inherit;
        }

        /* Rozet SAYIYI söyler, rengi değil: sayısı olmayan bir nokta,
           sepette kaç ürün olduğunu hiçbir misafire anlatmaz. */
        .qr-cart-badge {
            position: absolute;
            inset-block-start: -2px;
            inset-inline-end: -2px;
            min-inline-size: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--qr-accent);
            color: var(--qr-accent-fg);
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 18px;
        }

        /* Canlı bölge GÖZE görünmez ama EKRAN OKUYUCUYA görünür: `display`
           ya da `visibility` ile gizlenseydi duyuru da susardı. Yer
           kaplamaması için mutlak konumlanır; başlık satırının aritmetiği
           bu yüzden değişmez. */
        .qr-cart-live {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }

        /* Ekleme düğmesi kartın SARMALANAN satırının sonunda durur ve sağa
           itilir; yer kalmadığında kendi satırına iner. Ad bu yüzden hiçbir
           genişlikte sıfıra inmez (`docs/113` §7.2.2). */
        .qr-cart-add {
            flex: 0 0 auto;
            margin-inline-start: auto;
            font: inherit;
            min-height: var(--qr-tap);
            padding: 0 16px;
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface-2);
            color: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .qr-cart {
            /* Genişlik EŞİKTEN DEĞİL `min()`TEN gelir: 320'de kenar payı
               kalır, geniş ekranda okunur bir sütunda durur. */
            inline-size: min(34rem, calc(100% - 24px));
            max-block-size: min(88vh, 44rem);
            padding: 0;
            border: 1px solid var(--qr-border);
            border-radius: var(--qr-radius);
            background: var(--qr-surface);
            color: var(--qr-fg);
        }

        /* `display` YALNIZ AÇIKKEN verilir. Koşulsuz bir `display:flex`,
           tarayıcının kendi `display:none` kuralını ezer ve kapalı sepeti
           sayfanın ortasında açık bırakırdı — bir kez yaşanmış bir hata
           değil, bir kez yaşanması yeten bir hata. */
        .qr-cart[open] {
            display: flex;
            flex-direction: column;
        }

        .qr-cart::backdrop {
            background: rgb(0 0 0 / 0.5);
        }

        .qr-cart-head {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--qr-border);
        }

        .qr-cart-title {
            flex: 1 1 auto;
            margin: 0;
            font-size: 1.125rem;
        }

        .qr-cart-close {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--qr-tap);
            min-height: var(--qr-tap);
            border-radius: 999px;
            border: 1px solid var(--qr-border-strong);
            background: var(--qr-surface);
            color: inherit;
            cursor: pointer;
        }

        /* Uzun bir sepet panelin İÇİNDE kaydırılır, sayfanın değil: modal
           açıkken arkadaki listeyi kaydırmak misafiri kaybettirirdi. */
        .qr-cart-body {
            flex: 1 1 auto;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 4px 14px;
        }

        .qr-cart-empty {
            margin: 0;
            padding: 28px 4px;
            color: var(--qr-fg-2);
            text-align: center;
            text-wrap: pretty;
        }

        .qr-cart-lines {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        /* Satır da SARMALANIR: ad ile tutar yer varsa yan yana, adet
           denetimi her zaman kendi tam satırında. Üç dokunma hedefini bir
           adın yanına sıkıştırmak, 320'de adı yok ederdi. */
        .qr-cart-line {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 8px;
            padding: 8px 0;
            border-bottom: 1px solid var(--qr-border);
        }

        .qr-cart-name {
            flex: 1 1 10ch;
            font-weight: 600;
        }

        .qr-cart-line-total,
        .qr-cart-qty {
            font-variant-numeric: tabular-nums;
        }

        .qr-cart-line-total {
            flex: 0 0 auto;
            font-weight: 700;
        }

        .qr-cart-step {
            flex: 1 0 100%;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .qr-cart-step button {
            min-height: var(--qr-tap);
            min-inline-size: var(--qr-tap);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid var(--qr-border);
            background: var(--qr-surface-2);
            color: inherit;
            cursor: pointer;
        }

        /* "Çıkar" adet denetiminden AYRILIR: yan yana duran üç aynı düğmeden
           birine yanlışlıkla basmak, misafirin seçtiği satırı silmektir. */
        .qr-cart-step [data-cart-remove] {
            margin-inline-start: auto;
        }

        .qr-cart-qty {
            min-inline-size: 3ch;
            text-align: center;
            font-weight: 700;
        }

        .qr-cart-foot {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            /* Telefonun kendi çubuğu düğmenin üstüne binmesin. */
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
            border-top: 1px solid var(--qr-border);
        }

        .qr-cart-sum {
            display: flex;
            gap: 8px;
            margin: 0;
            font-weight: 700;
        }

        .qr-cart-sum span:last-child {
            margin-inline-start: auto;
        }

        .qr-cart-status:empty {
            display: none;
        }

        .qr-cart-status {
            margin: 0;
            font-size: 0.875rem;
            color: var(--qr-fg-2);
            text-wrap: pretty;
        }

        .qr-cart-send {
            font: inherit;
            min-height: var(--qr-tap);
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--qr-accent);
            background: var(--qr-accent);
            color: var(--qr-accent-fg);
            font-weight: 700;
            cursor: pointer;
        }

        .qr-cart-send[disabled] {
            opacity: 0.6;
        }

        .qr-cart-note {
            margin: 0;
            font-size: 0.8125rem;
            color: var(--qr-muted);
            text-wrap: pretty;
        }
        @endisset

        @isset($rating)
        /* ---- PUANLAMA (`docs/116` §3/§4) --------------------------------

           Puan, bilinmezlik cümlesi, sahibin yanıtı ve oy denetimi — dördü
           de kartın SARMALANAN satırında birer TAM SATIR alır. Ad ile fiyat
           yan yana durabilir çünkü ikisi de kısadır; bunlar cümledir ve
           320 px'de bir cümlenin yanına bir şey koymak, ikisini birden
           okunmaz yapar (`docs/113` §7.1). */
        .qr-rate-score,
        .qr-rate-none,
        .qr-rate-reply,
        .qr-rate {
            flex: 1 0 100%;
        }

        .qr-rate-score,
        .qr-rate-none {
            font-size: 0.8125rem;
        }

        /* BİLİNMEZLİK SOLUK YAZILIR AMA GİZLENMEZ: bir cümledir ve
           okunmalıdır. Renk tek başına anlam taşımıyor — cümlenin kendisi
           "henüz yeterli değerlendirme yok" diyor (WCAG 1.4.1). */
        .qr-rate-none {
            color: var(--qr-muted);
        }

        .qr-rate-reply {
            display: block;
            margin-top: 4px;
            padding: 8px 10px;
            border-inline-start: 3px solid var(--qr-accent);
            background: var(--qr-surface);
            font-size: 0.8125rem;
            text-wrap: pretty;
        }

        /* KİM KONUŞUYOR — kendi satırında ve vurgulu. Restoranın cümlesiyle
           misafirlerin ölçümü aynı kartta duruyor; ayırt edilemezlerse
           sahibin sözü bir değerlendirme sanılır. */
        .qr-rate-reply-who {
            display: block;
            font-weight: 700;
            color: var(--qr-muted);
        }

        .qr-rate {
            display: flex;
            gap: 2px;
            margin-top: 4px;
        }

        /* Beş düğme × 44 px = 220 px; 320 px'lik ekrana kendi satırında
           rahat sığar (`docs/48` §1). Ölçü ortak `--qr-tap` değişkeninden
           gelir — sepetin adet düğmeleriyle aynı parmak. */
        .qr-rate-btn {
            min-height: var(--qr-tap);
            min-inline-size: var(--qr-tap);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            background: none;
            color: var(--qr-muted);
            cursor: pointer;
        }

        /* SEÇİLİ YILDIZ YALNIZ RENKLE AYRILMAZ.

           Renk körü bir misafir için "gri yıldız" ile "vurgu rengi yıldız"
           aynı yıldızdır; parlaklık farkı ise her görme türünde okunur.
           Ekran okuyucu için ayrım zaten `aria-pressed`te (WCAG 1.4.1). */
        .qr-rate-btn {
            opacity: 0.45;
        }

        .qr-rate-btn[aria-pressed='true'] {
            color: var(--qr-accent);
            opacity: 1;
        }

        .qr-rate-ico {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }
        @endisset

        /* ---- BOŞ DURUMLAR ----------------------------------------------- */
        .qr-menu-empty-state,
        .qr-menu-category-empty {
            margin: 0;
            padding: 32px 20px;
            background: var(--qr-surface);
            border: 1px solid var(--qr-border);
            border-radius: var(--qr-radius);
            color: var(--qr-fg-2);
            text-align: center;
            text-wrap: pretty;
        }

        .qr-menu-category-empty {
            padding: 20px;
            font-size: 0.875rem;
        }

        /* ---- ALTBİLGİ — kimliğin geri kalanı ---------------------------- */
        .qr-menu-foot {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--qr-border);
            font-size: 0.875rem;
            color: var(--qr-muted);
        }

        /* Uzun bir cadde adı 320 px'te satırı taşırmaz: kelime kırılır. */
        .qr-menu-address,
        .qr-menu-phone,
        .qr-menu-subtitle {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .qr-menu-phone a {
            display: inline-flex;
            align-items: center;
            min-height: var(--qr-tap);
        }

        /* ---- TEK KIRILMA NOKTASI ---------------------------------------

           `docs/113` §7.1: kaynağın altı eşiğinden yalnız BU kalır ve
           deponun kendi ölçeğindeki `--aep-bp-md` ile aynı değerdedir
           (`resources/css/aep/tokens/layout.css:6` → 1024px).

           NEDEN BU EŞİK MEŞRU: yan rayın içeriğin YANINDA mı yoksa ÜSTÜNDE
           mi durduğu gerçekten SAYFANIN kararıdır — bir kartın kendi
           tercihi değil. `docs/48` §3'ün üçüncü aracı tam olarak bunun için
           ayrılmıştır ve gerekçesi burada yazılıdır.

           Diğer beşi (375, 430, 600, 1280, 1600) yukarıda içsel düzene ve
           kapsayıcı sorgusuna çevrildi; bu dosyada başka `@media (…-width)`
           kuralı YOKTUR ve `GuestMenuDesignLanguageTest` bunu dondurur. */
        @media (min-width: 1024px) {
            .qr-shell {
                grid-template-columns: 288px minmax(0, 1fr);
                gap: 24px;
            }

            .qr-rail {
                position: sticky;
                top: calc(var(--qr-hdr-h) + var(--qr-stick));
            }

            /* Ray dikeye döndüğünde yatay kaydırıcı olmaktan çıkar: fareyle
               kaydırılamayan gizli çubuk masaüstünde bir tuzaktır. */
            .qr-menu-nav {
                flex-direction: column;
                overflow-x: visible;
            }

            .qr-menu-nav a {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
{{-- ZABUNO ÇERÇEVESİ (`docs/113` §6) — misafir yüzeyinin sahiplik sınırı.
     Bugün hiçbir piksel üretmez; `header`/`footer` yuvaları boş olduğu için
     çıktıda tek bir düğüm bile bırakmaz. Zabuno'nun kendi başlığı ve
     altbilgisi geldiğinde dört misafir yüzeyine ayrı ayrı değil, buraya
     girer. --}}
<x-zabuno surface="menu">
{{-- Marka şeridi sayfanın EN ÜSTÜNDE durur. `main` içinde dururken kurulum
     çubuğunun altına düşüyor ve sayfanın ortasında başıboş bir çizgi gibi
     görünüyordu. --}}
<div class="qr-brand-bar" aria-hidden="true"></div>

{{-- YAPIŞKAN KİMLİK BAŞLIĞI — kaynağın üst şeridi (`docs/113` §1.1 no.1).
     Misafir kaydırdıkça nerede olduğunu unutmasın diye yapışkan; sayfadaki
     TEK yapışkan öğe olduğu için 320×480'de dikey alanı da yemez. --}}
<header class="qr-hdr">
    <div class="qr-hdr-row">
        @php($logo = $identity !== null && isset($snapshot['identity']['logo']) && is_array($snapshot['identity']['logo'])
            ? $snapshot['identity']['logo']
            : null)

        @if ($logo)
            {{-- Ölçüler ATTRIBUTE olarak basılır: görsel inerken sayfa
                 zıplamasın, misafir okuduğu satırı kaybetmesin. `sizes`
                 çizilen ölçüyü söyler — 96 px yazıp 34 px çizmek, tarayıcıya
                 gereğinden büyük bir dosya indirtirdi. --}}
            <img class="qr-menu-logo"
                 src="{{ $logo['sources'][count($logo['sources']) - 1]['url'] }}"
                 srcset="{{ $srcset($logo['sources']) }}"
                 sizes="34px"
                 width="{{ $logo['width'] }}" height="{{ $logo['height'] }}"
                 alt="{{ $logo['altText'] }}"
                 decoding="async">
        @endif

        {{-- Misafirin gördüğü ilk kelime "Menü" değil, gittiği yerin adıdır.
             Ad bilinmiyorsa başlık yine de basılır: boş bir <h1> sayfayı
             ekran okuyucu için başlıksız bırakırdı. --}}
        <span class="qr-hdr-id">
            <h1 class="qr-menu-title">{{ $documentTitle }}</h1>

            @if ($identity !== null && $identity->locationName !== '' && $identity->locationName !== $headline)
                <span class="qr-menu-location">{{ $identity->locationName }}</span>
            @endif
        </span>

        {{-- SEPET DÜĞMESİ — kaynağın başlık satırındaki çanta (FF-178).

             Satır için ayrılan pay tam olarak buydu: `var(--qr-tap)`
             genişliğinde, `flex:none`. 320'de kimlik metnine kalan 254 px,
             bu düğmeyle birlikte 254 - 44 - 8 = 202 px'e iner ve hâlâ bir
             restoran adını taşır.

             SUNUCU ONU YALNIZ SİPARİŞ GERÇEKTEN VERİLEBİLİYORSA BASAR; JS
             de `<dialog>` ve cihaz deposu çalışmıyorsa hiç AÇMAZ. İki kapı
             da aynı sözü tutar: basınca hiçbir şey olmayan bir düğme, o
             düğmenin hiç olmamasından kötüdür.

             Simge SATIR İÇİ SVG'dir; ikon yazı tipi bir ağ isteğidir ve bu
             sayfanın ölçülen sözü sıfır istektir (`docs/113` §8). --}}
        @isset($ordering)
            <button type="button" class="qr-cart-btn qr-press" data-cart-open hidden aria-label="{{ $text('cartOpen') }}">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M4 8h16l-1.2 11.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8z"></path>
                    <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                </svg>
                {{-- Rozet SAYIDIR ve sıfırken hiç çizilmez: "0" yazan bir
                     rozet, boş bir sepeti dolu göstermenin sessiz yoludur. --}}
                <span class="qr-cart-badge" data-cart-count hidden></span>
            </button>
            {{-- "Sepete eklendi" CÜMLESİNİN YERİ.

                 Cümle sepetin İÇİNE yazılamaz: misafir ürünü menüden
                 eklerken sepet kapalıdır ve kapalı bir panele yazılan bir
                 onay, hiç yazılmamış bir onaydır.

                 Gören misafir için geri bildirim ROZETİN KENDİSİDİR — sayı
                 yapışkan başlıkta, gözünün önünde artar. Görmeyen misafir
                 için sayının artması hiçbir şey söylemez; bu canlı bölge
                 tam olarak onun için var (WCAG 4.1.3). --}}
            <span class="qr-cart-live" role="status" aria-live="polite" data-cart-live></span>
        @endisset
        @isset($rating)
            {{-- "PUANINIZ KAYDEDİLDİ" CÜMLESİNİN YERİ.

                 Gören misafir için geri bildirim yıldızların DOLMASIDIR —
                 bastığı yerde, gözünün önünde. Görmeyen misafir için dolan
                 bir yıldız hiçbir şey söylemez; bu canlı bölge tam olarak
                 onun için var (WCAG 4.1.3).

                 TEK BİR BÖLGE, kırk ürün için. Satır başına bir canlı bölge
                 koymak, ekran okuyucuya kırk ayrı duyuru kanalı açmak
                 olurdu ve hangisinin konuştuğu belirsizleşirdi. --}}
            <span class="qr-cart-live" role="status" aria-live="polite" data-rate-live></span>
        @endisset
    </div>
</header>

@isset($rating)
    {{-- YILDIZIN ŞEKLİ SAYFADA BİR KEZ TANIMLANIR.

         Kırk ürünlük bir menüde her düğmeye ayrı bir `<path>` basmak, aynı
         yolu iki yüz kez tekrarlamak olurdu. Simge burada bir kez durur,
         düğmeler ona `<use>` ile bakar. KÜTÜPHANE YOK: bu sayfaya bir simge
         paketi indirmek, masadaki zayıf hatta tek bir yıldız için bir
         ağ isteği ödemek demekti (`GUEST-CART-320-08` ile aynı gerekçe). --}}
    <svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
        <symbol id="qr-star" viewBox="0 0 24 24"><path d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.2-5.9 3.2 1.2-6.5L2.5 9.5l6.6-.9z"/></symbol>
    </svg>
@endisset

<main role="main" class="qr-page" @isset($menuKey) data-menu-key="{{ $menuKey }}" @endisset>
    @isset($previewNotice)
        {{-- ÖNİZLEME KENDİNİ SÖYLER ve sayfanın EN ÜSTÜNDE söyler. Bağlantı
             bir grup sohbetine düşerse onu açan kişi de, sahibin kendisi de
             bunun canlı menü olmadığını menüye bakmadan önce görür. Uyarı
             yalnız renge yaslanmaz: cümlenin kendisi metindir ve `role`
             ile duyurulur. --}}
        <p role="status" class="qr-menu-preview-notice"
           style="margin:0;padding:12px 16px;border-bottom:1px solid currentColor;font-weight:700">
            {{ $previewNotice }}
            @isset($previewBlockedReason)
                @if ($previewBlockedReason !== null)
                    — {{ $previewBlockedReason }}
                @endif
            @endisset
        </p>
    @endisset
    {{-- ŞUBE ŞU ANDA KAPALI (FF-141) — menü GİZLENMEZ, üstüne dürüst bir
         şerit konur. Gece 23:00'te karekodu okutan misafir çoğu zaman yarını
         planlıyordur; menüyü saklamak ona hizmet etmez, yalnız elimizdeki
         bilgiyi ondan gizler.

         Bu şerit `public-menu-out-of-service` DEĞİLDİR ve olmamalıdır: o sayfa
         "gösterilecek menü yok" der ve menüyü hiç çizmez. Burada menü vardır.

         ÖNİZLEME UYARISININ ALTINDA, İKİSİ BİRDEN (FF-143). "Bu bir önizleme"
         ile "şu anda kapalıyız" farklı iki gerçektir ve aynı anda doğru
         olabilirler; biri diğerinin yerine geçmez, geçseydi sahip önizlemede
         misafirin gördüğünden başka bir sayfa görürdü.

         İşaretleme ORTAK PARÇADADIR çünkü aynı şerit ürün sayfasında da
         çizilir; kopyalasaydık ikisi bir gün ayrışırdı. --}}
    @include('partials.guest-closed-notice', ['closedNotice' => $closedNotice ?? null])
    {{-- İKİ SÜTUNLU KABUK — kaynağın `gm-shell`'i (`docs/113` §1.1 no.5).
         320'de tek sütun: ray içeriğin ÜSTÜNDE, yatay bir çip şeridi olarak.
         1024'ten sonra ray içeriğin YANINDA ve yapışkan. Bu dosyadaki TEK
         kırılma noktası budur ve gerekçesi stil bloğunda yazılı. --}}
    <div class="qr-shell">
        <aside class="qr-rail">
            @if ($categoryCount > 0)
                <nav class="qr-menu-nav" aria-label="{{ $text('categoriesLabel') }}">
                    @foreach ($categories as $navIndex => $category)
                        <a href="#category-{{ $navIndex }}">{{ $category['name'] }}</a>
                    @endforeach
                </nav>
            @endif

            {{-- YARDIMCI ÇUBUK. Kaynağın filtre çubuğunun yerini tutar ve
                 onun 320'deki hatasını TEKRARLAMAZ (`docs/113` §7.2.1):
                 buradaki her durum/sayı cümlesi `flex: 1 0 100%` ile kendi
                 satırını alır, hiçbir zaman sabit denetimlerin yanında
                 ezilmez. Filtre denetimleri arka uçlarıyla birlikte ayrı bir
                 pakette bu çubuğa girecek; düzen onları olduğu gibi
                 kaldırır. --}}
            <div class="qr-utility">
                <div class="qr-menu-search">
                    <label for="menu-search">{{ $text('searchLabel') }}</label>
                    {{-- ARAMA SUNUCUYA GİTMEZ ve bu ölçülmüş bir karardır
                         (FF-177): yayının TAMAMI zaten bu sayfada basılı —
                         80 ürünlük bir menü 15 KB gzip iniyor ve her ürünün
                         adı, fiyatı ve alerjeni DOM'da duruyor. Bir arama
                         ucu açmak, misafire elinde olanı her tuş vuruşunda
                         yeniden indirtirdi. Menü sayfalanmaya başladığı gün
                         bu karar yeniden verilir; testi o gün kırılır. --}}
                    <div class="qr-menu-search-row" data-search-row>
                        <input type="search" id="menu-search" name="menu-search" autocomplete="off" placeholder="{{ $text('searchPlaceholder') }}">
                        {{-- Mikrofon düğmesi BURAYA girer ama sunucu onu
                             çizmez; aşağıdaki `<template>` ve betik bakınız. --}}
                    </div>
                </div>
                <p id="menu-search-status" role="status" aria-live="polite"></p>
                <p id="menu-voice-status" role="status" aria-live="polite"></p>

                {{-- SESLİ ARAMA DÜĞMESİ ÇİZİLMEZ, İNER (FF-177).

                     Konuşma tanıma bir TARAYICI yeteneğidir ve sunucu isteğe
                     bakarak onu bilemez; kullanıcı aracısından tahmin etmek
                     ise bu depoda zaten bir kez yanlış çıkmış bir yöntemdir.
                     Bu yüzden işaretleme inert bir `<template>` içinde iner
                     ve DOM'a yalnız yetenek GERÇEKTEN varsa girer.

                     Desteklemeyen tarayıcıda hiçbir şey görünmez ve hiçbir
                     şey söylenmez: masadaki misafire olmayan bir yetenek
                     vaat etmemenin tek yolu, onu hiç göstermemektir.

                     Simge SATIR İÇİ SVG'dir. Kaynağın ikon yazı tipi
                     (Phosphor) bir AĞ İSTEĞİ demektir ve bu sayfanın ölçülen
                     sözü sıfır istektir (`docs/113` §8). --}}
                <template id="menu-voice-template">
                    <button type="button" id="menu-voice" class="qr-voice qr-press" aria-pressed="false" aria-label="{{ $text('voiceLabel') }}">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3z"></path>
                            <path d="M5 11a7 7 0 0 0 14 0"></path>
                            <path d="M12 18v3"></path>
                        </svg>
                    </button>
                </template>

                {{-- FİLTRELER (FF-177) — `docs/114` §3 Dalga 2.

                     KATEGORİ EKSENİ ZATEN VAR: yukarıdaki ray düz çıpalarla
                     çalışır ve JavaScript istemez; onu bir filtreye çevirmek
                     çalışan bir gezinmeyi betiğe borçlandırırdı. Buraya
                     eklenen iki eksen ALERJEN ve FİYATTIR.

                     PANEL GİZLİ İNER ve onu betik açar; gerekçesi stil
                     bloğunda yazılı. --}}
                <details class="qr-filters" data-filters hidden>
                    <summary class="qr-filters-summary">{{ $text('filtersLabel') }}</summary>
                    <div class="qr-filters-body">
                        @if ($declaredAllergens !== [])
                            <div class="qr-filter-group">
                                <h2 class="qr-filter-title">{{ $text('allergenExcludeLabel') }}</h2>
                                {{-- BU CÜMLE KISALTILAMAZ ve gizlenemez.
                                     Filtre yalnız HARİÇ TUTAR: ürün "bu
                                     üründe fıstık yoktur" diyemez, "restoran
                                     fıstık bildirmedi" der. Cümle olmasaydı
                                     boşalan liste, misafirin kalanları
                                     güvenli sanmasına yol açardı — ve yanlış
                                     bir alerjensizlik iddiası bir SAĞLIK
                                     OLAYIDIR (`docs/114` §0). --}}
                                <p class="qr-filter-hint">{{ $text('allergenExcludeHint') }}</p>
                                <div class="qr-filter-chips">
                                    @foreach ($declaredAllergens as $allergenKey => $allergenLabel)
                                        <button type="button" class="qr-filter-chip" data-allergen-filter="{{ $allergenKey }}" aria-pressed="false">{{ $allergenLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="qr-filter-group">
                            <h2 class="qr-filter-title">{{ $text('priceRangeLabel') }}</h2>
                            <div class="qr-filter-prices">
                                <label class="qr-filter-price" for="filter-price-min">
                                    <span>{{ $text('priceMinLabel') }}</span>
                                    <input type="number" inputmode="decimal" min="0" step="any" id="filter-price-min" data-price-min>
                                </label>
                                <label class="qr-filter-price" for="filter-price-max">
                                    <span>{{ $text('priceMaxLabel') }}</span>
                                    <input type="number" inputmode="decimal" min="0" step="any" id="filter-price-max" data-price-max>
                                </label>
                            </div>
                        </div>

                        <button type="button" class="qr-filter-clear" data-filter-clear>{{ $text('filtersClear') }}</button>
                    </div>
                </details>

                @isset($guestLocale)
                    {{-- Dil seçimi düz BAĞLANTIDIR: JavaScript çalışmasa da
                         çalışır ve seçim sunucuda hatırlanır (çerez), böylece
                         sayfa daha ilk boyamada doğru dilde gelir.

                         Başlıktan BAĞIMSIZ: restoranın adı bilinsin bilinmesin,
                         misafirin dili değiştirebilmesi gerekir. --}}
                    <nav class="qr-menu-language" aria-label="{{ $text('languageLabel') }}">
                        @foreach (\App\Support\Localization\GuestLocale::SUPPORTED as $option)
                            @if ($option === $guestLocale)
                                <span aria-current="true">{{ strtoupper($option) }}</span>
                            @else
                                <a href="?lang={{ $option }}" rel="nofollow">{{ strtoupper($option) }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endisset

                {{-- KURULUM VE ÇEVRİMDIŞI — kaynakta karşılığı olmayan, ama
                     depoda çalışan bir yetenek (`docs/113` §4.1). Kaynağa
                     birebir uyulsaydı bu çubuk sessizce kaybolurdu. --}}
                <div class="pwa-bar">
                    <button type="button" id="pwa-install-button" hidden>{{ $text('installButton') }}</button>
                    <span id="pwa-install-status" role="status" aria-live="polite"></span>
                    <span id="pwa-offline-status" role="status" aria-live="polite"></span>
                </div>

                @isset($guestLocale)
                    @if ($guestLocale !== ($contentLocale ?? $guestLocale))
                        {{-- İÇERİK çevirisi ARAYÜZ çevirisi değildir: ürün
                             adlarını restoran kendi dilinde yazar. Bunu
                             söylememek, tutulmayacak bir söz vermek olurdu. --}}
                        <p class="qr-menu-content-notice">{{ $text('contentNotice') }}</p>
                    @endif
                @endisset
            </div>
        </aside>

        <div class="qr-content">
            {{-- SONUÇ SAYISI KENDİ SATIRINDA — kırık 1'in çözümü
                 (`docs/113` §7.2.1). Kaynakta bu etiket filtre çubuğunda
                 sabit denetimlerin yanındaydı ve 320'de ~3 px'e sıkışıp
                 tamamen kayboluyordu; burada listenin başında tam genişlikte
                 durur. --}}
            <div class="qr-listhead">
                <p class="qr-menu-summary">{{ $text('summary') }}</p>
            </div>

            {{-- MENÜ İÇERİĞİ kendi dilini taşır: arayüz İngilizce olsa da ürün
                 adları restoranın dilindedir ve ekran okuyucu onları o dilde
                 telaffuz etmeli (`docs/85`). --}}
            @if ($categoryCount === 0)
                <p class="qr-menu-empty-state">{{ $text('menuEmpty') }}</p>
            @else
                @foreach ($categories as $categoryIndex => $category)
            <section class="qr-menu-category" id="category-{{ $categoryIndex }}" data-category @isset($contentLocale) lang="{{ $contentLocale }}" @endisset>
                <h2 class="qr-menu-category-name">{{ $category['name'] }}</h2>

                @if (empty($category['menuItems']))
                    <p class="qr-menu-category-empty">{{ $text('categoryEmpty') }}</p>
                @else
                    <ul class="qr-menu-item-list">
                        @foreach ($category['menuItems'] as $item)
                            @php($isSoldOut = isset($item['menuItemId']) && isset($soldOut[(int) $item['menuItemId']]))
                            {{-- ÇIPA: `#item-101` tarayıcının kendi işidir ve
                                 JavaScript gerektirmez. Sahibin örneğindeki
                                 `#item=101` biçimi de aşağıdaki küçük betikle
                                 buraya bağlanır (FF-116).

                                 SÜZÜLEBİLİR EKSENLER satırın kendi
                                 özniteliğindedir (FF-177): filtre istemcide
                                 çalışıyor ve veriyi satırın YANINDA bulmalı.
                                 Değerleri sayfanın başındaki PHP bloğu
                                 hazırladı; gerekçesi orada yazılı. --}}
                            <li class="qr-menu-item{{ $isSoldOut ? ' qr-menu-item-sold-out' : '' }}" data-item data-item-name="{{ $item['productName'] }}" @if (($item['filterAllergens'] ?? '') !== '') data-item-allergens="{{ $item['filterAllergens'] }}" @endif @if (($item['filterPrice'] ?? null) !== null) data-item-price="{{ $item['filterPrice'] }}" @endif @isset($ordering) data-item-price-minor="{{ (int) ($item['priceMinorAmount'] ?? 0) }}" @endisset @isset($item['menuItemId']) id="item-{{ $item['menuItemId'] }}" data-menu-item-id="{{ $item['menuItemId'] }}" @endisset>
                                @if (! empty($item['image']['sources']))
                                    @php($image = $item['image'])
                                    {{-- `loading="lazy"`: kırk ürünlük bir menüde
                                         misafir ilk ekranı görmek için kırk
                                         fotoğraf beklemez. --}}
                                    <img class="qr-menu-item-image"
                                         @if (! empty($image['lqip'])) style="background: url({{ $image['lqip'] }}) center / cover no-repeat" @endif
                                         src="{{ $image['sources'][0]['url'] }}"
                                         srcset="{{ $srcset($image['sources']) }}"
                                         sizes="(max-width: 480px) 96px, 128px"
                                         width="{{ $image['width'] }}" height="{{ $image['height'] }}"
                                         alt="{{ $image['altText'] }}"
                                         loading="lazy" decoding="async">
                                @endif
                                {{-- KARTIN METİN SÜTUNU. Sarmalanan bir satır:
                                     ad ile fiyat yer varsa yan yana durur, yer
                                     kalmayınca fiyat alta düşer; açıklama,
                                     durum ve alerjen ise birer CÜMLEDİR ve her
                                     biri kendi tam satırını alır. Kaynağın 375
                                     eşiği (`.gm-price{flex:1 0 100%}`) tam
                                     olarak bunu yapıyordu — burada bir eşik
                                     olmadan yapılıyor (`docs/113` §7.1).

                                     Rozet ve eylem yeri de buradadır: kaynağın
                                     kartında görselin üstünde rozet, fiyatın
                                     yanında favori ve "Ekle" düğmeleri var.
                                     Bunların arka ucu ayrı paketlerde geliyor;
                                     geldiklerinde bu satır onları eşiksiz
                                     kaldırır, çünkü sarmalanan bir satırdır.
                                     Bugün çizmiyoruz: veri gelmeden çizilen bir
                                     düğme, misafire çalışacağını söyleyip
                                     hiçbir şey yapmaz. --}}
                                <div class="qr-menu-item-body">
                                    {{-- ÜRÜN ADI, ANLATACAK ŞEYİ VARSA bağlantıdır
                                         (FF-116). Açıklaması, görseli ve alerjeni
                                         olmayan bir ürünün sayfası bu satırın
                                         kopyasıdır; hiçbir yere götürmeyen bir
                                         bağlantı kurmak bir yalandır. --}}
                                    @if (isset($item['menuItemId']) && isset($itemPathFor) && \App\Http\Controllers\QrDestination\ShowPublicMenuItemController::hasSomethingToSay($item))
                                        <a class="qr-menu-item-name" href="{{ $itemPathFor((int) $item['menuItemId'], (string) $item['productName']) }}">{{ $item['productName'] }}</a>
                                    @else
                                        <span class="qr-menu-item-name">{{ $item['productName'] }}</span>
                                    @endif
                                    @php($priceLabel = \App\Support\Money\PriceLabel::for((int) $item['priceMinorAmount'], (string) $item['currencyCode']))
                                    @if ($priceLabel !== null)
                                        <span class="qr-menu-item-price">{{ $priceLabel }}</span>
                                    @endif
                                    @if ($isSoldOut)
                                        {{-- Tükendi METİNLE söylenir. Yalnız renk
                                             ya da soluklukla anlatmak, renk
                                             göremeyen misafir için hiçbir şey
                                             anlatmaz (WCAG 1.4.1). --}}
                                        <span class="qr-menu-item-sold-out-note">{{ $text('soldOut') }}</span>
                                    @endif
                                    @if (! empty($item['description']))
                                        <span class="qr-menu-item-description">{{ $item['description'] }}</span>
                                    @endif
                                    @if (! empty($item['allergens']))
                                        <span class="qr-menu-item-allergens">
                                            @foreach ($item['allergens'] as $allergen)
                                                <span class="qr-menu-item-allergen-chip">{{ $allergen }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                    {{-- SEPETE EKLE — kaynağın karttaki "Ekle"si (FF-178).

                                         TÜKENMİŞ ÜRÜNDE HİÇ ÇİZİLMEZ (M7).
                                         Çizilseydi misafir onu sepete atar,
                                         gönderir ve sunucudan `out_of_stock`
                                         yerdi; yani ürün ona bilerek boşuna bir
                                         tur attırırdı. "Bugün bitti" işareti
                                         sipariş yolunda da geçerlidir.

                                         Düğme SARMALANAN satırın sonunda durur
                                         ve `margin-inline-start:auto` ile sağa
                                         itilir: 320'de ad + fiyat ilk satırı
                                         doldurunca kendi satırına iner ve ürün
                                         ADI hiçbir genişlikte sıfıra inmez
                                         (`docs/113` §7.2.2). --}}
                                    @if (isset($ordering) && isset($item['menuItemId']) && ! $isSoldOut)
                                        <button type="button" class="qr-cart-add qr-press" data-cart-add hidden>{{ $text('cartAdd') }}</button>
                                    @endif
                                    @if (isset($rating) && isset($item['menuItemId']))
                                        @php($ratingRow = $rating['items'][(int) $item['menuItemId']] ?? null)
                                        {{-- PUAN YA DA BİLİNMEZLİK — İKİSİNDEN BİRİ HER ZAMAN YAZILIR.

                                             Eşik altında SIFIR YILDIZ çizilmez (`docs/116` §3): sıfır bir
                                             ÖLÇÜMDÜR ve bilinmeyenin yerine geçemez. Ama hiçbir şey
                                             yazmamak da bir cevap değil — misafir "bu ürün puanlanmıyor
                                             mu, yoksa kötü mü?" diye sorar ve boş bir yer o soruyu
                                             cevaplamaz.

                                             KARAR SUNUCUDA VERİLDİ. Buraya gelen `label` ya bir metindir
                                             ya `null`; eşiğin sayısı, sinyal sayısı ve toplam ağırlık bu
                                             sayfaya HİÇ inmiyor. Şablonun yanlış yapabileceği bir şey
                                             kalmıyor. --}}
                                        @if ($ratingRow !== null && $ratingRow['label'] !== null)
                                            <span class="qr-rate-score" data-rating-score="{{ $ratingRow['label'] }}">{{ $fillScore($text('ratingScorePattern'), $ratingRow['label'], (int) $rating['scaleMax']) }}</span>
                                        @else
                                            <span class="qr-rate-none" data-rating-unknown>{{ $text('ratingNotEnough') }}</span>
                                        @endif
                                        @if ($ratingRow !== null && ($ratingRow['reply'] ?? null) !== null)
                                            {{-- SAHİBİN YANITI (`docs/116` §4, P6) — KİM KONUŞUYOR YAZILI.

                                                 Kaynağı yazılmasaydı misafir restoranın cümlesini bir
                                                 değerlendirme sanırdı; aynı ilke dış kaynaklar için de
                                                 yazılı (§5 D1). --}}
                                            <span class="qr-rate-reply" data-rating-reply>
                                                <span class="qr-rate-reply-who">{{ $text('ratingReplyLabel') }}</span>
                                                <span class="qr-rate-reply-body">{{ $ratingRow['reply'] }}</span>
                                            </span>
                                        @endif
                                        {{-- OY DENETİMİ. `hidden` ile iner ve betik onu açar: JavaScript
                                             çalışmayan bir tarayıcıda basınca hiçbir şey olmayan beş
                                             düğme göstermek, misafire olmayan bir yetenek vaat etmektir.
                                             Puan ve yanıt ise `hidden` DEĞİL — onlar düz metindir ve
                                             betiksiz de doğrudur. --}}
                                        <span class="qr-rate" role="group" aria-label="{{ $text('ratingLabel') }}" data-rate-group hidden>
                                            @for ($star = 1; $star <= (int) $rating['scaleMax']; $star++)
                                                <button type="button" class="qr-rate-btn qr-press" data-rate="{{ $star }}" aria-pressed="false" aria-label="{{ $fillScore($text('ratingChoicePattern'), (string) $star, (int) $rating['scaleMax']) }}"><svg class="qr-rate-ico" aria-hidden="true" focusable="false"><use href="#qr-star"/></svg></button>
                                            @endfor
                                        </span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
                @endforeach
            @endif

            {{-- ALTBİLGİ — kimliğin geri kalanı.

                 Adres ve telefon başlıktan BURAYA alındı: 320 px'lik bir
                 ekranda ilk görünen şey yemek olmalı, künye değil. İkisi de
                 kaybolmadı, aşağıda ve tam genişlikte duruyor.

                 Kaynağın altbilgisindeki "Fiyatlar KDV dahildir" cümlesi
                 buraya taşınMADI: bu paketin ürün verisinde vergi alanı
                 taşınmıyor, dolayısıyla cümle doğrulanamaz bir iddia olurdu.
                 Vergi alanı kendi paketiyle geldiğinde bu satır da gelir. --}}
            <div class="qr-menu-foot">
                @if ($identity?->addressLine)
                    <p class="qr-menu-address">{{ $identity->addressLine }}</p>
                @endif

                @if ($identity?->telHref())
                    {{-- Misafir masada numarayı elle yazmaz. Görünen metin
                         insan için, bağlantı makine içindir. --}}
                    <p class="qr-menu-phone">
                        <a href="{{ $identity->telHref() }}">{{ $identity->phone }}</a>
                    </p>
                @endif

                @if ($headline === '')
                    {{-- Bu cümle ürün-İÇİ bir cümledir: "yayınlanmış sürüm"
                         misafirin sorduğu bir soru değil, bizim kavramımız.
                         Sayfa kendi kimliğini söyleyebiliyorsa gereksizdir;
                         söyleyemiyorsa misafire hiç değilse ne baktığını
                         anlatır (`docs/79`). --}}
                    <p class="qr-menu-subtitle">{{ $text('subtitle') }}</p>
                @endif
            </div>
        </div>
    </div>
</main>
{{-- ═══ SEPET (FF-178) — `docs/115` S3 ═══

     KAYNAĞIN SABİT ALT ÇUBUĞU BURAYA GELMEDİ ve bu bilerek. Kaynakta
     `.gm-cartbar` ekranın altına yapışıyor; bu sayfada YAPIŞKAN OLAN TEK ŞEY
     kimlik başlığıdır (`docs/113` §7.2) ve 320×480'de ikinci bir sabit çubuk
     dikey alanın önemli bir kısmını yerdi. `docs/48` §6.5 de bunu söylüyor:
     hiçbir denetim içeriğin üstüne KALICI binmez. Sepet, misafirin kendi
     açtığı bir katmandır; açık olduğu sürece içeriğin üstündedir ve
     kapandığında hiçbir yer kaplamaz.

     `<dialog>` SEÇİLDİ, ÇÜNKÜ BEDAVA GELEN ŞEYLER ÖNEMLİ: odak tuzağı,
     `Esc` ile kapanma, arka planın erişilemez olması ve `::backdrop`.
     Bunları elle yazmak bir kütüphane kadar bayt tutardı — bu paketin kararı
     ise kütüphane eklememektir. Desteklemeyen tarayıcıda betik sepeti hiç
     AÇMAZ ve düğme çizilmez.

     Panel MENÜNÜN İÇİNDE değil, `main`in dışında durur: modal bir katmanın
     kaydırılan listenin içinde yaşaması, `overflow` bağlamlarına bağımlı bir
     davranış üretirdi. --}}
@isset($ordering)
    <dialog class="qr-cart" data-cart-panel aria-labelledby="cart-title">
        <div class="qr-cart-head">
            <h2 class="qr-cart-title" id="cart-title">{{ $text('cartTitle') }}</h2>
            <button type="button" class="qr-cart-close qr-press" data-cart-close aria-label="{{ $text('cartClose') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
                    <path d="M6 6l12 12M18 6L6 18"></path>
                </svg>
            </button>
        </div>

        <div class="qr-cart-body">
            {{-- BOŞ SEPET BİR CÜMLEDİR. Boş bir panel misafire ürünün bozuk
                 olduğunu düşündürür; cümle ne olduğunu ve ne yapacağını
                 söyler. --}}
            <p class="qr-cart-empty" data-cart-empty>{{ $text('cartEmpty') }}</p>
            <ul class="qr-cart-lines" data-cart-lines></ul>
        </div>

        {{-- ALT BÖLÜM SEPET DOLUYKEN ÇİZİLİR: boş bir sepette "Gönder"
             düğmesi, basıldığında hiçbir şey yapmayan bir düğmedir. --}}
        <div class="qr-cart-foot" data-cart-foot hidden>
            <p class="qr-cart-sum"><span>{{ $text('cartTotal') }}</span><span data-cart-total></span></p>
            {{-- Gönderme sonucunun TEK yeri. `role="status"` ile ekran
                 okuyucu da öğrenir: sepeti gözle takip etmeyen misafir için
                 sessiz bir başarı, sessiz bir hatadan ayırt edilemez. --}}
            <p class="qr-cart-status" role="status" aria-live="polite" data-cart-status></p>
            <button type="button" class="qr-cart-send qr-press" data-cart-submit>{{ $text('cartSubmit') }}</button>
            {{-- İKİ ONAY OLDUĞUNU MİSAFİR GÖNDERMEDEN ÖNCE OKUR
                 (`docs/115` §2): gönderdiği bir taleptir, garson onaylayınca
                 iş olur. Ödemenin masada alındığı da burada yazar; bu üründe
                 ödeme yoktur ve olmadığını saklamak misafiri telefonda
                 bekletirdi. --}}
            <p class="qr-cart-note">{{ $text('cartSubmitNote') }}</p>
        </div>
    </dialog>

    {{-- SATIR İŞARETLEMESİ BETİKTE DEĞİL, ŞABLONDA YAŞAR.

         Satırı JavaScript içinde dize birleştirerek kursaydık, düğmelerin
         erişilebilirlik etiketleri de betiğe kaçardı — oysa onlar kullanıcı
         metnidir ve katalogda yaşamak zorundadır (`docs/85`). --}}
    <template id="cart-line-template">
        <li class="qr-cart-line" data-cart-line>
            <span class="qr-cart-name" data-line-name></span>
            <span class="qr-cart-line-total" data-line-total></span>
            <span class="qr-cart-step">
                <button type="button" data-cart-dec aria-label="{{ $text('cartDecrease') }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 12h12"></path></svg>
                </button>
                <span class="qr-cart-qty" data-line-qty></span>
                <button type="button" data-cart-inc aria-label="{{ $text('cartIncrease') }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 6v12M6 12h12"></path></svg>
                </button>
                <button type="button" data-cart-remove aria-label="{{ $text('cartRemove') }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"></path></svg>
                </button>
            </span>
        </li>
    </template>
@endisset
</x-zabuno>
{{-- Betik gövdesindeki sabitler de KULLANICI METNİDİR (`docs/85`).
     Harita JSON olarak basılır; betik onu okur ve tek bir cümle bile
     şablonda kalmaz. --}}
<script type="application/json" id="guest-text" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($gt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@isset($ordering)
    {{-- SİPARİŞİN İKİ SABİTİ: NEREYE ve HANGİ BİÇİMDE (FF-178).

         ADRES sunucudan iner; istemcide kurulsaydı karekod belirtecinin
         biçimi değiştiği gün sayfa sessizce yanlış uca yazardı.

         PARA BİÇİMİ de sunucudan iner ve bu ölçülmüş bir karardır: toplam
         misafirin telefonunda toplanıyor ama biçimi orada DOĞMUYOR. Ondalık
         basamak sayısı para biriminin kendi özelliğidir (yende sıfır,
         dinarda üç) ve tarayıcının kendi biçimlendiricisi sunucununkinden
         başka bir cevap verebilir — aradaki fark ancak masada hesap
         istendiğinde anlaşılırdı (`MoneyFormatContract`). --}}
    <script type="application/json" id="guest-order" nonce="{{ $cspNonce ?? '' }}">{!! json_encode([
        'submitPath' => $ordering['submitPath'],
        'money' => $ordering['money'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endisset
@isset($rating)
    {{-- OYUN GİDECEĞİ ADRES — sunucudan iner (`docs/116` §4).

         İstemcide kurulsaydı karekod belirtecinin biçimi değiştiği gün
         sayfa sessizce yanlış uca yazardı; sipariş ucunda aynı karar aynı
         gerekçeyle alınmıştı.

         BURADA PUAN YOK, EŞİK YOK, SİNYAL SAYISI YOK. Bu blok yalnız
         adresi taşır: gösterilecek her sayı zaten sunucuda metne çevrilip
         satırın kendisine basıldı. --}}
    <script type="application/json" id="guest-rating" nonce="{{ $cspNonce ?? '' }}">{!! json_encode([
        'submitPath' => $rating['submitPath'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endisset
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var TEXT = {};

        try {
            var textNode = document.getElementById('guest-text');
            TEXT = textNode ? JSON.parse(textNode.textContent || '{}') : {};
        } catch (error) {
            // Metin okunamadıysa arayüz sessiz kalır; menü yine görünür.
        }

        function say(key) {
            return TEXT[key] || '';
        }

        /*
            `#item=101` BİÇİMİNİ TARAYICININ ANLADIĞI ÇIPAYA BAĞLA (FF-116).

            Sahibin örneği bu biçimdeydi. Tarayıcı `#item=101` diye bir çıpa
            tanımaz; sayfadaki gerçek kimlik `item-101`. Fragment sunucuya hiç
            ulaşmadığı için bunu ancak istemci çözebilir. Adres DEĞİŞTİRİLMEZ:
            misafirin paylaştığı bağlantı elinde ne ise o kalır.
        */
        (function () {
            var match = /^#item=(\d+)$/.exec(window.location.hash || '');

            if (match) {
                var target = document.getElementById('item-' + match[1]);

                if (target && typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView();
                }
            }
        })();

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/public-diner-sw.js', { scope: '/menu/' });
            navigator.serviceWorker.register('/public-diner-sw.js', { scope: '/q/' });
        }

        var installButton = document.getElementById('pwa-install-button');
        var installStatus = document.getElementById('pwa-install-status');
        var offlineStatus = document.getElementById('pwa-offline-status');
        var deferredInstallPrompt = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredInstallPrompt = event;
            installButton.hidden = false;
        });

        installButton.addEventListener('click', function () {
            if (!deferredInstallPrompt) {
                return;
            }

            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function (choice) {
                installStatus.textContent =
                        choice.outcome === 'accepted' ? say('installAccepted') : say('installDismissed');
                deferredInstallPrompt = null;
                installButton.hidden = true;
            });
        });

        window.addEventListener('appinstalled', function () {
            installStatus.textContent = say('installed');
            installButton.hidden = true;
        });

        function updateOfflineStatus() {
            offlineStatus.textContent = navigator.onLine ? '' : say('offline');
        }

        window.addEventListener('online', updateOfflineStatus);
        window.addEventListener('offline', updateOfflineStatus);
        updateOfflineStatus();

        var searchInput = document.getElementById('menu-search');
        var searchStatus = document.getElementById('menu-search-status');
        var voiceStatus = document.getElementById('menu-voice-status');
        var categories = Array.prototype.slice.call(document.querySelectorAll('[data-category]'));
        var filters = document.querySelector('[data-filters]');
        var allergenButtons = Array.prototype.slice.call(document.querySelectorAll('[data-allergen-filter]'));
        var priceMinInput = document.querySelector('[data-price-min]');
        var priceMaxInput = document.querySelector('[data-price-max]');

        /*
            FİLTRE PANELİNİ BETİK AÇAR.

            Süzme JavaScript ister. Betik çalışmayan bir tarayıcıda paneli
            açık bırakmak, basıldığında hiçbir şey elemeyen denetimler
            çizmek olurdu — çalışmayan bir mikrofon düğmesiyle aynı yalan.
        */
        if (filters) {
            filters.hidden = false;
        }

        function amount(input) {
            // Türk klavyesinde ondalık ayırıcı virgüldür; misafir "18,50"
            // yazdığında filtre çalışmalı.
            var value = input ? parseFloat(String(input.value).replace(',', '.')) : NaN;

            return isNaN(value) ? null : value;
        }

        function excludedAllergens() {
            var excluded = [];

            allergenButtons.forEach(function (button) {
                if (button.getAttribute('aria-pressed') === 'true') {
                    excluded.push(button.getAttribute('data-allergen-filter') || '');
                }
            });

            return excluded;
        }

        function allergenAllows(item, excluded) {
            if (excluded.length === 0) {
                return true;
            }

            /*
                ELEME YÖNÜ TEK YÖNDÜR: seçilen alerjeni BİLDİREN satır düşer.
                Bildirilmemiş bir satır "içermiyor" sayılmaz, yalnız
                elenmemiş olur — panelin kendi cümlesi bunu misafire açıkça
                söylüyor (`docs/114` §0).
            */
            var declared = item.getAttribute('data-item-allergens') || '';

            for (var index = 0; index < excluded.length; index += 1) {
                if (declared.indexOf('|' + excluded[index] + '|') !== -1) {
                    return false;
                }
            }

            return true;
        }

        function priceAllows(item, min, max) {
            if (min === null && max === null) {
                return true;
            }

            var raw = item.getAttribute('data-item-price');

            // Fiyatı okunamayan satır ELENMEZ: fiyatı zaten gösterilmiyor
            // ve onu bir aralığın dışında saymak, bilmediğimiz bir şeyi
            // biliyormuş gibi davranmak olurdu.
            if (raw === null) {
                return true;
            }

            var price = parseFloat(raw);

            return !(min !== null && price < min) && !(max !== null && price > max);
        }

        function statusFor(query, filtered, visibleCount) {
            if (visibleCount === 0) {
                /*
                    SIFIR SONUÇ İKİ AYRI CÜMLEDİR.

                    Aramadaki boşluk "menüde bu yok" demektir ve sahibin
                    defterine yazılır. Filtredeki boşluk ise yalnız
                    misafirin kendi koyduğu sınırı anlatır; menü doludur.
                    Tek cümleye indirmek, misafire menünün boş olduğunu
                    söylerdi.
                */
                if (filtered) {
                    return say('filterNoMatch');
                }

                return query === '' ? '' : say('searchNoMatch');
            }

            if (query !== '') {
                return say('searchMatched').replace('{count}', String(visibleCount));
            }

            return filtered ? say('filterMatched').replace('{count}', String(visibleCount)) : '';
        }

        function apply() {
            var query = searchInput ? searchInput.value.trim().toLocaleLowerCase('tr') : '';
            var excluded = excludedAllergens();
            var min = amount(priceMinInput);
            var max = amount(priceMaxInput);
            var filtered = excluded.length > 0 || min !== null || max !== null;
            var visibleCount = 0;
            /*
                ARAMA EKSENİ AYRI SAYILIR ve bu sayı yalnız ölçüme gider.

                Filtreler yüzünden boşalan bir liste "aradı, bulamadı"
                DEĞİLDİR: misafir alerjen filtresiyle karidesi kendisi
                eledi ve karides menüde duruyor. İki sayıyı birleştirmek,
                sahibin "menümde olmayan ne isteniyor" defterine olmayan
                bir talep yazardı (`docs/84`).
            */
            var searchOnlyCount = 0;

            categories.forEach(function (categorySection) {
                var items = Array.prototype.slice.call(categorySection.querySelectorAll('[data-item]'));
                // Ürünü olmayan kategori, süzülmediği sürece görünür kalır:
                // "bu kategoride henüz ürün yok" cümlesi de bir bilgidir.
                var categoryHasMatch = items.length === 0 && query === '' && !filtered;

                items.forEach(function (item) {
                    var name = (item.getAttribute('data-item-name') || '').toLocaleLowerCase('tr');
                    var nameMatches = query === '' || name.indexOf(query) !== -1;

                    if (nameMatches) {
                        searchOnlyCount += 1;
                    }

                    var matches = nameMatches
                        && allergenAllows(item, excluded)
                        && priceAllows(item, min, max);

                    item.hidden = !matches;

                    if (matches) {
                        visibleCount += 1;
                        categoryHasMatch = true;
                    }
                });

                categorySection.hidden = !categoryHasMatch;
            });

            if (searchStatus) {
                searchStatus.textContent = statusFor(query, filtered, visibleCount);
            }

            reportNoResults(query, searchOnlyCount);
        }

        if (searchInput && searchStatus) {
            searchInput.addEventListener('input', apply);
        }

        allergenButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                button.setAttribute(
                    'aria-pressed',
                    button.getAttribute('aria-pressed') === 'true' ? 'false' : 'true',
                );
                apply();
            });
        });

        [priceMinInput, priceMaxInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', apply);
            }
        });

        var filterClear = document.querySelector('[data-filter-clear]');

        if (filterClear) {
            filterClear.addEventListener('click', function () {
                allergenButtons.forEach(function (button) {
                    button.setAttribute('aria-pressed', 'false');
                });

                if (priceMinInput) {
                    priceMinInput.value = '';
                }

                if (priceMaxInput) {
                    priceMaxInput.value = '';
                }

                apply();
            });
        }

        /*
            SESLİ ARAMA — TARAYICIDA BAŞLAR, TARAYICIDA BİTER (`docs/114` §3).

            SES SUNUCUYA GİTMEZ. Tarayıcının kendi tanıyıcısı metni üretir ve
            ürün o metni arar; hiçbir kayıt bizim sunucumuza ulaşmaz. Ses
            kişisel veridir ve onu taşımak, çözdüğünden çok sorun getirirdi.

            DÜĞME YALNIZ YETENEK VARSA ÇİZİLİR: işaretleme inert bir
            `<template>` içinde indi ve DOM'a ancak burada girer.
        */
        var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        var voiceTemplate = document.getElementById('menu-voice-template');
        var searchRow = document.querySelector('[data-search-row]');

        if (Recognition && voiceTemplate && searchRow && searchInput && voiceStatus) {
            searchRow.appendChild(voiceTemplate.content.cloneNode(true));

            var voiceButton = document.getElementById('menu-voice');
            var recognition = null;

            voiceButton.addEventListener('click', function () {
                // İkinci basış dinlemeyi bitirir: başlattığı şeyi
                // durduramayan bir düğme, misafiri beklemeye mahkûm eder.
                if (recognition) {
                    recognition.stop();
                    // "Dinliyorum" cümlesi ekranda kalmaz: misafir
                    // durdurdu, ürün hâlâ dinliyormuş gibi görünmemeli.
                    voiceStatus.textContent = '';

                    return;
                }

                recognition = new Recognition();

                // Tanıma dili ARAYÜZÜN dilidir: misafir menüyü hangi dilde
                // okuyorsa büyük ihtimalle o dilde konuşur.
                if (document.documentElement.lang) {
                    recognition.lang = document.documentElement.lang;
                }

                recognition.interimResults = false;
                recognition.maxAlternatives = 1;

                recognition.onresult = function (event) {
                    searchInput.value = event.results[0][0].transcript;
                    voiceStatus.textContent = '';
                    apply();
                };

                recognition.onerror = function (event) {
                    /*
                        SESSİZ BAŞARISIZLIK YOK. Misafir düğmeye basıp
                        hiçbir şey olmadığını görürse ürünü bozuk sanır.
                        Reddedilen izin kendi cümlesini alır ve ikisi de
                        yazarak aramanın hâlâ açık olduğunu söyler.
                    */
                    voiceStatus.textContent =
                        event.error === 'not-allowed' || event.error === 'service-not-allowed'
                            ? say('voiceDenied')
                            : say('voiceError');
                };

                recognition.onend = function () {
                    recognition = null;
                    voiceButton.setAttribute('aria-pressed', 'false');
                };

                voiceButton.setAttribute('aria-pressed', 'true');
                voiceStatus.textContent = say('voiceListening');

                /*
                    MİKROFON İZNİ TAM BURADA İSTENİR — sayfa açılışında
                    değil. Karekodu okutan misafir menüye bakmak istiyor;
                    sormadığı bir soruya izin istemek çoğu zaman "hayır"
                    ile döner ve o "hayır", gerçekten kullanmak istediği
                    anda da karşısına çıkar.

                    BAŞLATMA REDDEDİLİRSE DÜĞME KİLİTLENMEZ. Güvensiz bir
                    bağlamda bu satır doğrudan hata fırlatır ve `onend` hiç
                    çalışmaz; yakalamasaydık `recognition` dolu kalır ve
                    ikinci basış "durdur" sanılırdı — düğme bir daha hiç
                    başlamazdı.
                */
                try {
                    recognition.start();
                } catch (error) {
                    recognition = null;
                    voiceButton.setAttribute('aria-pressed', 'false');
                    voiceStatus.textContent = say('voiceError');
                }
            });
        }

        @isset($ordering)
        /*
            ═══ SEPET VE SİPARİŞ ONAYI — `docs/115` S3 (FF-178) ═══

            BU BLOK YALNIZ SEPET ÇİZİLDİĞİNDE İNER. Sipariş alma kapalı bir
            restoranın menüsünü açan misafire, hiçbir düğmesi olmayan bir
            sepetin betiğini indirtmek boşuna bayt yemektir — ve bu sayfa
            masada, çoğu zaman zayıf bir hücresel bağlantıda açılıyor
            (`docs/113` §8).

            SEPET CİHAZDA YAŞAR. Sunucuda misafir oturumu yok ve olmayacak:
            sepeti orada tutmak, hiç sipariş vermeyecek her misafir için
            satır yazmak olurdu. Sunucuya giden tek şey GÖNDERİLEN sipariştir
            ve o da tek bir POST'tur.

            ÜÇ KAPI VAR VE ÜÇÜ DE AYNI SÖZÜ TUTAR: sunucu sepeti yalnız
            gerçekten sipariş verilebiliyorsa BASAR; `<dialog>` desteği yoksa
            betik onu AÇMAZ; cihaz deposu çalışmıyorsa (gizli pencere,
            kapatılmış site verisi) yine AÇMAZ. Hatırlamadığı bir listeyi
            hatırlıyormuş gibi göstermek, misafirin siparişini sessizce
            kaybetmektir.
        */
        (function () {
            var orderNode = document.getElementById('guest-order');
            var panel = document.querySelector('[data-cart-panel]');
            var opener = document.querySelector('[data-cart-open]');
            var lineTemplate = document.getElementById('cart-line-template');
            var liveBox = document.querySelector('[data-cart-live]');

            if (!orderNode || !panel || !opener || !lineTemplate || !liveBox) {
                return;
            }

            // `<dialog>` yoksa odak tuzağını, `Esc`i ve arka planı elle
            // yazmak gerekirdi — bu paketin kararı kütüphane eklememektir.
            if (typeof panel.showModal !== 'function') {
                return;
            }

            var store;

            try {
                store = window.localStorage;
                store.setItem('zabuno.probe', '1');
                store.removeItem('zabuno.probe');
            } catch (error) {
                return;
            }

            var ORDER;

            try {
                ORDER = JSON.parse(orderNode.textContent || '{}');
            } catch (error) {
                return;
            }

            if (!ORDER.submitPath) {
                return;
            }

            var M = ORDER.money || {};
            var main = document.querySelector('main');
            // Sepet MENÜYE bağlıdır: aynı restoranın iki menüsü ayrı sepet
            // tutar, yoksa kahvaltıda seçilen bir ürün akşam menüsünde
            // ortaya çıkardı.
            var KEY = 'zabuno.cart.' + ((main && main.dataset.menuKey) || 'menu');
            // Ekran adedi burada durur; SINIR SUNUCUDADIR
            // (`BuildOrderLines::MAX_QUANTITY_PER_LINE`). Ekranın durması
            // misafire nazik davranmaktır, kapı değil.
            var MAX_PER_LINE = 20;

            var lineBox = panel.querySelector('[data-cart-lines]');
            var emptyNote = panel.querySelector('[data-cart-empty]');
            var foot = panel.querySelector('[data-cart-foot]');
            var totalBox = panel.querySelector('[data-cart-total]');
            var statusBox = panel.querySelector('[data-cart-status]');
            var badge = opener.querySelector('[data-cart-count]');
            var sendButton = panel.querySelector('[data-cart-submit]');

            function read() {
                try {
                    var raw = JSON.parse(store.getItem(KEY) || '{}');

                    return raw && typeof raw === 'object' ? raw : {};
                } catch (error) {
                    // Bozuk bir kayıt sepeti kilitlemez: boş sepet, açılmayan
                    // bir sayfadan iyidir.
                    return {};
                }
            }

            function write(state) {
                try {
                    store.setItem(KEY, JSON.stringify(state));
                } catch (error) {
                    // Depo doluysa sepet bu oturumda yaşar; misafir yine
                    // sipariş gönderebilir.
                }
            }

            /*
                PARA BİÇİMİ SUNUCUDAN GELİR, BURADA DOĞMAZ.

                Bu işlev hiçbir işaret ya da ayırıcı UYDURMAZ: hepsini
                `MoneyFormatContract`ın söktüğü kalıptan alır. Kuruşu sabit
                bir sayıya bölmek yende ve dinarda yanlış fiyat üretirdi ve
                fiyat, restoranın misafirine verdiği taahhüttür.
            */
            function money(minor) {
                var digits = M.digits || 0;
                var text = String(Math.round(minor));

                while (text.length <= digits) {
                    text = '0' + text;
                }

                var whole = digits ? text.slice(0, text.length - digits) : text;
                var fraction = digits ? text.slice(text.length - digits) : '';

                if (M.group) {
                    whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, M.group);
                }

                var body = whole + (digits ? M.decimal + fraction : '');

                // Latin rakamı evrensel değildir: biçimlendirici başka bir
                // rakam takımı ürettiyse sayı oraya kaydırılır.
                if (M.zero && M.zero !== '0') {
                    var shift = M.zero.charCodeAt(0) - 48;

                    body = body.replace(/[0-9]/g, function (character) {
                        return String.fromCharCode(character.charCodeAt(0) + shift);
                    });
                }

                return (M.prefix || '') + body + (M.suffix || '');
            }

            function itemNode(id) {
                return document.querySelector('[data-menu-item-id="' + String(id).replace(/[^0-9]/g, '') + '"]');
            }

            function render() {
                var state = read();
                var count = 0;
                var total = 0;

                lineBox.textContent = '';

                Object.keys(state).forEach(function (id) {
                    var node = itemNode(id);
                    var quantity = Number(state[id]) || 0;

                    /*
                        MENÜDEN KALKMIŞ SATIR SEPETTE DE DURMAZ.

                        Adı ve fiyatı cihazda saklasaydık sepet, dünkü fiyatı
                        bugün gösterirdi — ve misafir o fiyatı masada isterdi.
                        İkisi de her seferinde sayfanın kendisinden okunur.
                    */
                    if (!node || quantity < 1) {
                        delete state[id];

                        return;
                    }

                    var price = Number(node.getAttribute('data-item-price-minor')) || 0;
                    var row = lineTemplate.content.cloneNode(true);

                    row.querySelector('[data-cart-line]').setAttribute('data-cart-line', id);
                    row.querySelector('[data-line-name]').textContent = node.getAttribute('data-item-name') || '';
                    row.querySelector('[data-line-qty]').textContent = String(quantity);
                    row.querySelector('[data-line-total]').textContent = money(price * quantity);
                    lineBox.appendChild(row);

                    count += quantity;
                    total += price * quantity;
                });

                write(state);

                totalBox.textContent = money(total);
                badge.textContent = String(count);
                badge.hidden = count === 0;
                emptyNote.hidden = count > 0;
                foot.hidden = count === 0;
                // Düğmenin etiketi sayıyı da söyler: rozeti göremeyen misafir
                // için renk ve konum hiçbir şey anlatmaz (WCAG 1.4.1).
                opener.setAttribute(
                    'aria-label',
                    say('cartOpen') + ' — ' + say('cartCount').replace('{count}', String(count)),
                );
            }

            function change(id, quantity) {
                var state = read();

                if (quantity < 1) {
                    delete state[id];
                } else {
                    state[id] = Math.min(quantity, MAX_PER_LINE);
                }

                write(state);
                render();
            }

            function quantityOf(id) {
                return Number(read()[id]) || 0;
            }

            document.addEventListener('click', function (event) {
                var addButton = event.target.closest ? event.target.closest('[data-cart-add]') : null;

                if (!addButton) {
                    return;
                }

                var item = addButton.closest('[data-item]');
                var id = item && item.getAttribute('data-menu-item-id');

                if (!id) {
                    return;
                }

                change(id, quantityOf(id) + 1);
                /*
                    EKLEME SESSİZ OLMAZ.

                    Cümle SEPETİN İÇİNE yazılmaz: ürün menüden eklenirken
                    sepet kapalıdır ve kapalı bir panele yazılan onay, hiç
                    yazılmamış onaydır. Gören misafirin geri bildirimi
                    başlıktaki rozettir; bu satır görmeyen misafir içindir.
                */
                liveBox.textContent = say('cartAdded').replace('{name}', item.getAttribute('data-item-name') || '');
            });

            panel.addEventListener('click', function (event) {
                var target = event.target.closest ? event.target : null;

                if (!target) {
                    return;
                }

                if (target.closest('[data-cart-close]')) {
                    panel.close();

                    return;
                }

                var line = target.closest('[data-cart-line]');

                if (!line) {
                    return;
                }

                var id = line.getAttribute('data-cart-line');

                if (target.closest('[data-cart-remove]')) {
                    change(id, 0);
                } else if (target.closest('[data-cart-inc]')) {
                    change(id, quantityOf(id) + 1);
                } else if (target.closest('[data-cart-dec]')) {
                    change(id, quantityOf(id) - 1);
                }
            });

            opener.addEventListener('click', function () {
                // Önceki gönderimin cümlesi yeni bir sepette durmaz.
                statusBox.textContent = '';
                render();
                panel.showModal();
            });

            /*
                DÖRT RET, DÖRT CÜMLE (`docs/115` §7 S2).

                Masadaki misafir için bunlar apayrı durumlardır: bitmiş bir
                ürünü sepetten çıkarır, kapanmış bir mutfakta personele
                sorar, masaya bağlı olmayan bir kodda masasındaki kodu
                okutur. Tek bir "sipariş gönderilemedi" cümlesi onu aynı
                düğmeye tekrar bastırır ve hangisini düzeltebileceğini asla
                öğretmez.
            */
            var REFUSALS = {
                'out_of_stock': 'refusedOutOfStock',
                'item_unavailable': 'refusedItemUnavailable',
                'ordering_closed': 'refusedOrderingClosed',
                'table_unknown': 'refusedTableUnknown',
                'entitlement_required': 'refusedEntitlementRequired',
                'too_many_open_orders': 'refusedTooManyOpenOrders',
                'too_many_lines': 'refusedTooManyLines',
                'order_not_saved': 'refusedNotSaved'
            };

            function refusal(data) {
                var key = REFUSALS[data && data.reason];
                var sentence = key ? say(key) : '';
                // HANGİ ÜRÜN olduğu söylenir: sepette beş satır varken "bir
                // şey bitmiş" demek, misafire sepeti tek tek denetletirdi.
                var node = data && data.menuItemId ? itemNode(data.menuItemId) : null;

                // Ada ihtiyaç duyan bir cümleyi adsız kurmayız: boşlukla
                // başlayan bir cümle, cümle değildir.
                if (!sentence || (sentence.indexOf('{name}') !== -1 && !node)) {
                    return say('refusedUnknown');
                }

                return sentence.replace('{name}', node ? node.getAttribute('data-item-name') || '' : '');
            }

            sendButton.addEventListener('click', function () {
                var state = read();
                var items = Object.keys(state).map(function (id) {
                    return { menuItemId: Number(id), quantity: Number(state[id]) };
                });

                if (!items.length) {
                    return;
                }

                sendButton.disabled = true;
                statusBox.textContent = say('cartSending');

                fetch(ORDER.submitPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ items: items })
                })
                    .then(function (response) {
                        return response
                            .json()
                            .catch(function () {
                                return {};
                            })
                            .then(function (data) {
                                sendButton.disabled = false;

                                /*
                                    SEPET YALNIZ 201'DE TEMİZLENİR.

                                    Ret sonrası temizlemek, misafirin tek tek
                                    seçtiği listeyi tam da düzeltmesi gereken
                                    anda silmek olurdu. 201 ise "mutfak
                                    başladı" DEMEK DEĞİLDİR: gönderilen bir
                                    taleptir, garson onaylayınca iş olur —
                                    cümle bunu söyler ve uydurma bir süre
                                    vermez.
                                */
                                if (response.status === 201) {
                                    write({});
                                    render();
                                    statusBox.textContent = say('orderPlaced');

                                    return;
                                }

                                statusBox.textContent = refusal(data);
                            });
                    })
                    .catch(function () {
                        sendButton.disabled = false;
                        // Ağ koptuğunda belirsizlik misafire yıkılmaz: sepet
                        // durur ve cümle personele sormayı önerir.
                        statusBox.textContent = say('refusedOffline');
                    });
            });

            // Denetimler ancak buraya kadar gelindiğinde çizilir: üç kapının
            // üçü de açıksa sepet gerçekten çalışıyor demektir.
            Array.prototype.slice.call(document.querySelectorAll('[data-cart-add]')).forEach(function (button) {
                button.hidden = false;
            });

            opener.hidden = false;
            render();
        })();
        @endisset

        @isset($rating)
        /*
            MİSAFİRİN OYU (`docs/116` §4).

            ═══ DENETİM ÇİZİLİ GELMEZ, AÇILIR ═══

            Yıldızlar `hidden` iniyor ve ancak buraya kadar gelindiğinde
            açılıyor. JavaScript çalışmayan bir tarayıcıda basınca hiçbir şey
            olmayan beş düğme göstermek, misafire olmayan bir yetenek vaat
            etmektir — sepette de aynı kural yazılı.

            Puanın KENDİSİ ve sahibin yanıtı `hidden` DEĞİL: onlar düz
            metindir ve betik hiç çalışmasa da doğrudur.

            ═══ SUNUCU HAYIR DERSE YILDIZ GERİ SÖNER ═══

            Basılan yıldızı bırakıp cümleyi değiştirmek, misafire oyunun
            durduğunu göstermek olurdu. Ekranın söylediği şey defterin
            hâliyle aynı kalmalı.

            ═══ BU BLOK MASASIZ BİR SAYFAYA HİÇ İNMEZ ═══

            Puanlama koşulunun içinde duruyor: afişten menüye bakan misafir
            oy veremediği için bu betiğin tek baytını bile indirmiyor.
        */
        (function () {
            var config = {};

            try {
                var node = document.getElementById('guest-rating');
                config = node ? JSON.parse(node.textContent || '{}') : {};
            } catch (error) {
                return;
            }

            if (!config.submitPath) {
                return;
            }

            var live = document.querySelector('[data-rate-live]');

            function paint(group, score) {
                Array.prototype.slice.call(group.querySelectorAll('[data-rate]')).forEach(function (button) {
                    button.setAttribute(
                        'aria-pressed',
                        Number(button.getAttribute('data-rate')) <= score ? 'true' : 'false'
                    );
                });
            }

            Array.prototype.slice.call(document.querySelectorAll('[data-rate-group]')).forEach(function (group) {
                var row = group.closest('[data-menu-item-id]');

                if (!row) {
                    return;
                }

                group.hidden = false;

                group.addEventListener('click', function (event) {
                    var button = event.target.closest ? event.target.closest('[data-rate]') : null;

                    if (!button) {
                        return;
                    }

                    var score = Number(button.getAttribute('data-rate'));

                    paint(group, score);

                    fetch(config.submitPath, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({
                            menuItemId: Number(row.getAttribute('data-menu-item-id')),
                            score: score,
                        }),
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                paint(group, 0);
                            }

                            if (live) {
                                /*
                                    "KAYDEDİLDİ" DENİR, "EKLENDİ" DENMEZ.

                                    Oy ağırlıklandırmaya girmemiş olabilir
                                    (ani yığılma) ve o karar algoritmanındır.
                                    Kaydedildiği ise her durumda doğrudur —
                                    sinyal deftere yazıldı ve orada duruyor.
                                */
                                live.textContent = response.ok ? say('ratingRecorded') : say('ratingFailed');
                            }
                        })
                        .catch(function () {
                            paint(group, 0);

                            if (live) {
                                live.textContent = say('ratingOffline');
                            }
                        });
                });
            });
        })();
        @endisset

        /*
            MENÜ MÜHENDİSLİĞİ ÖLÇÜMÜ — `docs/84`.

            Ölçüm misafirin işi DEĞİLDİR: hiçbir hata onun ekranında
            görünmez, hiçbir istek sayfanın açılmasını bekletmez.
        */
        var menuKey = (document.querySelector('main') || {}).dataset
            ? document.querySelector('main').dataset.menuKey
            : null;

        if (!menuKey) {
            return;
        }

        function send(events) {
            if (!events.length) {
                return;
            }

            var body = JSON.stringify({ menuKey: menuKey, events: events });

            /*
                `sendBeacon` sayfa kapanırken bile gider ve sayfayı
                bekletmez. Yoksa `fetch` ile denenir; ikisi de yoksa ölçüm
                sessizce yapılmaz — misafirin menüsü her hâlükârda açılır.
            */
            try {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('/q/events', new Blob([body], { type: 'application/json' }));

                    return;
                }

                fetch('/q/events', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    keepalive: true,
                }).catch(function () {});
            } catch (error) {
                // Ölçüm best-effort.
            }
        }

        var pendingViews = [];
        var reportedItems = {};

        function flushViews() {
            var batch = pendingViews;
            pendingViews = [];
            send(batch);
        }

        if ('IntersectionObserver' in window) {
            var timers = {};

            var observer = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        var id = entry.target.getAttribute('data-menu-item-id');

                        if (!id || reportedItems[id]) {
                            return;
                        }

                        if (!entry.isIntersecting) {
                            // Kaydırırken hızla geçilen satır SAYILMAZ.
                            window.clearTimeout(timers[id]);
                            delete timers[id];

                            return;
                        }

                        timers[id] = window.setTimeout(function () {
                            reportedItems[id] = true;
                            observer.unobserve(entry.target);
                            pendingViews.push({ type: 'item_view', menuItemId: Number(id) });
                        }, 1000);
                    });
                },
                // Satırın en az YARISI görünmeli: listede olması yetmez,
                // yoksa kırk ürünlük menüde kırk görüntülenme sayılırdı ve
                // "hangi ürün ilgi çekiyor" sorusu cevapsız kalırdı.
                { threshold: 0.5 },
            );

            Array.prototype.slice
                .call(document.querySelectorAll('[data-menu-item-id]'))
                .forEach(function (node) {
                    observer.observe(node);
                });

            // Toplu gönderim: kırk ürün için kırk istek atmak, misafirin
            // hücresel bağlantısını menünün kendisinden çok yorardı.
            window.setInterval(flushViews, 5000);
            window.addEventListener('pagehide', flushViews);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    flushViews();
                }
            });
        }

        var searchTimer = null;
        var reportedTerms = {};

        function reportNoResults(query, visibleCount) {
            window.clearTimeout(searchTimer);

            if (query === '' || visibleCount !== 0) {
                return;
            }

            /*
                Yazarken geçilen ara adımlar gönderilmez: "kar", "kari",
                "karid" hepsi sonuçsuzdur ama misafirin aradığı şey
                "karides"tir. Yarım saniye beklemek, niyeti aramadan ayırır.
            */
            searchTimer = window.setTimeout(function () {
                if (reportedTerms[query]) {
                    return;
                }

                reportedTerms[query] = true;
                send([{ type: 'search_no_results', term: query }]);
            }, 500);
        }
    })();
</script>
</body>
</html>
