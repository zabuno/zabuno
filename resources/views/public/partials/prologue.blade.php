{{-- ══ ÖNSÖZ — kurumsal sayfaların ORTAK sahne bandı (`docs/146` §10) ══════

     Döngü 1'in kendi eksik listesi ilk maddesinde bunu yazıyordu: *"Sahne
     yalnız ana sayfada."* (`docs/146` §9 madde 1). Bu parça o maddenin
     cevabıdır ve cevabı SAYFA SAYISINDAN bağımsız kurar.

     ── NEDEN BİR PARÇA, HER SAYFAYA KOPYALANMIŞ MARKUP DEĞİL ──

     Kabuğun kendi kuralı zaten bu (`docs/100` §2, `SHELL-SINGLE-SOURCE-01`):
     bir yerde değiştir, her yerde değişsin. Sahne bandını her sayfaya elle
     yazmak, yarın katman sırası düzeldiğinde altı dosyanın beşini
     güncellemekle biterdi — ve unutulan altıncı sayfa, sitenin ikinci bir
     hâli olurdu.

     ── NEDEN KABUĞA DEĞİL, SAYFAYA ──

     Bandı `layout.blade.php` içine koymak onu 386 kütük sayfasına da bir
     anda giydirirdi; o sayfaların kompozisyonu ölçülmedi ve ölçülmemiş bir
     şeyi yayına almak, sahibin göreceği ilk kusuru üretir. Sayfa bandı
     İSTER; kabuk onu taşır ama dayatmaz.

     ── DEĞİŞKENLER ──

     `$prologueHeading`  — zorunlu. KATALOGDAN gelir; burada dize yazılmaz.
     `$prologueLead`     — isteğe bağlı giriş cümlesi.
     `$prologueTag`      — `h1` (varsayılan) ya da `h2`.
     `$prologueVariant`  — `orbit` | `grid` | `conduit`. Sahne dağarcığından
                           hangi ikinci fikrin çizileceği. Üç sayfa üç ayrı
                           yüz görsün diye var: aynı bandı üç kez görmek,
                           bandın kendisini görünmez yapar.
     `$prologueCta`      — `['href' => …, 'label' => …]` ya da yok.
     `$prologueId`       — başlığın `id`si. Sayfanın gövdesindeki bir bölüm
                           `aria-labelledby` ile ONA işaret edebilsin diye:
                           aynı sözcüğü bir kez bantta, bir kez gövdede
                           yazmak, ekran okuyucuda sayfayı ikiye bölerdi.

     ── TEK TUVAL, SAYFA BAŞINA ──

     Her sayfada BİR `data-scene="field"` var. İkinci bir WebGL bağlamının
     maliyeti Döngü 1'de tahmin edildi, ölçülmedi (`docs/146` §9 madde 4) ve
     Döngü 2 de ölçmedi; bu yüzden ikinci bağlam AÇILMIYOR. Yörünge, tel
     kafes ve veri hattı saf CSS'tir — ölçülmemiş bir maliyeti ürüne sokmak
     yerine, aynı görsel fikri sıfır bağlamla kuran yol seçildi. --}}
@php
    $prologueTag = $prologueTag ?? 'h1';
    $prologueVariant = $prologueVariant ?? 'orbit';
    $prologueLead = $prologueLead ?? null;
    $prologueCta = $prologueCta ?? null;
    $prologueId = $prologueId ?? null;
