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
