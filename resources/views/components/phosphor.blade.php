{{-- PHOSPHOR — kurumsal sitenin İKON DİLİ (`docs/118` E6, `docs/136` §4).

     ── Düzeltilmiş karar ────────────────────────────────────────────────

     `docs/118` E6 "kurumsal sitede ikon kullanılmayacaktır" diyordu ve
     kaynağı `docs/119` §1 madde 10'du. Sahip 2026-09-08'de düzeltti:
     *"hayır, Phosphor icon var, emoji yok. Kararı güncelle."*

     Kural artık İKİ YÜZEY İÇİN AYNI: **emoji yasak, Phosphor ilk.** Panel
     `@phosphor-icons/react` kullanıyor; kurumsal site React YÜKLEMEZ, bu
     yüzden aynı ikon ailesine sunucu tarafından erişir — ama aynı aileye.

     ── Yol verisi UYDURULMADI ───────────────────────────────────────────

     Aşağıdaki `d` dizeleri `node_modules/@phosphor-icons/react/dist/defs/`
     altındaki tanımların `regular` ağırlığından bire bir kopyalandı.
     `PhosphorIconSourceTest` her birini o dosyalarla KARŞILAŞTIRIR: elle
     "düzeltilmiş" ya da yeniden çizilmiş bir yol kapıyı kırar. Bir ikon
     kütüphanesinin değeri tutarlılığındadır; gözle çizilmiş bir kopya o
     değeri sessizce yok eder.

     Lisans: MIT (`node_modules/@phosphor-icons/react/LICENSE`), depoda.
     Dışarıdan hiçbir şey indirilmez — CSP zaten `default-src 'self'`.

     ── Erişilebilirlik ──────────────────────────────────────────────────

     İkon varsayılan olarak `aria-hidden`: yanında her zaman gerçek bir
     sözcük durur ve ekran okuyucunun aynı şeyi iki kez söylemesi gürültüdür.
     Tek başına duran bir ikon `label` alır ve o zaman `img` rolü kazanır —
     ama kabukta tek başına duran ikon YOKTUR ve olmamalıdır. --}}
@props(['name', 'label' => null])

