@extends('public.layout')

{{-- ANA SAYFA — `docs/138`.

     ── METİN KATALOGDAN GELİR ────────────────────────────────────────────

     Blade'e gömülen bir dize hiçbir PO dosyasında görünmez; sahibi onu açıp
     çeviremez (`I18N-SSR-RATCHET-16`, borç sıfır ve MUTLAK YASAK). Bu
     sayfadaki her sözcük `resources/js/i18n/site.ts` içinde bir anahtara
     sahiptir.

     ── İDDİA UYDURULMAZ ──────────────────────────────────────────────────

     Zincir, parçalar ve sınırlar `App\Support\Site\HomeStory` üzerinden
     gelir ve başlıkları ürünün kendi genel bakış sayfasının
     (`ProductOverviewPage`) terimleriyle BİREBİR aynıdır. Bir yetenek
     üründen düşerse ya da adı değişirse `HOME-REAL-07` kırılır — sayfa
     eski iddiayı sessizce taşımaya devam edemez.

     Sayfada YOK ve bilerek yok: müşteri logosu, referans, "1000+ restoran",
     ödül, canlı sayaç, uydurma grafik, "yakında" vaadi. Yatırımcıya
     hazırlık uydurmayla değil, YAPILMIŞ İŞİ göstererek kurulur — bu yüzden
     sayfanın en uzun iki bölümü ürünün on iki parçası ve yedi sınırıdır.

     ── DAR EKRAN TABAN, YAZIM SIRASI DA (`docs/118` E1) ──────────────────

     Bu dosyada tek bir kırılma noktası jetonu (`sm:`, `md:`…) ve tek bir
     `max-*` bastırması yoktur; "mobilde gizle" de yoktur. Kapılar:
     `HOME-FLUID-04` ve `HOME-SCENE-01`.

     Taban 320×480 — iPhone 4. İlk ekranda başlık, vaat ve iki eylem durur;
     sahne onların ALTINDA yaşar, üstünde değil. Ekranı dolduran bir
     "hero" yok: `100vh` yüksekliğinde bir sahne 480 pikselde içeriği
     katlanmanın altına iterdi.

     ── DEKOR GEZİNMEYİ ÖRTMEZ ────────────────────────────────────────────

     Her sahne katmanı `aria-hidden` ve `pointer-events: none`
     (`site-shell.css`), katman sırası `--layer-scene-*` (0-2) — yani
     içeriğin (10), menünün (20), çerez şeridinin (30) ve atlama
     bağlantısının (50) hep ALTINDA. Bu bir tercih değil kural: hiçbir
     süsleme bir bağlantıyı ya da hukuki bir seçimi örtemez. --}}

@section('title', $st['homeMetaTitle'])
@section('description', $st['homeMetaDescription'])

