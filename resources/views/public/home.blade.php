@extends('public.layout')

{{-- ANA SAYFA — SAHNE (`docs/146`) + GERÇEK İDDİA (`docs/138`).

     ── METİN KATALOGDAN GELİR ────────────────────────────────────────────

     Blade'e gömülen bir dize hiçbir PO dosyasında görünmez; sahibi onu açıp
     çeviremez (`I18N-SSR-RATCHET-16`, borç `lang/untranslatable-debt.json`
     içinde SIFIR). Aşağıdaki her görünür sözcük `SiteText` ya da
     `HomeStory` üzerinden geliyor ve bir tanesi bile yeni değil.

     ── İDDİA UYDURULMAZ ──────────────────────────────────────────────────

     Bu sayfa iki paketin BİRLEŞİMİDİR ve birleşmede hangi tarafın neyi
     verdiği açıkça karara bağlandı:

       · SAHNE dili (`site-stage`, `site-deep`, `site-veil`, `site-field`,
         `scene-reveal`, `--scene-order`, `data-scene-progress`, `data-axis`,
         yörünge / tel kafes / veri hattı / morph) sahne motorundan gelir.
       · İÇERİK `App\Support\Site\HomeStory` üzerinden ÜRÜNÜN KENDİ
         envanterinden gelir ve başlıkları `ProductOverviewPage`in
         terimleriyle BİREBİR aynıdır.

     Eski model (dört uydurma "feature", dört genel "step") silindi ve
     sebebi içerik: o dört başlık ürünün envanterinden bağımsız yazılmıştı
     ve envanterle ayrışsa hiçbir şey kırılmazdı. Yenisinde ayrışma
     `SceneContractTest`in HOME-REAL-07 kapısını kırar.

     Sayfada YOK ve bilerek yok: müşteri logosu, referans, "1000+ restoran",
     ödül, canlı sayaç, uydurma grafik, "yakında" vaadi (HOME-HONEST-08).
     Yatırımcıya hazırlık uydurmayla değil, YAPILMIŞ İŞİ göstererek kurulur
     — bu yüzden sayfanın en uzun iki bölümü ürünün on iki PARÇASI ve yedi
     SINIRIDIR.

     ── DAR EKRAN TABAN, YAZIM SIRASI DA (`docs/118` E1) ──────────────────

     Bu dosyada tek bir kırılma noktası jetonu (`sm:`, `md:`…), tek bir
     `max-*` bastırması ve tek bir "mobilde gizle" yoktur. Kapılar:
     `HOME-FLUID-04` (`PublicHomeContractTest`) ve HOME-SCENE-01.

     ── DEKOR GEZİNMEYİ ÖRTMEZ ────────────────────────────────────────────

     Her sahne katmanı `aria-hidden` ve `pointer-events: none`
     (`site-shell.css`), katman sırası `--layer-scene-*` (0-2) — yani
     içeriğin (10), menünün (20), çerez şeridinin (30) ve atlama
     bağlantısının (50) hep ALTINDA. Kapı: HOME-SCENE-03/04. --}}
@section('title', $st['homeMetaTitle'])
@section('description', $st['homeMetaDescription'])