@php
    /*
        VİEWBOX 256×256 — Phosphor'un kendi tuvali. Başka bir tuvale
        taşımak, yol verisini yeniden ölçeklemek demekti ve o an kopya
        kaynağından ayrışırdı.
    */
    $paths = [
        'list' => 'M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,72H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z',
        'x' => 'M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z',
        'caret-down' => 'M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z',
        /* Dil denetiminin GRUP İŞARETİ (C1): küre, yanındaki iki dil
           sözcüğünü tek bir denetim olarak toplar; tek başına bir
           etiket değildir, bu yüzden `aria-hidden` kalır. */
        'globe' => 'M128,24h0A104,104,0,1,0,232,128,104.12,104.12,0,0,0,128,24Zm88,104a87.61,87.61,0,0,1-3.33,24H174.16a157.44,157.44,0,0,0,0-48h38.51A87.61,87.61,0,0,1,216,128ZM102,168H154a115.11,115.11,0,0,1-26,45A115.27,115.27,0,0,1,102,168Zm-3.9-16a140.84,140.84,0,0,1,0-48h59.88a140.84,140.84,0,0,1,0,48ZM40,128a87.61,87.61,0,0,1,3.33-24H81.84a157.44,157.44,0,0,0,0,48H43.33A87.61,87.61,0,0,1,40,128ZM154,88H102a115.11,115.11,0,0,1,26-45A115.27,115.27,0,0,1,154,88Zm52.33,0H170.71a135.28,135.28,0,0,0-22.3-45.6A88.29,88.29,0,0,1,206.37,88ZM107.59,42.4A135.28,135.28,0,0,0,85.29,88H49.63A88.29,88.29,0,0,1,107.59,42.4ZM49.63,168H85.29a135.28,135.28,0,0,0,22.3,45.6A88.29,88.29,0,0,1,49.63,168Zm98.78,45.6a135.28,135.28,0,0,0,22.3-45.6h35.66A88.29,88.29,0,0,1,148.41,213.6Z',
        /* Hesap açma eylemi (C1): ikon eylemin CİNSİNİ söyler, sözcük
           hedefini. İkisi birlikte durur, biri ötekinin yerine geçmez. */
        'user-plus' => 'M256,136a8,8,0,0,1-8,8H232v16a8,8,0,0,1-16,0V144H200a8,8,0,0,1,0-16h16V112a8,8,0,0,1,16,0v16h16A8,8,0,0,1,256,136Zm-57.87,58.85a8,8,0,0,1-12.26,10.3C165.75,181.19,138.09,168,108,168s-57.75,13.19-77.87,37.15a8,8,0,0,1-12.25-10.3c14.94-17.78,33.52-30.41,54.17-37.17a68,68,0,1,1,71.9,0C164.6,164.44,183.18,177.07,198.13,194.85ZM108,152a52,52,0,1,0-52-52A52.06,52.06,0,0,0,108,152Z',
        /* Görünüm tercihi (C2): üç seçenek, üç ayrı glif. Aynı gliften
           renkle türetilmiş iki hâl değil — dokunmalı cihazda vurgu
           yoktur ve seçili olanı yalnız renge bağlamak, o cihazda tek
           sinyal bırakırdı. Sözcük her üçünün yanında durur. */
        'sun' => 'M120,40V16a8,8,0,0,1,16,0V40a8,8,0,0,1-16,0Zm72,88a64,64,0,1,1-64-64A64.07,64.07,0,0,1,192,128Zm-16,0a48,48,0,1,0-48,48A48.05,48.05,0,0,0,176,128ZM58.34,69.66A8,8,0,0,0,69.66,58.34l-16-16A8,8,0,0,0,42.34,53.66Zm0,116.68-16,16a8,8,0,0,0,11.32,11.32l16-16a8,8,0,0,0-11.32-11.32ZM192,72a8,8,0,0,0,5.66-2.34l16-16a8,8,0,0,0-11.32-11.32l-16,16A8,8,0,0,0,192,72Zm5.66,114.34a8,8,0,0,0-11.32,11.32l16,16a8,8,0,0,0,11.32-11.32ZM48,128a8,8,0,0,0-8-8H16a8,8,0,0,0,0,16H40A8,8,0,0,0,48,128Zm80,80a8,8,0,0,0-8,8v24a8,8,0,0,0,16,0V216A8,8,0,0,0,128,208Zm112-88H216a8,8,0,0,0,0,16h24a8,8,0,0,0,0-16Z',
        'moon' => 'M233.54,142.23a8,8,0,0,0-8-2,88.08,88.08,0,0,1-109.8-109.8,8,8,0,0,0-10-10,104.84,104.84,0,0,0-52.91,37A104,104,0,0,0,136,224a103.09,103.09,0,0,0,62.52-20.88,104.84,104.84,0,0,0,37-52.91A8,8,0,0,0,233.54,142.23ZM188.9,190.34A88,88,0,0,1,65.66,67.11a89,89,0,0,1,31.4-26A106,106,0,0,0,96,56,104.11,104.11,0,0,0,200,160a106,106,0,0,0,14.92-1.06A89,89,0,0,1,188.9,190.34Z',
        'monitor' => 'M208,40H48A24,24,0,0,0,24,64V176a24,24,0,0,0,24,24H208a24,24,0,0,0,24-24V64A24,24,0,0,0,208,40Zm8,136a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V64a8,8,0,0,1,8-8H208a8,8,0,0,1,8,8Zm-48,48a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,224Z',
        /* Doğrulanmış tek sosyal profilin glifi (C3): bir platform
           ikonu MARKASINI taşır — GitHub'ın kendi işareti, benzeri değil.
           Şeritte tek başına durur, bu yüzden erişilebilir adı ikonun
           kendisinde değil, onu saran bağlantıda yaşar (`ICON-03`). */
        'github-logo' => 'M208.31,75.68A59.78,59.78,0,0,0,202.93,28,8,8,0,0,0,196,24a59.75,59.75,0,0,0-48,24H124A59.75,59.75,0,0,0,76,24a8,8,0,0,0-6.93,4,59.78,59.78,0,0,0-5.38,47.68A58.14,58.14,0,0,0,56,104v8a56.06,56.06,0,0,0,48.44,55.47A39.8,39.8,0,0,0,96,192v8H72a24,24,0,0,1-24-24A40,40,0,0,0,8,136a8,8,0,0,0,0,16,24,24,0,0,1,24,24,40,40,0,0,0,40,40H96v16a8,8,0,0,0,16,0V192a24,24,0,0,1,48,0v40a8,8,0,0,0,16,0V192a39.8,39.8,0,0,0-8.44-24.53A56.06,56.06,0,0,0,216,112v-8A58.14,58.14,0,0,0,208.31,75.68ZM200,112a40,40,0,0,1-40,40H112a40,40,0,0,1-40-40v-8a41.74,41.74,0,0,1,6.9-22.48A8,8,0,0,0,80,73.83a43.81,43.81,0,0,1,.79-33.58,43.88,43.88,0,0,1,32.32,20.06A8,8,0,0,0,119.82,64h32.35a8,8,0,0,0,6.74-3.69,43.87,43.87,0,0,1,32.32-20.06A43.81,43.81,0,0,1,192,73.83a8.09,8.09,0,0,0,1,7.65A41.72,41.72,0,0,1,200,104Z',
    ];
@endphp

<svg
    {{ $attributes->merge(['class' => 'site-icon']) }}
    viewBox="0 0 256 256"
    fill="currentColor"
    focusable="false"
    @if ($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
><path d="{{ $paths[$name] }}"></path></svg>