@section('content')
    <main id="main-content">
        {{-- ══ 1. SAHNE ═══════════════════════════════════════════════════

             SAYFANIN TEK BASKIN HAREKETLİ ARKA PLANI (`docs/118` E5 md. 6).
             Aşağıdaki bölümlerde ikinci bir sahne YOK: hareketin etkisi
             nadirliğinden gelir ve her bölümde kıpırdayan bir arka plan,
             üçüncü bölümde gürültüdür.

             Dört katman, dört farklı parallax mesafesi. Sıra derinliktir:
             en uzaktaki parçacık alanı en az kıpırdar (`near`), ufuk
             ızgarası ortada (`mid`), halka en çok (`far`) — yakın olan
             hızlı geçer, uzak olan yavaş. Bu, gözün gerçek dünyada
             kullandığı tek derinlik ipucudur ve bir görsel indirmeden
             verilir. --}}
        {{-- `data-theme="dark"` SAHNENİN KENDİSİNDE: kök öğedeki temayla
             aynı öznitelik, ama burada bir ADACIK kurar. AEP jetonları
             element düzeyinde `[data-theme="dark"]` ile koyu değerlerine
             döner; sahne böylece açık temada da koyu kalır ve içindeki her
             şey — metin, kenar, daisyUI düğmesi — temanın KENDİ koyu
             değerlerini kullanır. Elle seçilmiş tek bir renk yok. --}}
        <section class="site-stage site-scene" data-theme="dark" aria-labelledby="hero-heading">
            <div class="site-stage-layer site-field site-drift" data-shift="near" aria-hidden="true"></div>
            <div class="site-stage-layer site-horizon site-drift" data-depth="mid" data-shift="mid" aria-hidden="true"></div>
            {{-- SAĞLI SOLLU: ışıma ve halka ZIT yöne akar (`data-away`).
                 Aynı yöne akan iki katman tek bir düzlem gibi okunur;
                 ayrışmayı yaratan şey yön farkıdır. --}}
            <div class="site-stage-layer site-halo site-sweep" data-away="true" data-depth="mid" data-shift="mid" aria-hidden="true"></div>
            <div class="site-stage-layer site-orb site-sweep" data-depth="front" data-shift="far" aria-hidden="true"></div>

            <div class="site-stage-content site-shell-inner site-hero">
                <h1 id="hero-heading" class="site-hero-heading">{{ $st['homeHeroHeading'] }}</h1>
                <p class="site-scene-lead">{{ $st['homeHeroLead'] }}</p>

                {{-- İKİ EYLEM, ÜÇ DEĞİL. Üçüncüsü (`/login`) kabuğun
                     menüsünde zaten duruyor; 320×480'de üç düğme üst üste
                     132 piksel eder ve vaat cümlesini katlanmanın altına
                     iterdi. Sıra da düşünülmüştür: ilk düğme HENÜZ HESABI
                     OLMAYAN için, ikincisi geri dönen için. --}}
                <nav aria-label="{{ $st['homeHeroActionsLabel'] }}" class="site-hero-actions">
                    <a href="/register" class="dz-btn dz-btn-primary site-action">{{ $st['homeHeroRegister'] }}</a>
                    <a href="/app" class="dz-btn site-action">{{ $st['homeOpenApp'] }}</a>
                </nav>

                {{-- Ücretsiz zincir bir kampanya değil, plan kataloğundaki
                     ölçülmüş bir olgu (`PlanCatalogueSeeder`). --}}
                <p class="site-hero-note">{{ $st['homeHeroNote'] }}</p>
            </div>
        </section>

        {{-- ══ 2. ZİNCİR ══════════════════════════════════════════════════

             YATAY ŞERİT — dokunmanın ucuz hareketi. Altı adımı 320 pikselde
             dikey dizmek, ilk ekranı zincirin BİR adımına ayırmak demekti.
             Şerit kendi kaydırma kabıdır: sayfa yana kaymaz.

             Kaydırılabilirlik HOVER İLE anlatılmaz (dokunmada hover yoktur,
             `docs/118` E2): bir sonraki kartın kenarı görünür kalır ve
             altındaki ilerleme çizgisi kaç adım kaldığını söyler. İkisi de
             her giriş kipinde aynı biçimde çalışır. --}}
        <section id="how-it-works" class="site-block site-rail-region" aria-labelledby="how-it-works-heading">
            <div class="site-shell-inner">
                <h2 id="how-it-works-heading" class="text-2xl font-bold site-reveal">{{ $st['homeChainHeading'] }}</h2>
                <p class="mt-2 text-fg-secondary site-reveal">{{ $st['homeChainLead'] }}</p>
            </div>

            {{-- `tabindex="0"`: kendi kaydırma kabı olan bir bölge klavyeyle
                 de gezilebilmeli — ok tuşları, tarayıcının kendi davranışı,
                 betiksiz. `role="list"` ise `display: grid`in bazı ekran
                 okuyucularda liste anlamını düşürmesine karşı yazıldı. --}}
            <ol class="site-rail" role="list" tabindex="0" aria-label="{{ $st['homeChainLabel'] }}">
                @foreach ($story['chain'] as $index => $step)
                    <li class="site-panel">
                        {{-- Numara ekran okuyucuya `<ol>` üzerinden zaten
                             ulaşır; rozet onu İKİNCİ kez söylemez. --}}
                        <span class="site-step-badge" aria-hidden="true">{{ $index + 1 }}</span>
                        <h3 class="font-semibold">{{ $step['title'] }}</h3>
                        <p class="text-fg-secondary">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="site-thread" aria-hidden="true"><span class="site-thread-fill"></span></div>
        </section>

        {{-- ══ 3. PARÇALAR ════════════════════════════════════════════════

             `id="features"` KORUNDU: dış bağlantılar ve kabuğun gezintisi
             bu çıpaya işaret ediyor (`SiteNavigation`), adı değiştirmek
             çalışan bağlantıları kırardı.

             On iki başlık `ProductOverviewPage`in terimleriyle birebir
             aynı; ölçen kapı HOME-REAL-07. --}}
        <section id="features" class="site-block site-shell-inner" aria-labelledby="features-heading">
            <h2 id="features-heading" class="text-2xl font-bold site-reveal">{{ $st['homePartsHeading'] }}</h2>
            <p class="text-fg-secondary site-reveal">{{ $st['homePartsLead'] }}</p>

            <ul class="site-cards" role="list">
                @foreach ($story['parts'] as $part)
                    <li class="site-panel site-reveal">
                        <h3 class="font-semibold">{{ $part['title'] }}</h3>
                        <p class="text-fg-secondary">{{ $part['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ══ 4. NE DEĞİL ════════════════════════════════════════════════

             Bir tanıtım sayfasının en zor bölümü ve bu ürünün en dürüst
             kanıtı. Gerekçesi `/urun/` sayfasında yazılı: yanlış varsayımın
             bedeli SERVİS SIRASINDA ödenir — var sandığı bir özelliğin
             olmadığını, hiçbir şeyi değiştiremeyeceği saatte öğrenir.

             Yedi başlık `ProductOverviewPage`in "What Zabuno is not"
             bloğuyla birebir aynı. --}}
        <section id="limits" class="site-block site-shell-inner" aria-labelledby="limits-heading">
            <h2 id="limits-heading" class="text-2xl font-bold site-reveal">{{ $st['homeLimitsHeading'] }}</h2>
            <p class="text-fg-secondary site-reveal">{{ $st['homeLimitsLead'] }}</p>

            <ul class="site-cards" role="list">
                @foreach ($story['limits'] as $limit)
                    <li class="site-panel site-reveal">
                        <h3 class="font-semibold">{{ $limit['title'] }}</h3>
                        <p class="text-fg-secondary">{{ $limit['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ══ 5. FİYAT ═══════════════════════════════════════════════════
             Rakam plan kataloğundan okunur, sayfaya elle yazılmaz. --}}
        <div class="site-block site-shell-inner">
            @include('public.partials.pricing')
        </div>

        {{-- ══ 6. SSS ═════════════════════════════════════════════════════

             `<details>` tabandır: tarayıcının kendi açılır kapanır düğmesi,
             klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betiksiz
             açılır. Cevap metni KAPALIYKEN DE HTML'dedir — arama motoru ve
             betik çalıştırmayan bot onu görür.

             480 piksellik bir ekranda beş açık soru-cevap 600 piksel eder;
             kapalı başlamak, okuyucuya hangi soruyu açacağını SEÇTİRİR. --}}
        <section id="faq" class="site-block site-shell-inner" aria-labelledby="faq-heading">
            <h2 id="faq-heading" class="text-2xl font-bold site-reveal">{{ $st['homeFaqHeading'] }}</h2>

            <div class="flex flex-col gap-2">
                @foreach ([
                    ['q' => $st['homeFaqWhatQuestion'], 'a' => $st['homeFaqWhatAnswer']],
                    ['q' => $st['homeFaqAccountQuestion'], 'a' => $st['homeFaqAccountAnswer']],
                    ['q' => $st['homeFaqInstallQuestion'], 'a' => $st['homeFaqInstallAnswer']],
                    ['q' => $st['homeFaqPosQuestion'], 'a' => $st['homeFaqPosAnswer']],
                    ['q' => $st['faqCostQuestion'], 'a' => $st['faqCostAnswer']],
                ] as $entry)
                    <details class="site-faq site-reveal">
                        <summary class="site-faq-question">{{ $entry['q'] }}</summary>
                        <p class="site-faq-answer">{{ $entry['a'] }}</p>
                    </details>
                @endforeach
            </div>

            {{-- Fiyat sorusunun cevabı bir SAYFADIR; bağlantı cümlenin
                 içinde değil, kendi satırında ve 44 piksel (`docs/117` K1).
                 Cümle içine gömülmüş bir bağlantı dar ekranda 18 piksel
                 yüksekliğinde kalıyor ve parmakla ıskalanıyor. --}}
            <a href="/pricing" class="site-action underline underline-offset-2">{{ $st['pricingHeading'] }}</a>
        </section>

        {{-- ══ 7. İLETİŞİM ════════════════════════════════════════════════ --}}
        <section id="contact" class="site-block site-shell-inner" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-2xl font-bold site-reveal">{{ $st['contactHeading'] }}</h2>
            <p class="text-fg-secondary site-reveal">{{ $st['homeContactLead'] }}</p>
            <a href="/contact" class="site-action underline underline-offset-2">{{ $st['homeContactCta'] }}</a>
        </section>
    </main>

    {{-- ══ HAREKET KANCASI ═══════════════════════════════════════════════

         KABUKTA DEĞİL, SAYFADA. Betik `layout.blade.php`e eklenseydi her
         kurumsal adres onu indirirdi — yasal sayfalarda ve yardım
         makalelerinde tek bir sahne yok. Burada durduğu için yalnız sahne
         taşıyan sayfa öder.

         SAYFANIN SONUNDA: `type="module"` zaten ertelenir (`defer`
         semantiği), yani ayrıştırmayı hiç engellemez. Gövdenin sonunda
         olması, betik hiç inmese bile sayfanın çoktan okunur olduğunu
         yazıya döker.

         CSP: `@vite` etiketi `Vite::useCspNonce()` ile üretilen nonce'u
         taşır (`SecurityHeaders`); CDN yok, uzak kaynak yok — politika
         zaten `default-src 'self'` (`docs/136` §2).

         BU BETİK SAYFAYI ÇİZMEZ. Yukarıdaki her satır sunucudan gelir ve
         betik engellenmişse sayfa eksiksizdir; kapı `HOME-SCENE-05`. --}}
    @vite(['resources/js/site-motion.ts'])
@endsection
