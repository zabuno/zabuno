@extends('public.layout')

{{-- KISA SÜRÜM — "pitch deck", SAYFA olarak (FF-251, `docs/149`).

     ── NEDEN İNDİRİLEN BİR DOSYA YOK ─────────────────────────────────────

     Sahip bir "pitch deck" istedi. İstenmeyen şey ise aynı cümlede yazılı:
     *"uydurulmuş rakamla değil"*. İndirilen bir sunum dosyası bu iki isteği
     aynı anda karşılayamaz — bir kez indirildikten sonra düzeltilemez ve
     ürün değiştiği gün elde kalan, kimsenin geri alamayacağı bir iddia
     olur. Bu yüzden deck bir SAYFA ve öteki sayfalarla AYNI olguları okuyor
     (`InvestorDossier`): ürün değişince deck de değişir, çünkü ikisi tek bir
     kaynak.

     ── NEDEN AYRI BİR SAYFA ──────────────────────────────────────────────

     `/investors` bir DURUM sayfasıdır ve gezinilir; burası bir SIRADIR ve
     bir kez okunur. Aynı olguların iki düzeni, iki farklı okuyucu için:
     biri arayan, öteki ilk kez bakan. Panellerin metni katalogdan, `Measured`
     satırı ise ÖLÇÜLEN değerden gelir — bir panelin kanıtı yoksa paneli de
     yoktur.

     Sahne: tuval yalnız önsözde (`SAHNE-B7`), geri kalan saf CSS. --}}
@section('title', $st['investorsDeckMetaTitle'])
@section('description', $st['investorsDeckMetaDescription'])

@section('content')
    <main id="main-content" class="site-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['investorsDeckHeading'],
            'prologueLead' => $st['investorsDeckLead'],
            'prologueVariant' => 'conduit',
        ])

        <section id="deck" aria-labelledby="deck-heading"
                 class="site-stage site-deep site-band" data-scene-progress>
            <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x" aria-hidden="true">
                <span class="scene-nebula"></span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x-" aria-hidden="true">
                <span class="scene-orbit">
                    <span class="scene-orbit-ring" style="--scene-orbit-scale: 1; --scene-orbit-spin: 58s"></span>
                    <span class="scene-orbit-ring" data-spin="reverse" style="--scene-orbit-scale: 0.6; --scene-orbit-spin: 37s"></span>
                </span>
            </div>

            <div class="site-stage-layer scene-plane" data-plane="near" data-depth="front" aria-hidden="true">
                <span class="scene-horizon scene-progress-glow"></span>
            </div>

            <div class="site-stage-layer" data-depth="front" aria-hidden="true">
                <span class="scene-vignette"></span>
            </div>

            <div class="site-stage-content site-measure-page">
                {{-- Başlık önsözde `h1` olarak zaten basıldı; burada ikinci
                     kez yazmak aynı sözcüğü alt alta iki kez göstermek
                     olurdu (`docs/146` §5, göz izi ölçümü). Bölüm yine de
                     adlandırılmak zorunda: `aria-labelledby` bandın kendi
                     başlığına işaret eder. --}}
                <h2 id="deck-heading" class="sr-only">{{ $st['investorsDeckHeading'] }}</h2>

                <ol class="site-ledger" role="list">
                    @foreach ($deck as $index => $panel)
                        <li class="site-panel site-lit site-ledger-card scene-reveal" style="--scene-order: {{ $index }}">
                            <span class="site-ledger-index" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="site-display-3">{{ $panel['title'] }}</h3>
                            <p class="site-ledger-body">{{ $panel['body'] }}</p>
                            <p class="site-ledger-note">{{ $st['investorsDeckEvidenceLabel'] }}: {{ $panel['evidence'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Deck'in kapanışı bir eylem değil bir YOL: kısa sürümü okuyan
             kişinin sonraki sorusu "kanıtı nerede" ya da "kime yazarım". --}}
        <section id="next" aria-labelledby="next-heading" class="site-measure-page site-page-body">
            <h2 id="next-heading" class="site-display-2">{{ $st['investorsContactHeading'] }}</h2>
            <p class="site-section-lead">{{ $st['investorsContactLead'] }}</p>
            <p>
                <a href="/investors/product" class="site-action site-cta">{{ $st['investorsBuiltCta'] }}</a>
            </p>
            <p>
                <a href="/investors/contact" class="site-action site-cta" data-emphasis="true">{{ $st['investorsContactCta'] }}</a>
            </p>
        </section>
    </main>
@endsection
