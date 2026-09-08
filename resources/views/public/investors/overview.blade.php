@extends('public.layout')

{{-- YATIRIMCI İLİŞKİLERİ — DURUM SAYFASI (FF-251, `docs/149`).

     ── UYDURMA YOK, VE BU BİR KAPIDIR ────────────────────────────────────

     Sahibin isteği iki cümleydi: *"yatırımcı ilişkileri, pitch deck vb.
     bilgiler için sayfalar olmalı"* ve *"tabii ki uydurulmuş rakamla
     değil"*.

     Bu sayfada müşteri sayısı, gelir, büyüme oranı, pazar büyüklüğü,
     müşteri logosu, referans ve canlı sayaç YOKTUR — hiçbiri bu depoda
     ölçülemez. Kural bir niyet değil bir ölçüm: `INVESTOR-HONEST-01`
     sayfadaki HER rakamın `InvestorDossier`den geldiğini doğruluyor. Katalog
     metnine yazılmış tek bir sayı testi kırar.

     Sayfanın en güçlü bölümü "ne yapmıyoruz"tur ve bu bir üslup tercihi
     değil: var sandığı bir yeteneğin olmadığını servis sırasında öğrenen bir
     restoran, hiçbir şeyi değiştiremeyeceği saatte öğrenir.

     ── METİN KATALOGDAN GELİR ────────────────────────────────────────────

     Blade'e gömülen bir dize hiçbir PO dosyasında görünmez
     (`I18N-SSR-RATCHET-16`). Aşağıdaki her görünür sözcük `SiteText`,
     `InvestorDossier` ya da plan kataloğundan geliyor.

     ── SAHNE ─────────────────────────────────────────────────────────────

     Dağarcık `docs/146` §4'ten; yeni bir fikir uydurulmadı. Sayfa başına
     TEK tuval ve o tuval önsöz bandında (`SAHNE-B7`); geri kalan katmanların
     hepsi saf CSS. Fiyatın ve iletişimin okunduğu yerde sahne SUSAR. --}}
