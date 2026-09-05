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

        {{-- YER BURADA AÇIK KALIYOR. Kaynağın başlık satırında arama, tema,
             favori ve sepet düğmeleri var; onların arka ucu ayrı paketlerde
             geliyor. Satır esnek kurulduğu için o düğmeler geldiğinde kimlik
             bloğu kendiliğinden daralır ve bu dosyada düzen değişmez —
             320'deki 34+8 px'lik sabit payı korumak için yeni her düğme
             `var(--qr-tap)` genişliğinde ve `flex:none` olarak eklenir.
             Çalışmayan bir düğmeyi BUGÜNDEN çizmek ise ayrı bir şeydir ve
             yapılmaz: masadaki misafir ona basar ve hiçbir şey olmaz. --}}
    </div>
</header>

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
                            <li class="qr-menu-item{{ $isSoldOut ? ' qr-menu-item-sold-out' : '' }}" data-item data-item-name="{{ $item['productName'] }}" @if (($item['filterAllergens'] ?? '') !== '') data-item-allergens="{{ $item['filterAllergens'] }}" @endif @if (($item['filterPrice'] ?? null) !== null) data-item-price="{{ $item['filterPrice'] }}" @endif @isset($item['menuItemId']) id="item-{{ $item['menuItemId'] }}" data-menu-item-id="{{ $item['menuItemId'] }}" @endisset>
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
</x-zabuno>
{{-- Betik gövdesindeki sabitler de KULLANICI METNİDİR (`docs/85`).
     Harita JSON olarak basılır; betik onu okur ve tek bir cümle bile
     şablonda kalmaz. --}}
<script type="application/json" id="guest-text" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($gt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
