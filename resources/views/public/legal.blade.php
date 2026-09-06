@extends('public.layout')

{{-- YASAL BELGENİN TEK ŞABLONU — FF-198 (`docs/107` Faz 1.2, `docs/124`).

     Sekiz belge bu dosyadan çizilir; aralarındaki fark yalnız içeriktir.
     Şablonda TEK BİR sabit kullanıcı metni yok (I18N-SSR-RATCHET-16):
     belge metni kütüphaneden (`LegalLibrary`, İngilizce kaynak), etiketler
     katalogdan (`$st`). İKON YOK (`docs/118` E6). Dar ekran taban
     (`docs/118` E1, E3): dolgu bir kez uygulanır (`.site-legal`), satır
     uzunluğu okunur, her bağlantı 44 piksel. --}}

@section('title', $document->title)
@section('description', $document->summary)

@section('content')
    <main id="main-content"
          class="site-legal"
          data-legal-document="{{ $document->key }}"
          data-legal-version="{{ $document->version }}"
          data-legal-effective="{{ $document->effectiveDate }}">
        @if ($reviewPending)
            {{-- İNCELEME NOTU: belge yayında ama bir hukukçu okuyana kadar
                 bunu SÖYLER. `LEGAL_REVIEWED_AT` dolunca kalkar (`LegalReview`). --}}
            <p class="site-legal-notice" role="note" data-legal-review="pending">{{ $st['legalReviewPending'] }}</p>
        @endif

        <h1 class="site-page-title">{{ $document->title }}</h1>
        <p class="site-legal-summary">{{ $document->summary }}</p>

        {{-- SÜRÜM VE YÜRÜRLÜK belgenin kimliğidir: onay kaydı bu sürüme
             yazılır (`consent_records`). --}}
        <dl class="site-legal-meta">
            <div>
                <dt>{{ $st['legalVersion'] }}</dt>
                <dd>{{ $document->version }}</dd>
            </div>
            <div>
                <dt>{{ $st['legalEffective'] }}</dt>
                <dd><time datetime="{{ $document->effectiveDate }}">{{ $document->effectiveDate }}</time></dd>
            </div>
        </dl>

        {{-- SAYFA İÇİ BÖLÜM LİSTESİ: numara veriden değil sıradan gelir. --}}
        <nav aria-label="{{ $st['legalContents'] }}" data-legal-contents class="site-legal-contents">
            <ol>
                @foreach ($document->sections as $section)
                    <li><a href="#section-{{ $loop->iteration }}">{{ $loop->iteration }}. {{ $section->heading }}</a></li>
                @endforeach
            </ol>
        </nav>

        @foreach ($document->sections as $section)
            <section id="section-{{ $loop->iteration }}"
                     aria-labelledby="section-{{ $loop->iteration }}-heading"
                     class="site-legal-section">
                <h2 id="section-{{ $loop->iteration }}-heading">{{ $loop->iteration }}. {{ $section->heading }}</h2>
                @foreach ($section->paragraphs as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach

        @if ($showConsentPreference ?? false)
            {{-- ÇEREZ TERCİHİ YENİDEN SEÇİLEBİLİR (FF-198). Mevcut karar
                 görünür; iki düğme aynı uca gider ve JavaScript gerektirmez. --}}
            <section id="measurement-preference"
                     aria-labelledby="measurement-preference-heading"
                     class="site-legal-section"
                     data-consent-preference="{{ $consentState }}">
                <h2 id="measurement-preference-heading">{{ $st['cookiesPreferenceHeading'] }}</h2>
                <p>{{ $st['cookiesPreferenceCurrent'] }} <strong>{{ $consentStateLabel }}</strong></p>
                <form method="post" action="/consent/measurement" class="site-consent-actions">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <button type="submit" name="decision" value="accept" class="site-consent-button" data-emphasis="true">{{ $st['consentAccept'] }}</button>
                    <button type="submit" name="decision" value="decline" class="site-consent-button">{{ $st['consentDecline'] }}</button>
                </form>
            </section>
        @endif

        @if ($showDataRequest ?? false)
            {{-- HESAP VERİSİ TALEBİNİN YOLU (FF-169, `docs/110` P0-09).

                 Söylediği tek şey, sahibin BUGÜN ne yapabileceğidir: menüsünü
                 kendisi indirebilir, gerisi için çalışan bir iletişim yolu
                 vardır. --}}
            <section id="account-data-request"
                     aria-labelledby="account-data-request-heading"
                     class="site-legal-section">
                <h2 id="account-data-request-heading">{{ $st['dataRequestHeading'] }}</h2>

                <p>{{ $st['dataRequestBody'] }}</p>

                <p>
                    <a href="/contact" class="site-action font-medium text-fg underline">
                        {{ $st['dataRequestCta'] }}
                    </a>
                </p>

                {{-- Etiket KENDİ SATIRINDA: araya sabit bir iki nokta yazmak
                     şablona çevrilemez bir noktalama gömerdi ve bazı diller
                     onu başka türlü diziyor. --}}
                <p class="text-sm text-fg-secondary">
                    <span class="block font-medium text-fg">{{ $st['dataRequestAddressLabel'] }}</span>
                    {{-- ADRES UYDURULMAZ. Girilmemişse sayfa bunu söyler. --}}
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