@section('title', $st['investorsMetaTitle'])
@section('description', $st['investorsMetaDescription'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['investorsHeading'],
            'prologueLead' => $st['investorsLead'],
            'prologueVariant' => 'orbit',
            'prologueCta' => ['href' => '/investors/deck', 'label' => $st['investorsDeckCta']],
        ])

        {{-- ══ NE YOK ════════════════════════════════════════════════════

             Bir yatırımcı sayfasının İLK bölümü normalde en büyük iddiadır.
             Burada en büyük iddia, iddia etmemektir — ve bunu en başa
             koymak bir karar: aşağıdaki hiçbir satırı okumadan önce
             okuyucunun neyi aramaması gerektiğini bilmesi gerekiyor. --}}
        <section id="ground-rules" aria-labelledby="ground-rules-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-content site-measure-page">
                <div class="site-section-head scene-reveal">
                    <h2 id="ground-rules-heading" class="site-display-2">{{ $st['investorsRulesHeading'] }}</h2>
                    <p class="site-section-lead">{{ $st['investorsRulesBody'] }}</p>
                    <p class="site-section-lead">{{ $st['investorsRulesBody2'] }}</p>
                </div>
            </div>
        </section>

        {{-- ══ ZİNCİR ════════════════════════════════════════════════════

             Ürünün kendi sırası, ürünün kendi envanterinden. Her adımın
             altında o adımı ÇALIŞTIRAN dosya yazıyor: bir iddia, arkasındaki
             dosya silindiğinde iddia olmaktan çıkar. --}}
        <section id="chain" aria-labelledby="chain-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer" aria-hidden="true">
                <span class="scene-grid"></span>
            </div>

            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x-" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-content site-measure-page">
                <div class="site-section-head scene-reveal">
                    <h2 id="chain-heading" class="site-display-2">{{ $st['investorsChainHeading'] }}</h2>
                    <p class="site-section-lead">{{ $st['investorsChainLead'] }}</p>
                </div>

                <ol class="site-ledger" role="list" aria-label="{{ $st['investorsChainLabel'] }}">
                    @foreach ($facts['chain'] as $index => $step)
                        <li class="site-panel site-lit site-ledger-card scene-reveal" style="--scene-order: {{ $index }}">
                            <span class="site-ledger-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="site-display-3">{{ $step['title'] }}</h3>
                            <p class="site-ledger-body">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ══ NE YAPILDI ════════════════════════════════════════════════

             Sayı katalogda DEĞİL: cümle `{parts}` yer tutucusu taşıyor ve
             denetleyici onu envanterin uzunluğuyla dolduruyor. Bir parça
             üründen düşerse cümle kendiliğinden değişir. --}}
        <section id="built" aria-labelledby="built-heading" class="site-stage site-field site-band">
            <div class="site-stage-layer scene-plane" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-conduit">
                    <span class="scene-conduit-line" style="--scene-conduit-run: 9s"></span>
                    <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 13s; --scene-conduit-delay: 1.1s"></span>
                    <span class="scene-conduit-line" style="--scene-conduit-run: 7s; --scene-conduit-delay: 2.3s"></span>
                </span>
            </div>

            <div class="site-measure-page">
                <div class="site-section-head scene-reveal scene-plane" data-plane="near" data-axis="x-">
                    <h2 id="built-heading" class="site-display-2">{{ $st['investorsBuiltHeading'] }}</h2>
                    <p class="site-section-lead">{{ $measured['built'] }}</p>
                </div>

                <ul class="site-ledger scene-plane" role="list" data-plane="near" data-axis="x">
                    @foreach ($facts['parts'] as $index => $part)
                        <li class="scene-tilt scene-reveal" style="--scene-order: {{ $index }}">
                            <div class="site-panel site-lit scene-tilt-face site-ledger-card">
                                <h3 class="site-display-3">{{ $part['title'] }}</h3>
                                <p class="site-ledger-body">{{ $part['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <p class="site-page-body scene-reveal">
                    <a href="/investors/product" class="site-action site-cta">{{ $st['investorsBuiltCta'] }}</a>
                </p>
            </div>
        </section>

        {{-- ══ NE YAPILMADI ══════════════════════════════════════════════

             SAHNE BURADA SÖNÜK: bir sınırın okunduğu yer, dikkatin
             bölünmemesi gereken yerdir. Bant derin kalıyor (kompozisyon
             kopmasın) ama ne yörünge ne veri hattı taşıyor. --}}
        <section id="limits" aria-labelledby="limits-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-content site-measure-page">
                <div class="site-section-head scene-reveal">
                    <h2 id="limits-heading" class="site-display-2">{{ $st['investorsLimitsHeading'] }}</h2>
                    <p class="site-section-lead">{{ $measured['limits'] }}</p>
                </div>

                <ul class="site-ledger" role="list">
                    @foreach ($facts['limits'] as $index => $limit)
                        <li class="site-panel site-ledger-card scene-reveal" style="--scene-order: {{ $index }}">
                            <h3 class="site-display-3">{{ $limit['title'] }}</h3>
                            <p class="site-ledger-body">{{ $limit['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ══ TAAHHÜT ═══════════════════════════════════════════════════

             Rakamsız bir SLA'yı gizlemek yerine, boş bırakılmış anahtarları
             ADIYLA saymak. `config/sla.php`nin kendi gerekçesi bu: ölçülmeyen
             bir oranı taahhüt etmek, tutulup tutulmadığı bilinemeyecek bir
             söz vermektir. Sayfa o kararı tekrar etmez, GÖSTERİR. --}}
        <section id="commitment" aria-labelledby="commitment-heading" class="site-measure-page site-page-body">
            <div class="site-section-head scene-reveal">
                <h2 id="commitment-heading" class="site-display-2">{{ $st['investorsCommitmentHeading'] }}</h2>
            </div>

            <div class="site-panel site-ledger-card scene-reveal">
                @if ($facts['commitment']['committed'])
                    <p class="site-ledger-body">{{ $st['investorsCommitmentSet'] }}</p>
                @else
                    <p class="site-ledger-body">{{ $st['investorsCommitmentOpen'] }}</p>
                    <p class="site-ledger-note">
                        {{ $st['investorsCommitmentMissingLabel'] }}
                        {{ implode(', ', $facts['commitment']['missing']) }}
                    </p>
                @endif
            </div>

            <p>
                <a href="/sla" class="site-action site-cta">{{ $st['investorsCommitmentCta'] }}</a>
            </p>
        </section>

        {{-- ══ NEREDE KOŞUYOR ════════════════════════════════════════════

             Liste ELLE YAZILMAZ, ölçülür (`MeasuredSubprocessors`): barındırma
             yapılandırmadan, sağlayıcılar kimlik kasasından, ölçüm araçları
             analitik ayarlarından. Kasa okunamazsa sayfa boş liste göstermez,
             "bakamadık" der — ikisi aynı cümle değildir. --}}
        <section id="infrastructure" aria-labelledby="infrastructure-heading" class="site-measure-page site-page-body">
            <div class="site-section-head scene-reveal">
                <h2 id="infrastructure-heading" class="site-display-2">{{ $st['investorsInfrastructureHeading'] }}</h2>
                <p class="site-section-lead">{{ $measured['subprocessors'] }}</p>
            </div>

            @if ($facts['vaultUnreadable'])
                <p role="status" class="site-notice">{{ $st['investorsInfrastructureUnreadable'] }}</p>
            @endif

            <ul class="site-ledger" role="list">
                @foreach ($facts['subprocessors'] as $index => $row)
                    <li class="site-panel site-ledger-card scene-reveal" style="--scene-order: {{ $index }}">
                        <h3 class="site-display-3">{{ $row['name'] }}</h3>
                        <div class="site-facts">
                            <p class="site-facts-term">{{ $st['investorsInfrastructureRoleLabel'] }}</p>
                            <p class="site-facts-value">{{ $row['role'] }}</p>
                            <p class="site-facts-term">{{ $st['investorsInfrastructureDataLabel'] }}</p>
                            <p class="site-facts-value">{{ $row['data'] }}</p>
                            <p class="site-facts-term">{{ $st['investorsInfrastructureLocationLabel'] }}</p>
                            <p class="site-facts-value">{{ $row['location'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ══ FİYAT ═════════════════════════════════════════════════════

             Rakam plan kataloğundan gelir, sayfaya elle yazılmaz. Sahne
             burada susar: bir fiyatın okunduğu yer, dikkatin bölünmemesi
             gereken yerdir (`docs/146` §5). --}}
        <div class="site-measure-page site-page-body scene-reveal">
            @include('public.partials.pricing')
        </div>

        {{-- ══ İLETİŞİM — kapanış sahnesi ════════════════════════════════ --}}
        <section id="contact" aria-labelledby="investors-contact-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x-" aria-hidden="true">
                <span class="scene-orbit">
                    <span class="scene-orbit-ring" style="--scene-orbit-scale: 1; --scene-orbit-spin: 68s"></span>
                    <span class="scene-orbit-ring" data-spin="reverse" style="--scene-orbit-scale: 0.55; --scene-orbit-spin: 44s"></span>
                </span>
            </div>

            <div class="site-stage-layer" data-depth="front" aria-hidden="true">
                <span class="scene-vignette"></span>
            </div>

            <div class="site-stage-content site-measure-page site-page-body scene-reveal">
                <h2 id="investors-contact-heading" class="site-display-2">{{ $st['investorsContactHeading'] }}</h2>
                <p class="site-lede">{{ $st['investorsContactLead'] }}</p>
                <p>
                    <a href="/investors/contact" class="site-action site-cta" data-emphasis="true">{{ $st['investorsContactCta'] }}</a>
                </p>
            </div>
        </section>
    </main>
@endsection
