{{--
    FİYAT VERİDİR, KOD DEĞİL — `docs/88` (P1-01).

    Bu bölüm plan kataloğundan okur. Rakamı sahibi girer; sayfaya elle
    yazmak, fiyat değiştiği gün ikinci bir gerçek kaynak yaratır ve ikisi
    ayrıştığında hangisinin doğru olduğunu kimse bilemez.

    Metin de şablonda DEĞİL katalogda yaşar (`docs/85` ile aynı gerekçe).
--}}
@php
    /* Bu bölüm hem ana sayfada (bir alt başlık olarak) hem de kendi
       sayfasında (SAYFANIN başlığı olarak) görünür. Etiket çağırana
       bırakılır: `/pricing` üzerinde ayrı bir `<h1>` basmak "Pricing"i üst
       üste iki kez yazardı ve ekran okuyucuda iki ayrı bölüm gibi okunurdu
       (`docs/89`). */
    $pricingHeadingTag = $pricingHeadingTag ?? 'h2';
    $pricingHeadingClass = $pricingHeadingTag === 'h1' ? 'text-3xl font-bold' : 'text-2xl font-bold';

    /* Giriş cümlesi BAŞLIĞIN ALTINDA durur. Sayfa başlığı parçaya
       devredilince cümle yukarıda kalmıştı: okuyucu neyin açıklamasını
       okuduğunu, ancak sonraki satırda öğreniyordu. */
    $pricingLead = $pricingLead ?? null;
@endphp
<section id="pricing" aria-labelledby="pricing-heading" class="flex flex-col gap-4">
    <{{ $pricingHeadingTag }} id="pricing-heading" class="{{ $pricingHeadingClass }}">{{ $st['pricingHeading'] }}</{{ $pricingHeadingTag }}>

    @if ($pricingLead)
        <p class="text-fg-secondary">{{ $pricingLead }}</p>
    @endif

    @if (empty($plans))
        {{--
            Boş bir fiyat tablosu, ziyaretçiye "bu ürün hazır değil"
            dedirtir. Sayfa DURUMU söyler ve bir ÇIKIŞ YOLU bırakır: boş bir
            hâl bir hata değildir, ama bir çıkmaz da olmamalıdır (`docs/66`).
        --}}
        <p class="text-fg-secondary">
            {{ $st['pricingEmpty'] }}
            <a class="underline underline-offset-2" href="/contact">{{ $st['pricingEmptyCta'] }}</a>
        </p>
    @else
        {{--
            HER PLANDA OLAN, bir kez söylenir.

            Yetenek listesi EK yetkileri anlatır; temel zinciri değil. Yalnız
            onları göstermek, ücretsiz kademeyi "hiçbir şey içermiyor" gibi
            gösterirdi — oysa menü, yayın, karekod ve misafir sayfası her
            planda var (`docs/90`).
        --}}
        <div class="rounded-lg border border-border p-4">
            <p class="font-semibold">{{ $st['includedHeading'] }}</p>
            <p class="mt-1 text-fg-secondary">{{ $st['includedBody'] }}</p>
        </div>

        <ul class="flex flex-col gap-4">
            @foreach ($plans as $plan)
                <li class="flex flex-col gap-1 rounded-lg border border-border p-4">
                    <span class="text-lg font-semibold">{{ $plan['name'] }}</span>

                    @if (! empty($plan['free']))
                        {{-- `0,00 TRY` teknik olarak doğru ama insan onu
                             "ücretsiz" diye okumaz, bir hata sanır. --}}
                        <span class="text-fg">{{ $st['free'] }}</span>
                    @elseif ($plan['price'] === null)
                        {{--
                            Tutarı girilmemiş bir planı "0" ya da "ücretsiz"
                            göstermek, tutulmayacak bir söz vermek olurdu.
                        --}}
                        <span class="text-fg-secondary">
                            {{ $st['perRestaurant'] }}
                            <a class="underline underline-offset-2" href="/contact">{{ $st['perRestaurantCta'] }}</a>
                        </span>
                    @else
                        {{-- `tabular-nums`: rakamlar eşit genişlikte olmazsa
                             planlar arasında fiyat karşılaştırması gözle
                             yapılamaz. --}}
                        <span class="text-fg">
                            <span class="tabular-nums">{{ $plan['price'] }}</span>
                            <span class="text-meta text-fg-muted">{{ $st['perMonth'] }}</span>
                        </span>
                    @endif

                    @if (! empty($plan['entitlements']))
                        <p class="mt-2 text-meta font-medium text-fg-secondary">{{ $st['adds'] }}</p>
                        <ul class="flex list-disc flex-col gap-1 pl-5 text-fg-secondary">
                            @foreach ($plan['entitlements'] as $entitlement)
                                <li>{{ $entitlement }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        <p class="text-meta text-fg-muted">
            {{ $st['unsure'] }}
            <a class="underline underline-offset-2" href="/contact">{{ $st['unsureCta'] }}</a>
        </p>
    @endif
</section>