@section('content')
    <main id="main-content" class="site-home">
        {{-- ══ KAHRAMAN ══════════════════════════════════════════════════

             `.site-stage` sahnenin kabıdır: `overflow: clip` taşıyor, yani
             içindeki hiçbir katman sayfayı yatay olarak kaydıramaz
             (`site-shell.css`). Bu, 320 pikselde ölçülen ilk kuraldır.

             Katman sırası dıştan içe: yıldız alanı (tuval) en arkada,
             nebula onun önünde, hüzme ortada, ufuk en önde. Metin her
             zaman `.site-stage-content` içinde ve `--layer-content`
             seviyesinde — hiçbir dekoratif katman okunan bir sözcüğü
             örtemez. --}}
        <section class="site-stage site-deep site-veil home-hero" data-scene-progress>
            {{-- Yıldız alanı. Betik yoksa tuval boş kalır ve GÖRÜNMEZ
                 (`opacity: 0`), yani sayfada boş bir siyah dikdörtgen
                 durmaz — geriye nebulanın kendisi kalır.

                 SAYFA BAŞINA TEK TUVAL: ikinci bir WebGL bağlamının
                 maliyeti henüz ÖLÇÜLMEDİ ve ölçülmemiş bir maliyet ürüne
                 sokulmaz (SAHNE-B7). --}}
            <div class="site-stage-layer" aria-hidden="true">
                <canvas class="scene-canvas" data-scene="field" data-scene-sway="0.18" data-scene-speed="0.24" aria-hidden="true"></canvas>
            </div>

            {{-- Nebula EN UZAK düzlem: kaydırmada en yavaş hareket eder.
                 Eksen `xy` — yani hem aşağı hem YANA süzülüyor. Sahibin
                 "sağlı sollu hareket eden landing page" isteği burada bir
                 şeritte değil, kahramanın kendi katmanında. --}}
            <div class="site-stage-layer scene-plane" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            {{-- YÖRÜNGE. Üç halka, üç hız, ortadaki ters yönde. Yıldız alanı
                 UZAKLIĞI anlatır; yörünge İŞ yapıldığını anlatır ve bir uzay
                 şirketinin dağarcığında ikincisi birincisinden önce gelir.
                 Düzlem TERS eksende akıyor: iki katman aynı yöne kayarsa göz
                 tek bir blok görür, zıt yönde kaydıklarında aralarında
                 derinlik doğar.

                 Saf CSS — ikinci bir WebGL bağlamı AÇILMIYOR. --}}
            <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x-" aria-hidden="true">
                <span class="scene-orbit">
                    <span class="scene-orbit-ring" style="--scene-orbit-scale: 1; --scene-orbit-spin: 52s"></span>
                    <span class="scene-orbit-ring" data-spin="reverse" style="--scene-orbit-scale: 0.62; --scene-orbit-spin: 34s"></span>
                    <span class="scene-orbit-ring" style="--scene-orbit-scale: 0.34; --scene-orbit-spin: 21s"></span>
                </span>
            </div>

            {{-- Tarayıcı hüzme. Saf CSS: betiksiz de yaşar. --}}
            <div class="site-stage-layer" data-depth="mid" aria-hidden="true">
                <span class="scene-beam"></span>
            </div>

            {{-- Ufuk EN YAKIN düzlem ve bölümün ilerlemesini okur: sayfa
                 aşağı indikçe ışık güçlenir, eğri büyür. Üç düzlem, tek
                 kaydırma — parallax burada bir efekt değil, geometrinin
                 sonucu. --}}
            <div class="site-stage-layer scene-plane" data-plane="near" data-depth="front" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            {{-- Vinyet: yıldızların ÜSTÜNDE, metnin ALTINDA. Sıra kasıtlı —
                 perde okunan metnin arkasındaki zemini koyulaştırır, yani
                 kontrastı düşürmez YÜKSELTİR (`site-scene.css` §3). --}}
            <div class="site-stage-layer" data-depth="front" aria-hidden="true">
                <span class="scene-vignette"></span>
            </div>

            <div class="site-stage-content site-measure-stage home-hero-inner">
                {{-- GÖZ İZİ YOK — marka adı üst çubukta ZATEN yazıyor.

                     Ölçüldü (2026-09-08, 320×480): "Zabuno" satırı ve
                     boşluğu 36 piksel yiyordu ve o 36 piksel, birincil
                     düğmeyi ilk ekranın dışına iten farkın içindeydi. Aynı
                     sözcüğü 320 piksellik bir ekranda iki kez yazmak, en kıt
                     kaynağı tekrar için harcamaktır (`docs/118` E3). --}}
                <div class="home-hero-copy">
                <div class="home-hero-text scene-reveal">
                    <h1 class="site-display">{{ $st['homeHeroHeading'] }}</h1>
                    <p class="site-lede">{{ $st['homeHeroLead'] }}</p>
                </div>

                {{-- İKİ EYLEM, ÜÇ DEĞİL — VE İKİSİ DE AYNI KİŞİYE AİT.

                     Üçüncüsü (`/login`) kabuğun menüsünde zaten duruyor ve
                     `HOME-A11Y-02` onu ORADA arıyor. 320×480'de üç düğme üst
                     üste 132 piksel eder ve vaat cümlesini katlanmanın
                     altına iterdi.

                     İKİNCİ EYLEM DEĞİŞTİ (`HOME-PRICE-06`). Eskiden `/app`
                     idi: hesabı OLAN için bir kısayol. Ama ilk ekrandaki iki
                     yerin ikincisi, sayfanın kimin sorusunu cevapladığını
                     söyler — ve `/app`, hesabı olmayan ziyaretçiyi bir giriş
                     ekranına gönderip orada bırakıyordu. Ürünü ilk kez gören
                     birinin ikinci sorusu "nasıl girerim" değil, "bu bana
                     kaça mal olur"dur. O cevap sayfada VARDI ama en altta,
                     soru-cevap bölümünün sonunda duruyordu.

                     Sıra hâlâ düşünülmüştür: ilk düğme hesabı olmayanı
                     kaydeder, ikincisi henüz karar vermemişi fiyata götürür.
                     Etiket katalogdan (`site.pricing.heading`) ve soru-cevap
                     bağlantısıyla AYNI anahtardan geliyor — iki yerde iki
                     farklı sözcük, aynı sayfayı iki ayrı yer gibi
                     gösterirdi. `/app` silinmedi: kabuğun altbilgisinde
                     duruyor ve `HOME-SCENE-05` onu ORADA arıyor. --}}
                <nav aria-label="{{ $st['homeHeroActionsLabel'] }}" class="home-actions scene-reveal" style="--scene-order: 1">
                    <a href="/register" class="site-action site-cta" data-emphasis="true">{{ $st['homeHeroRegister'] }}</a>
                    <a href="/pricing" class="site-action site-cta">{{ $st['pricingHeading'] }}</a>
                </nav>

                {{-- Ücretsiz zincir bir kampanya değil, plan kataloğundaki
                     ölçülmüş bir olgu (`PlanCatalogueSeeder`). --}}
                <p class="home-hero-note scene-reveal" style="--scene-order: 2">{{ $st['homeHeroNote'] }}</p>
                </div>

                {{-- Ürünün gerçek adımları: sahte bir yönetim ekranı ya da canlı veri değil. --}}
                <aside class="home-product-map site-glass scene-reveal" aria-label="{{ $st['navHowItWorks'] }}" style="--scene-order: 3">
                    <p class="home-product-map-heading">{{ $st['homeChainHeading'] }}</p>
                    <ol class="home-product-map-flow" role="list">
                        @foreach ($story['chain'] as $index => $step)
                            <li>
                                <span class="home-product-map-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span>{{ $step['title'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <a href="#how-it-works" class="site-action home-product-map-link">{{ $st['navHowItWorks'] }}</a>
                </aside>
            </div>
        </section>

        {{-- ══ AKAN BANTLAR ══════════════════════════════════════════════

             Üç şerit, iki yön: biri satır başına, öteki satır sonuna akar,
             üçüncüsü en yavaş ve en sönük olanıdır. Yön MANTIKSALDIR —
             sağdan sola yazılan bir dilde hepsi kendiliğinden yer değiştirir
             (`site-scene.css` §4).

             İÇERİK GERÇEKTİR ve artık ENVANTERDEN: her madde ürünün kendi
             genel bakış sayfasında da yazan bir PARÇA ya da zincirin bir
             ADIMIDIR. `aria-hidden`, çünkü aynı sözcükler birkaç santim
             aşağıda okunabilir bir bölümde zaten duruyor; ekran okuyucuya
             kopyayı ikinci kez okutmak gürültü olurdu.

             Şerit BLADE'de iki kez basılıyor, betikte değil: döngünün
             dikişsiz olması için ikinci kopya gerekiyor ve betiği
             engellenmiş bir ziyaretçide de bant tam görünmeli. --}}
        @php
            /* Bant maddeleri ENVANTERDEN, elle yazılmış tek bir sözcük yok.
               Liste iki kez basılacağı için burada bir kez kuruluyor —
               şablonda iki ayrı liste olsaydı, biri güncellenip öteki
               unutulurdu.

               Şerit `parts`ın TAMAMINI değil ilk yarısını taşıyor: on iki
               madde bir bantta okunmaz, akar geçer. Aşağıdaki bölüm on
               ikisini de sayıyor; bandın işi haber vermek, saymak değil. */
            $driftParts = array_map(
                static fn (array $part): string => $part['title'],
                array_slice($story['parts'], 0, 6),
            );

            $driftChain = array_map(
                static fn (array $step): string => $step['title'],
                $story['chain'],
            );

            /* ÜÇÜNCÜ ŞERİT. İki şerit bir ZITLIK kurar ama bir DERİNLİK
               kurmaz: göz iki hızı karşılaştırır ve orada durur. Üçüncü
               şerit en yavaş ve en sönük olanıdır; onunla birlikte hızlar
               bir sıraya dizilir ve şeritler bir yüzey değil, bir HACİM
               okunur. */
            $driftDepth = array_merge($driftParts, $driftChain);
        @endphp

        <div class="site-stage home-drift" aria-hidden="true">
            <div class="site-bleed scene-drift" data-direction="start">
                <div class="scene-drift-track">
                    @foreach ([1, 2] as $pass)
                        @foreach ($driftParts as $label)
                            <span class="scene-drift-item">{{ $label }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="site-bleed scene-drift" data-direction="end">
                <div class="scene-drift-track">
                    @foreach ([1, 2] as $pass)
                        @foreach ($driftChain as $label)
                            <span class="scene-drift-item">{{ $label }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- EN UZAK ŞERİT: en yavaş, en sönük. Hızlar sıraya dizilince
                 şeritler bir yüzey değil bir HACİM okunur. --}}
            <div class="site-bleed scene-drift" data-direction="start" data-depth-lane="far">
                <div class="scene-drift-track" style="--scene-drift-duration: 74s">
                    @foreach ([1, 2] as $pass)
                        @foreach ($driftDepth as $label)
                            <span class="scene-drift-item">{{ $label }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══ ZİNCİR ════════════════════════════════════════════════════

             `id="how-it-works"` KORUNDU: kabuğun gezintisi (`SiteNavigation`)
             bu çıpaya işaret ediyor.

             Altı adım 320 pikselde doğal okuma sırasındadır, alan büyüdükçe
             ızgara kendiliğinden çoğalır. Sahne mevcut CSS katmanlarını
             kullanır; ikinci bir tuval eklenmez. --}}
        <section id="how-it-works" aria-labelledby="how-it-works-heading" class="site-stage site-deep" data-scene-progress>
            {{-- Nebula katmanı ŞEKİL DEĞİŞTİRİYOR (`scene-morph`): bant
                 kaydırıldıkça ışık halesinin sınırı dar bir kubbeden geniş
                 bir yaya açılıyor — aynı `--scene-progress`, üçüncü bir
                 yüz. --}}
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x-" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            {{-- TEL KAFES ZEMİN. Yıldız alanı derinlik verir ama ZEMİN
                 vermez; sahnenin "nerede" olduğu sorusunun cevabı yoktu.
                 Ufka doğru daralan çizgiler, bakanın bir yüzeyin üstünde
                 durduğunu söyler. Saf CSS: iki `repeating-linear-gradient`
                 ve tek bir `rotateX`. --}}
            <div class="site-stage-layer" aria-hidden="true">
                <span class="scene-grid"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-content">
                <div class="site-measure-page home-band-head">
                    <div class="home-head scene-reveal">
                        <h2 id="how-it-works-heading" class="site-display-2">{{ $st['homeChainHeading'] }}</h2>
                        <p class="home-lead">{{ $st['homeChainLead'] }}</p>
                    </div>
                </div>

                {{-- Altı adım doğal akışta: keşif yatay kaydırmaya bağlı değil. --}}
                <ol class="home-rail site-measure-page" role="list" aria-label="{{ $st['navHowItWorks'] }}">
                    @foreach ($story['chain'] as $index => $step)
                        <li class="site-panel site-lit home-rail-card scene-reveal" style="--scene-order: {{ $index }}">
                            {{-- Numara ekran okuyucuya `<ol>` üzerinden zaten
                                 ulaşır; rozet onu İKİNCİ kez söylemez. --}}
                            <span class="home-step-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="site-display-3">{{ $step['title'] }}</h3>
                            <p class="home-card-body">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ══ PARÇALAR ══════════════════════════════════════════════════

             `id="features"` KORUNDU: dış bağlantılar ve kabuğun gezintisi bu
             çıpaya işaret ediyor (`SiteNavigation`), adı değiştirmek çalışan
             bağlantıları kırardı.

             On iki başlık `ProductOverviewPage`in terimleriyle birebir
             aynı; ölçen kapı HOME-REAL-07.

             Kartlar `3D görünen 2D`: kalınlık `site-panel`den (parıltı +
             kenar + renkli gölge), eğilme `scene-tilt`ten geliyor. Eğilme
             YALNIZ işaretleyicili cihazda doğar — dokunmada `hover` yoktur
             ve parmak kartın üstünü kapatır (`TOUCH-FIRST-INTERFACE` §2). --}}
        <section id="features" aria-labelledby="features-heading" class="site-stage site-field">
            {{-- `.site-stage` BURADA ZORUNLU, bir süs değil: `.site-field`in
                 ışık katmanı kabın %30 dışına taşar (`site-identity.css` §6)
                 ve kırpılmazsa sayfayı YATAY olarak kaydırır — 320 pikselde
                 ölçülen ilk kusur. Kırpma `.site-stage`in işi. --}}
            {{-- VERİ HATTI, parçaların ARKASINDA. Yıldız ve yörünge MEKÂN
                 anlatır; veri hattı İŞLEM anlatır — ve bu bölüm tam olarak
                 ürünün ne YAPTIĞINI sayıyor. Çizgi durur, ışık akar; her hat
                 farklı hızda, çünkü eşit hızda giden paketler bir ışık
                 çubuğu gibi okunur, bir ağ gibi değil. --}}
            <div class="site-stage-layer scene-plane" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-conduit">
                    <span class="scene-conduit-line" style="--scene-conduit-run: 9s"></span>
                    <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 13s; --scene-conduit-delay: 1.1s"></span>
                    <span class="scene-conduit-line" style="--scene-conduit-run: 7s; --scene-conduit-delay: 2.3s"></span>
                    <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 16s; --scene-conduit-delay: 0.4s"></span>
                </span>
            </div>

            <div class="site-measure-page home-band">
                {{-- GÖZ İZİ YOK, ve bu ÖLÇÜLDÜ.

                     İlk sürüm başlığın üstüne `site.nav.features` etiketini
                     koyuyordu. Katalogda o anahtarın karşılığı da "Features"
                     — yani ekranda aynı sözcük iki kez, alt alta yazıyordu
                     (2026-09-08, 1280×800 ekran görüntüsü). Bir göz izi,
                     başlığı SINIFLANDIRIR; onu tekrar etmez. --}}
                {{-- BAŞLIK VE IZGARA ZIT YÖNLERDE SÜZÜLÜR.

                     Mesafe `--motion-drift-near` (320'de 10 piksel, geniş
                     ekranda 28): okunan bir metnin altındaki yatay hareket
                     BÜYÜK olamaz — satırı kovalayan bir göz, okuduğunu
                     kaybeder. Zıtlık burada hızdan değil YÖNDEN geliyor. --}}
                <div class="home-head scene-reveal scene-plane" data-plane="near" data-axis="x-">
                    <h2 id="features-heading" class="site-display-2">{{ $st['homePartsHeading'] }}</h2>
                    <p class="home-lead">{{ $st['homePartsLead'] }}</p>
                </div>

                <ul class="home-grid scene-plane" role="list" data-plane="near" data-axis="x">
                    @foreach ($story['parts'] as $index => $part)
                        <li class="home-feature scene-tilt scene-reveal" @if ($index === 0) data-featured @endif style="--scene-order: {{ $index }}">
                            <div class="site-panel site-lit scene-tilt-face home-card">
                                <h3 class="site-display-3">{{ $part['title'] }}</h3>
                                <p class="home-card-body">{{ $part['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ══ SINIRLAR — ürünün NE OLMADIĞI ═════════════════════════════

             Bir tanıtım sayfasının en zor bölümü ve bu ürünün en dürüst
             kanıtı. Gerekçesi `/urun/` sayfasında yazılı: yanlış varsayımın
             bedeli SERVİS SIRASINDA ödenir — var sandığı bir özelliğin
             olmadığını, hiçbir şeyi değiştiremeyeceği saatte öğrenir.

             Yedi başlık `ProductOverviewPage`in "What Zabuno is not"
             bloğuyla birebir aynı; ölçen kapı HOME-REAL-07.

             SAHNE BURADA SÖNÜKTÜR ve bu bir karar: bir sınırın okunduğu yer,
             dikkatin bölünmemesi gereken yerdir. Bant derindir (kompozisyon
             kopmasın) ama ne tuval ne yörünge ne veri hattı taşır — yalnız
             morph eden bir nebula ve ufuk. --}}
        <section id="limits" aria-labelledby="limits-heading" class="site-stage site-deep" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-content site-measure-page home-band">
                <div class="home-head scene-reveal">
                    <h2 id="limits-heading" class="site-display-2">{{ $st['homeLimitsHeading'] }}</h2>
                    <p class="home-lead">{{ $st['homeLimitsLead'] }}</p>
                </div>

                <ul class="home-limits" role="list">
                    @foreach ($story['limits'] as $index => $limit)
                        <li class="home-limit scene-reveal" style="--scene-order: {{ $index }}">
                            <h3>{{ $limit['title'] }}</h3>
                            <p class="home-card-body">{{ $limit['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ══ FİYAT ═════════════════════════════════════════════════════

             Rakam plan kataloğundan gelir, sayfaya elle yazılmaz
             (`docs/88`). Sahne burada susar: bir fiyatın okunduğu yer,
             dikkatin bölünmemesi gereken yerdir.

             ÖLÇÜ `form` DEĞİL `page`: fiyat bölümü kurumsal yüzey diline
             geçince dört plan bir IZGARA oldu (`site-pages.css` §3); okuma
             genişliğindeki bir kapta ızgara iki sütuna sıkışıyor ve kartlar
             arasında karşılaştırma yapılamıyordu (2026-09-08, 1280×800
             ekran görüntüsü). Bir fiyat tablosu okunan bir paragraf değil,
             KARŞILAŞTIRILAN bir tablodur. --}}
        <div class="site-measure-page home-prose scene-reveal">
            @include('public.partials.pricing')
        </div>

        {{-- ══ SSS ═══════════════════════════════════════════════════════

             `<details>` tabandır: tarayıcının kendi açılır kapanır düğmesi,
             klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betiksiz
             açılır. Cevap metni KAPALIYKEN DE HTML'dedir — arama motoru ve
             betik çalıştırmayan bot onu görür (HOME-SCENE-05).

             480 piksellik bir ekranda beş açık soru-cevap 600 piksel eder;
             kapalı başlamak, okuyucuya hangi soruyu açacağını SEÇTİRİR. --}}
        <section id="faq" aria-labelledby="faq-heading" class="site-measure-page home-prose">
            <div class="home-head scene-reveal">
                <h2 id="faq-heading" class="site-display-2">{{ $st['homeFaqHeading'] }}</h2>
            </div>

            <div class="home-faq">
                @foreach ([
                    ['q' => $st['homeFaqWhatQuestion'], 'a' => $st['homeFaqWhatAnswer']],
                    ['q' => $st['homeFaqAccountQuestion'], 'a' => $st['homeFaqAccountAnswer']],
                    ['q' => $st['homeFaqInstallQuestion'], 'a' => $st['homeFaqInstallAnswer']],
                    ['q' => $st['homeFaqPosQuestion'], 'a' => $st['homeFaqPosAnswer']],
                    ['q' => $st['faqCostQuestion'], 'a' => $st['faqCostAnswer']],
                ] as $index => $entry)
                    <details class="site-panel home-faq-item scene-reveal" style="--scene-order: {{ $index }}">
                        <summary class="home-faq-question">{{ $entry['q'] }}</summary>
                        <p class="home-faq-answer">{{ $entry['a'] }}</p>
                    </details>
                @endforeach
            </div>

            {{-- Fiyat sorusunun cevabı bir SAYFADIR; bağlantı cümlenin
                 içinde değil, kendi satırında ve 44 piksel (`docs/117` K1).
                 Cümle içine gömülmüş bir bağlantı dar ekranda 18 piksel
                 yüksekliğinde kalıyor ve parmakla ıskalanıyor. --}}
            <a href="/pricing" class="site-action site-cta home-faq-link">{{ $st['pricingHeading'] }}</a>
        </section>

        {{-- ══ İLETİŞİM — kapanış sahnesi ════════════════════════════════ --}}
        <section id="contact" aria-labelledby="contact-heading" class="site-stage site-deep" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            {{-- Kapanışta yörünge geri geliyor: sayfa açıldığı fikirle
                 kapanıyor. Halkalar kahramandakinden YAVAŞ — bir kapanış
                 sahnesi, ziyaretçiyi eylemden alıkoymaz. --}}
            <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x-" aria-hidden="true">
                <span class="scene-orbit">
                    <span class="scene-orbit-ring" style="--scene-orbit-scale: 1; --scene-orbit-spin: 68s"></span>
                    <span class="scene-orbit-ring" data-spin="reverse" style="--scene-orbit-scale: 0.55; --scene-orbit-spin: 44s"></span>
                </span>
            </div>

            <div class="site-stage-layer" data-depth="mid" aria-hidden="true">
                <span class="scene-beam"></span>
            </div>

            <div class="site-stage-layer" data-depth="front" aria-hidden="true">
                <span class="scene-vignette"></span>
            </div>

            <div class="site-stage-content site-measure-page home-closing scene-reveal">
                <h2 id="contact-heading" class="site-display-2">{{ $st['contactHeading'] }}</h2>
                <p class="site-lede">{{ $st['homeContactLead'] }}</p>
                <a href="/contact" class="site-action site-cta" data-emphasis="true">{{ $st['homeContactCta'] }}</a>
            </div>
        </section>
    </main>
@endsection
