@extends('public.layout')

{{-- YAPILMIŞ İŞİN ENVANTERİ (FF-251, `docs/149`).

     Bu sayfa bir pazarlama sayfası DEĞİL, bir kütüktür: ürünün kendi genel
     bakış sayfasının (`ProductOverviewPage`) envanteri, her satırın
     arkasındaki DOSYAYLA birlikte.

     ── KANIT NEDİR ───────────────────────────────────────────────────────

     "Şu yetenek var" cümlesinin kanıtı bir ekran görüntüsü ya da bir
     paragraf değil, o yeteneği ÜRETEN dosyadır. Envanterdeki `source` alanı
     bir yorum değil bir adres ve `InvestorDossier` onu her çizimde
     `file_exists` ile arıyor: dosya gitmişse satır "bu dağıtımda yok" der ve
     iddia kanıtsız kaldığını KENDİSİ söyler.

     ── SINIRLARIN KAYNAĞI YOKTUR VE OLMAMALI ─────────────────────────────

     Yedi sınırın hiçbiri bir dosyaya işaret etmez, çünkü bir YOKLUĞUN
     işaret edecek dosyası yoktur. Sayfa bunu boş bırakarak değil, yazarak
     söyler.

     Sahne dağarcığı `docs/146` §4'ten; tuval yalnız önsöz bandında
     (`SAHNE-B7`: sayfa başına bir). --}}
@section('title', $st['investorsProductMetaTitle'])
@section('description', $st['investorsProductMetaDescription'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['investorsProductHeading'],
            'prologueLead' => $st['investorsProductLead'],
            'prologueVariant' => 'grid',
        ])

        {{-- ══ ZİNCİR — ürünün kendi sırası ══════════════════════════════ --}}
        <section id="chain" aria-labelledby="chain-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x-" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-depth="mid" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
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
                            @include('public.investors.partials.evidence', ['row' => $step])
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ══ PARÇALAR ══════════════════════════════════════════════════ --}}
        <section id="built" aria-labelledby="built-heading" class="site-stage site-field site-band">
            <div class="site-stage-layer scene-plane" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-conduit">
                    <span class="scene-conduit-line" style="--scene-conduit-run: 9s"></span>
                    <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 13s; --scene-conduit-delay: 1.1s"></span>
                    <span class="scene-conduit-line" style="--scene-conduit-run: 7s; --scene-conduit-delay: 2.3s"></span>
                    <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 16s; --scene-conduit-delay: 0.4s"></span>
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
                                @include('public.investors.partials.evidence', ['row' => $part])
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ══ SINIRLAR ══════════════════════════════════════════════════ --}}
        <section id="limits" aria-labelledby="limits-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="xy" aria-hidden="true">
                <span class="scene-nebula"></span>
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
                            @include('public.investors.partials.evidence', ['row' => $limit])
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ══ İDDİA NASIL DENETLENİYOR ══════════════════════════════════

             İki sayı, ikisi de ölçülmüş: kaç iddianın dosyası bu dağıtımda
             DURUYOR ve kaç kapı betiği burada. Test sayısı bilerek YOK —
             `tests/` üretim imajına girmiyor ve orada sayılan bir sayı canlı
             sitede sıfır olurdu (`InvestorDossier` sınıf başlığı). --}}
        <section id="verification" aria-labelledby="verification-heading" class="site-measure-page site-page-body">
            <div class="site-section-head scene-reveal">
                <h2 id="verification-heading" class="site-display-2">{{ $st['investorsVerificationHeading'] }}</h2>
                <p class="site-section-lead">{{ $measured['verification'] }}</p>
                <p class="site-section-lead">{{ $measured['gates'] }}</p>
            </div>

            <ul class="site-ledger" role="list">
                @foreach ($facts['gates'] as $index => $gate)
                    <li class="site-panel site-ledger-card scene-reveal" style="--scene-order: {{ $index }}">
                        <p class="site-facts-value">{{ $gate['path'] }}</p>
                        <p class="site-ledger-note">
                            {{ $gate['present'] ? $st['investorsVerificationPresent'] : $st['investorsVerificationMissing'] }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    </main>
@endsection