@endphp
<section class="site-stage site-deep site-veil site-prologue" data-scene-progress>
    {{-- Yıldız alanı. Betik yoksa tuval boş kalır ve GÖRÜNMEZ; geriye
         nebulanın kendisi kalır. --}}
    <div class="site-stage-layer">
        <canvas class="scene-canvas" data-scene="field" data-scene-sway="0.14" data-scene-speed="0.18" aria-hidden="true"></canvas>
    </div>

    {{-- EN UZAK DÜZLEM ve yatay eksende: kaydırma ilerledikçe nebula yana
         süzülür, üstelik şekli de değişir (`scene-morph`). Sahibin "sağlı
         sollu" isteği burada bir şeritte değil, bandın KENDİSİNDE. --}}
    <div class="site-stage-layer scene-plane scene-morph" data-plane="far" data-axis="x" aria-hidden="true">
        <span class="scene-nebula"></span>
    </div>

    @if ($prologueVariant === 'orbit')
        {{-- YÖRÜNGE — üç halka, üç hız, biri ters yönde. Ortadaki düzlem TERS
             eksende akar: iki katman aynı yöne kayarsa göz tek bir blok
             görür, zıt yönde kaydıklarında aralarında derinlik doğar. --}}
        <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x-" data-depth="mid" aria-hidden="true">
            <span class="scene-orbit">
                <span class="scene-orbit-ring" style="--scene-orbit-scale: 1; --scene-orbit-spin: 46s"></span>
                <span class="scene-orbit-ring" data-spin="reverse" style="--scene-orbit-scale: 0.66; --scene-orbit-spin: 31s"></span>
                <span class="scene-orbit-ring" style="--scene-orbit-scale: 0.38; --scene-orbit-spin: 19s"></span>
            </span>
        </div>
    @elseif ($prologueVariant === 'grid')
        {{-- TEL KAFES ZEMİN — sahnenin "yer"i. Yıldız alanı derinlik verir
             ama zemin vermez; bakanın bir yüzeyin üstünde durduğunu
             söyleyen şey bu. --}}
        <div class="site-stage-layer" data-depth="mid" aria-hidden="true">
            <span class="scene-grid"></span>
        </div>
    @else
        {{-- VERİ HATTI — yıldız ve yörünge MEKÂN anlatır, bu İŞLEM anlatır.
             Çizgi durur, ışık akar; her hat farklı hızda, çünkü eşit hızda
             giden paketler bir ışık çubuğu gibi okunur, bir ağ gibi değil. --}}
        <div class="site-stage-layer scene-plane" data-plane="mid" data-axis="x" data-depth="mid" aria-hidden="true">
            <span class="scene-conduit">
                <span class="scene-conduit-line" style="--scene-conduit-run: 8s"></span>
                <span class="scene-conduit-line" data-direction="end" style="--scene-conduit-run: 11s; --scene-conduit-delay: 1.4s"></span>
                <span class="scene-conduit-line" style="--scene-conduit-run: 6.5s; --scene-conduit-delay: 0.7s"></span>
            </span>
        </div>
    @endif

    {{-- Ufuk EN YAKIN düzlem ve bölümün ilerlemesini okur. --}}
    <div class="site-stage-layer scene-plane" data-plane="near" data-depth="front" aria-hidden="true">
        <span class="scene-horizon scene-progress-glow"></span>
    </div>

    {{-- Vinyet: yıldızların ÜSTÜNDE, metnin ALTINDA. Perde kontrastı
         düşürmez, YÜKSELTİR (`site-scene.css` §3). --}}
    <div class="site-stage-layer" data-depth="front" aria-hidden="true">
        <span class="scene-vignette"></span>
    </div>

    <div class="site-stage-content site-measure-page site-prologue-inner">
        {{-- GİRİŞ ANİMASYONU YOK VE BU BİLEREK.

             Önsöz her zaman ilk ekrandadır; ilk ekrandaki bir başlık, bir
             `IntersectionObserver` geri çağrımının kuyruğunda bekleyemez
             (`depth.ts`, `observeReveals`). Betik ilk ekrandaki her
             `.scene-reveal`i zaten anında doğuruyor — burada sınıfı hiç
             yazmamak aynı sonucu bir kural olarak sabitliyor. --}}
        <div class="site-prologue-text">
            <{{ $prologueTag }} @if ($prologueId) id="{{ $prologueId }}" @endif class="site-display-2">{{ $prologueHeading }}</{{ $prologueTag }}>

            @if ($prologueLead)
                <p class="site-lede">{{ $prologueLead }}</p>
            @endif
        </div>

        @if ($prologueCta)
            <a href="{{ $prologueCta['href'] }}" class="site-action site-cta" data-emphasis="true">{{ $prologueCta['label'] }}</a>
        @endif
    </div>
</section>
