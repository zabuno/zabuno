@extends('public.layout')

{{-- METİN KATALOGDAN gelir, Blade'e gömülmez (`docs/100` Faz 2).
     Bir dize şablonda kaldığı sürece sahibi onu hiçbir PO dosyasında
     bulamaz ve çeviremez; borç `lang/untranslatable-debt.json` içinde
     SIFIRDIR ve bu paket onu artırmadı: aşağıdaki her görünür sözcük
     `SiteText` üzerinden geliyor, bir tanesi bile yeni değil.

     SAHNE (`docs/146`). Sahibin emri (2026-09-08): *"Bir uzay teknolojileri
     şirketi gibi, abartı dursun, görünsün, hissettirsin."*

     Bu sayfa o emrin karşılığıdır ve şunu DEĞİŞTİRMEZ: içerik gerçektir.
     Sahte müşteri, sahte rakam, sahte logo, sahte canlı gösterge yok
     (`HOME-HONEST-03`). Gösteri, SUNUMDA kuruldu — sözde değil. --}}
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
                 durmaz — geriye nebulanın kendisi kalır. --}}
            <div class="site-stage-layer">
                <canvas class="scene-canvas" data-scene="field" data-scene-sway="0.18" data-scene-speed="0.24" aria-hidden="true"></canvas>
            </div>

            {{-- Nebula EN UZAK düzlem: kaydırmada en yavaş hareket eder. Döngü
                 2'de eksen `xy` oldu — yani hem aşağı hem YANA süzülüyor.
                 Sahibin "sağlı sollu hareket eden landing page" isteği
                 (`docs/146` §9 madde 5) burada bir şeritte değil, kahramanın
                 kendi katmanında. --}}
            <div class="site-stage-layer scene-plane" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            {{-- YÖRÜNGE (Döngü 2). Üç halka, üç hız, ortadaki ters yönde.
                 Yıldız alanı UZAKLIĞI anlatır; yörünge İŞ yapıldığını anlatır
                 ve bir uzay şirketinin dağarcığında ikincisi birincisinden
                 önce gelir. Düzlem TERS eksende akıyor: iki katman aynı yöne
                 kayarsa göz tek bir blok görür, zıt yönde kaydıklarında
                 aralarında derinlik doğar.

                 Saf CSS — ikinci bir WebGL bağlamı AÇILMIYOR (`docs/146` §9
                 madde 4 hâlâ ölçülmedi ve ölçülmemiş bir maliyet ürüne
                 sokulmaz). --}}
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
                <div class="home-hero-text scene-reveal">
                    <h1 class="site-display">{{ $st['homeHeroHeading'] }}</h1>
                    <p class="site-lede">{{ $st['homeHeroLead'] }}</p>
                </div>

                <nav aria-label="{{ $st['homeHeroActionsLabel'] }}" class="home-actions scene-reveal" style="--scene-order: 1">
                    <a href="/app" class="site-action site-cta" data-emphasis="true">{{ $st['homeOpenApp'] }}</a>
                    <a href="/login" class="site-action site-cta">{{ $st['navLogin'] }}</a>
                    <a href="/register" class="site-action site-cta">{{ $st['navRegister'] }}</a>
                </nav>
            </div>
        </section>

        {{-- ══ AKAN BANTLAR ══════════════════════════════════════════════

             İki şerit, iki yön: biri satır başına, öteki satır sonuna akar.
             Yön MANTIKSALDIR — sağdan sola yazılan bir dilde ikisi de
             kendiliğinden yer değiştirir (`site-scene.css` §4).

             İÇERİK GERÇEKTİR: her madde, aşağıdaki bölümlerde de yazan bir
             ürün yeteneği ya da bir adımdır. `aria-hidden`, çünkü aynı
             sözcükler birkaç santim aşağıda okunabilir bir bölümde zaten
             duruyor; ekran okuyucuya kopyayı ikinci kez okutmak gürültü
             olurdu.

             Şerit BLADE'de iki kez basılıyor, betikte değil: döngünün
             dikişsiz olması için ikinci kopya gerekiyor ve betiği
             engellenmiş bir ziyaretçide de bant tam görünmeli. --}}
        @php
            /* Bant maddeleri: KATALOG anahtarlarından, elle yazılmış tek bir
               sözcük yok. Liste iki kez basılacağı için burada bir kez
               kuruluyor — şablonda iki ayrı liste olsaydı, biri güncellenip
               öteki unutulurdu. */
            $driftCapabilities = [
                $st['homeFeatureWorkspaceTitle'],
                $st['homeFeatureMenuTitle'],
                $st['homeFeaturePublicationTitle'],
                $st['homeFeatureMediaTitle'],
            ];

            $driftSteps = [
                $st['homeStepSetupTitle'],
                $st['homeStepBuildTitle'],
                $st['homeStepPublishTitle'],
                $st['homeStepUpdateTitle'],
            ];

            /* ÜÇÜNCÜ ŞERİT (Döngü 2). İki şerit bir ZITLIK kurar ama bir
               DERİNLİK kurmaz: göz iki hızı karşılaştırır ve orada durur. Üçüncü
               şerit en yavaş ve en sönük olanıdır; onunla birlikte hızlar bir
               sıraya dizilir ve şeritler bir yüzey değil, bir HACİM okunur.

               İçerik yine gerçektir ve yine katalogdan: iki listenin birleşimi.
               Yeni bir dize yazılmadı (`HOME-HONEST-03`, borç hâlâ sıfır). */
            $driftDepth = array_merge($driftCapabilities, $driftSteps);
        @endphp

        <div class="site-stage home-drift" aria-hidden="true">
            <div class="site-bleed scene-drift" data-direction="start">
                <div class="scene-drift-track">
                    @foreach ([1, 2] as $pass)
                        @foreach ($driftCapabilities as $label)
                            <span class="scene-drift-item">{{ $label }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="site-bleed scene-drift" data-direction="end">
                <div class="scene-drift-track">
                    @foreach ([1, 2] as $pass)
                        @foreach ($driftSteps as $label)
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

        {{-- ══ YETENEKLER ════════════════════════════════════════════════

             Kartlar `3D görünen 2D`: kalınlık `site-panel`den (parıltı +
             kenar + renkli gölge), eğilme `scene-tilt`ten geliyor. Eğilme
             YALNIZ işaretleyicili cihazda doğar — dokunmada `hover` yoktur
             ve parmak kartın üstünü kapatır (`TOUCH-FIRST-INTERFACE` §2). --}}
        <section id="features" aria-labelledby="features-heading" class="site-stage site-field">
            {{-- `.site-stage` BURADA ZORUNLU, bir süs değil: `.site-field`in
                 ışık katmanı kabın %30 dışına taşar (`site-identity.css` §6)
                 ve kırpılmazsa sayfayı YATAY olarak kaydırır — 320 pikselde
                 ölçülen ilk kusur. Kırpma `.site-stage`in işi. --}}
            {{-- VERİ HATTI (Döngü 2), yeteneklerin ARKASINDA. Yıldız ve yörünge
                 MEKÂN anlatır; veri hattı İŞLEM anlatır — ve bu bölüm tam olarak
                 ürünün ne YAPTIĞINI sayıyor. Çizgi durur, ışık akar; her hat
                 farklı hızda, çünkü eşit hızda giden paketler bir ışık çubuğu
                 gibi okunur, bir ağ gibi değil. --}}
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
                 koyuyordu. Katalogda o anahtarın karşılığı da "Features" —
                 yani ekranda aynı sözcük iki kez, alt alta yazıyordu
                 (2026-09-08, 1280×800 ekran görüntüsü). Bir göz izi, başlığı
                 SINIFLANDIRIR; onu tekrar etmez. Katalogda bugün ayrı bir
                 sınıflandırma dizesi yok ve UYDURULMAZ — yeni bir dize
                 Blade'e yazmak, sahibinin hiçbir PO dosyasında bulamayacağı
                 bir metin yaratırdı (`I18N-SSR-RATCHET-16`). --}}
            {{-- BAŞLIK VE IZGARA ZIT YÖNLERDE SÜZÜLÜR (Döngü 2).

                 Mesafe `--motion-drift-near` (320'de 10 piksel, geniş ekranda
                 28): okunan bir metnin altındaki yatay hareket BÜYÜK olamaz —
                 satırı kovalayan bir göz, okuduğunu kaybeder. Zıtlık burada
                 hızdan değil YÖNDEN geliyor ve bu ölçüde bile ikisi arasında
                 bir düzlem farkı doğuruyor. --}}
            <div class="home-head scene-reveal scene-plane" data-plane="near" data-axis="x-">
                <h2 id="features-heading" class="site-display-2">{{ $st['homeFeaturesHeading'] }}</h2>
            </div>

            <div class="home-grid scene-plane" data-plane="near" data-axis="x">
                @foreach ([
                    ['title' => $st['homeFeatureWorkspaceTitle'], 'body' => $st['homeFeatureWorkspaceBody']],
                    ['title' => $st['homeFeatureMenuTitle'], 'body' => $st['homeFeatureMenuBody']],
                    ['title' => $st['homeFeaturePublicationTitle'], 'body' => $st['homeFeaturePublicationBody']],
                    ['title' => $st['homeFeatureMediaTitle'], 'body' => $st['homeFeatureMediaBody']],
                ] as $index => $feature)
                    <div class="scene-tilt scene-reveal" style="--scene-order: {{ $index }}">
                        <div class="site-panel site-lit scene-tilt-face home-card">
                            <span class="home-card-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="site-display-3">{{ $feature['title'] }}</h3>
                            <p class="home-card-body">{{ $feature['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            </div>
        </section>

        {{-- ══ NASIL ÇALIŞIR ═════════════════════════════════════════════

             İkinci derin bant. Burada TUVAL YOK ve bu ölçülmüş bir karar:
             aynı sayfada ikinci bir WebGL bağlamı açmak, düşük güçlü bir
             telefonda kare süresini iki katına çıkarır ve kazandırdığı şey
             birincisinin zaten verdiği derinliktir. Bandın hacmi saf
             CSS'ten geliyor — nebula, ufuk ve parallax düzlemleri
             (`docs/146` §4). --}}
        <section id="how-it-works" aria-labelledby="how-it-works-heading" class="site-stage site-deep" data-scene-progress>
            {{-- Nebula katmanı artık ŞEKİL DEĞİŞTİRİYOR (`scene-morph`): bant
                 kaydırıldıkça ışık halesinin sınırı dar bir kubbeden geniş bir
                 yaya açılıyor. Döngü 1'de geçiş yalnız IŞIKLA anlatılıyordu —
                 aynı `--scene-progress`, üçüncü bir yüz (`docs/146` §9 madde
                 5: "bölümler arası gerçek morph geçişi"). --}}
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x-" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            {{-- TEL KAFES ZEMİN (Döngü 2). Yıldız alanı derinlik verir ama
                 ZEMİN vermez; sahnenin "nerede" olduğu sorusunun cevabı yoktu.
                 Ufka doğru daralan çizgiler, bakanın bir yüzeyin üstünde
                 durduğunu söyler. Saf CSS: iki `repeating-linear-gradient` ve
                 tek bir `rotateX`. --}}
            <div class="site-stage-layer" aria-hidden="true">
                <span class="scene-grid"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-content site-measure-page home-band">
                <div class="home-head scene-reveal">
                    <h2 id="how-it-works-heading" class="site-display-2">{{ $st['homeHowItWorksHeading'] }}</h2>
                </div>

                <ol class="home-steps">
                    @foreach ([
                        ['title' => $st['homeStepSetupTitle'], 'body' => $st['homeStepSetupBody']],
                        ['title' => $st['homeStepBuildTitle'], 'body' => $st['homeStepBuildBody']],
                        ['title' => $st['homeStepPublishTitle'], 'body' => $st['homeStepPublishBody']],
                        ['title' => $st['homeStepUpdateTitle'], 'body' => $st['homeStepUpdateBody']],
                    ] as $index => $step)
                        <li class="home-step scene-reveal" style="--scene-order: {{ $index }}">
                            <span class="home-step-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="site-display-3">{{ $step['title'] }}</h3>
                            <p class="home-card-body">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ══ FİYAT ═════════════════════════════════════════════════════

             Rakam plan kataloğundan gelir, sayfaya elle yazılmaz
             (`docs/88`). Sahne burada susar: bir fiyatın okunduğu yer,
             dikkatin bölünmemesi gereken yerdir. --}}
        {{-- ÖLÇÜ `form` DEĞİL `page` (Döngü 2).

             Fiyat bölümü kurumsal yüzey diline geçince dört plan bir IZGARA
             oldu (`site-pages.css` §3); okuma genişliğindeki bir kapta ızgara
             iki sütuna sıkışıyor ve kartlar arasında karşılaştırma yapılamıyordu
             (2026-09-08, 1280×800 ekran görüntüsü). Bir fiyat tablosu okunan
             bir paragraf değil, KARŞILAŞTIRILAN bir tablodur. --}}
        <div class="site-measure-page home-prose scene-reveal">
            @include('public.partials.pricing')
        </div>

        {{-- ══ SSS ═══════════════════════════════════════════════════════ --}}
        <section id="faq" aria-labelledby="faq-heading" class="site-measure-page home-prose">
            <div class="home-head scene-reveal">
                <h2 id="faq-heading" class="site-display-2">{{ $st['homeFaqHeading'] }}</h2>
            </div>

            <dl class="home-faq">
                <div class="site-panel scene-reveal">
                    <dt>{{ $st['homeFaqWhatQuestion'] }}</dt>
                    <dd>{{ $st['homeFaqWhatAnswer'] }}</dd>
                </div>
                <div class="site-panel scene-reveal" style="--scene-order: 1">
                    <dt>{{ $st['homeFaqAccountQuestion'] }}</dt>
                    <dd>{{ $st['homeFaqAccountAnswer'] }}</dd>
                </div>
                <div class="site-panel scene-reveal" style="--scene-order: 2">
                    <dt>{{ $st['faqCostQuestion'] }}</dt>
                    <dd>
                        <a class="underline underline-offset-2" href="/pricing">{{ $st['pricingHeading'] }}</a>
                        — {{ $st['faqCostAnswer'] }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- ══ İLETİŞİM — kapanış sahnesi ════════════════════════════════ --}}
        <section id="contact" aria-labelledby="contact-heading" class="site-stage site-deep" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x" aria-hidden="true">
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
