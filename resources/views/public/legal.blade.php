@extends('public.layout')

@section('title', $title)
@section('description', $st['legalPendingDescription'])

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-3xl font-bold" style="font-size: var(--font-size-display)">{{ $title }}</h1>
        <p class="mt-6 max-w-2xl text-fg-secondary">
{{ $st['legalPending'] }}
        </p>

        @if ($showDataRequest ?? false)
            {{-- HESAP VERİSİ TALEBİNİN YOLU (FF-169, `docs/110` P0-09).

                 Sayfanın hukuki hükmü hâlâ incelemede; bu bölüm o hükmün
                 yerine geçmez. Söylediği tek şey, sahibin BUGÜN ne
                 yapabileceğidir: menüsünü kendisi indirebilir, gerisi için
                 çalışan bir iletişim yolu vardır. --}}
            <section aria-labelledby="account-data-request"
                     class="mt-10 max-w-2xl rounded-lg border border-border p-4">
                <h2 id="account-data-request" class="text-xl font-semibold text-fg">
                    {{ $st['dataRequestHeading'] }}
                </h2>

                <p class="mt-3 text-fg-secondary">{{ $st['dataRequestBody'] }}</p>

                <p class="mt-4">
                    <a href="/contact" class="font-medium text-fg underline">
                        {{ $st['dataRequestCta'] }}
                    </a>
                </p>

                {{-- Etiket KENDİ SATIRINDA: araya sabit bir iki nokta yazmak
                     şablona çevrilemez bir noktalama gömerdi ve bazı diller
                     onu başka türlü diziyor. --}}
                <p class="mt-4 text-sm text-fg-secondary">
                    <span class="block font-medium text-fg">{{ $st['dataRequestAddressLabel'] }}</span>
                    {{-- ADRES UYDURULMAZ. Girilmemişse sayfa bunu söyler ve
                         sahte bir kutu göstermez; talebin yolu (form) yine de
                         açıktır ve cümlenin ikinci yarısı bunu yazar. --}}
                    @if ($dataRequestAddress === null)
                        {{ $st['dataRequestAddressMissing'] }}
                    @else
                        {{ $dataRequestAddress }}
                    @endif
                </p>
            </section>
        @endif
    </main>
@endsection
