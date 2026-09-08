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

            {{-- Nebula EN UZAK düzlem: kaydırmada en yavaş hareket eder. --}}
            <div class="site-stage-layer scene-plane" data-plane="far" aria-hidden="true">
                <span class="scene-nebula"></span>
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
                    <a href="/app" class="site-action home-action" data-emphasis="true">{{ $st['homeOpenApp'] }}</a>
                    <a href="/login" class="site-action home-action">{{ $st['navLogin'] }}</a>
                    <a href="/register" class="site-action home-action">{{ $st['navRegister'] }}</a>
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
            <div class="home-head scene-reveal">
                <h2 id="features-heading" class="site-display-2">{{ $st['homeFeaturesHeading'] }}</h2>
            </div>

            <div class="home-grid">
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
            <div class="site-stage-layer scene-plane" data-plane="far" aria-hidden="true">
                <span class="scene-nebula"></span>
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
        <div class="site-measure-form home-prose scene-reveal">
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
            <div class="site-stage-layer scene-plane" data-plane="far" aria-hidden="true">
                <span class="scene-nebula"></span>
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
                <a href="/contact" class="site-action home-action" data-emphasis="true">{{ $st['homeContactCta'] }}</a>
            </div>
        </section>
    </main>
@endsection
