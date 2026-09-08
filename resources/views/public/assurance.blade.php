@extends('public.layout')

{{-- GÜVENCE BEYANININ TEK ŞABLONU — FF-252 (`docs/107` Faz 3).

     İki sayfa bu dosyadan çizilir: güven merkezi (`/trust`) ve
     erişilebilirlik beyanı (`/accessibility`). Aralarındaki tek fark
     içeriktir ve önsöz bandının yüzüdür; ikisi de denetleyiciden gelir.
     Yasal belgelerin tek şablonu (`public.legal`) ile aynı karar: sekiz
     belge için sekiz şablon yazmak, birinde düzeltilen bir kusurun ötekinde
     durması demektir.

     ── ŞABLONDA TEK BİR SABİT KULLANICI METNİ YOK ──

     `lang/untranslatable-debt.json` sıfır ve bu bir cırcır değil MUTLAK
     YASAK (I18N-SSR-RATCHET-16). Beyan metni kütüphaneden gelir
     (`MeasuredAssuranceLibrary`, İngilizce kaynak — yasal belgelerle aynı
     desen), etiketler katalogdan (`$st`).

     ── SAHNE SAKİN ──

     Sayfa başına TEK tuval ve o tuval önsöz bandının içinde (`docs/146`
     §10.1, kapı SAHNE-B7). Gövdede hiçbir dekoratif katman yok ve bu
     bilerek: bu iki sayfa OKUNACAK sayfalardır. Bir eksiklik itirafının
     üstüne süsleme koymak, itirafı bir tasarım öğesine çevirirdi.

     `prefers-reduced-motion` MUTLAK: bu dosya hareket başlatan tek bir
     bildirim taşımıyor, dolayısıyla azaltılmış hareketle sayfa tam ve
     eksiksiz kalır — aynı bölümler, aynı bağlantılar, aynı metin.

     ── 320 PİKSEL TABAN ──

     Dolgu BİR kez uygulanır (`.site-legal`); iddia listesi kendi dolgusunu
     `.site-legal` sütununun İÇİNDE taşır, sütunu daraltmaz. Tek bir
     `max-width` bastırması, tek bir "mobilde gizle" ve tek bir genişlik
     medya sorgusu yoktur. --}}

@section('title', $statement->title)
@section('description', $statement->summary)

@section('content')
    @include('public.partials.prologue', [
        'prologueHeading' => $statement->title,
        'prologueLead' => $statement->summary,
        'prologueVariant' => $prologueVariant,
        'prologueId' => 'assurance-heading',
    ])

    @php
        /*
            HÂL ETİKETLERİ — katalog anahtarları, görünür dize değil.

            Harita burada duruyor çünkü çeviren şey `ClaimState`in kendi
            değeridir ve o değer çizimde zaten bir öznitelik olarak basılıyor
            (`data-claim-state`). İkinci bir eşleme yazmak, bir gün bir hâl
            eklendiğinde sessizce etiketsiz kalan bir satır üretirdi — bu
            yüzden bilinmeyen bir hâl için YEDEK YOK: harita eksikse sayfa
            kırılır ve kapı onu yakalar (`AssuranceHonestyGateTest`).
        */
        $claimStateLabels = [
            'measured' => $st['assuranceMeasured'],
            'not-measured' => $st['assuranceNotMeasured'],
            'known-gap' => $st['assuranceKnownGap'],
            'not-held' => $st['assuranceNotHeld'],
        ];
    @endphp

    <main id="main-content"
          class="site-legal"
          lang="en"
          aria-labelledby="assurance-heading"
          data-assurance-statement="{{ $statement->key }}">
        {{-- SAYFA İÇİ BÖLÜM LİSTESİ. Bir beyan uzundur ve aranan tek bir
             başlık için baştan sona kaydırmak, aramayı vazgeçmeye çevirir.
             Numara veriden değil SIRADAN gelir (`public.legal` ile aynı
             karar): bir bölüm silindiğinde "3, 5, 6" diye sayan bir belge
             doğmasın. --}}
        <nav aria-label="{{ $st['legalContents'] }}" data-assurance-contents class="site-legal-contents">
            <ol>
                @foreach ($statement->sections as $section)
                    <li><a href="#section-{{ $loop->iteration }}">{{ $loop->iteration }}. {{ $section->heading }}</a></li>
                @endforeach
            </ol>
        </nav>

        @foreach ($statement->sections as $section)
            <section id="section-{{ $loop->iteration }}"
                     aria-labelledby="section-{{ $loop->iteration }}-heading"
                     class="site-legal-section">
                <h2 id="section-{{ $loop->iteration }}-heading">{{ $loop->iteration }}. {{ $section->heading }}</h2>

                @foreach ($section->paragraphs as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach

                @if ($section->claims !== [])
                    {{-- İDDİA LİSTESİ.

                         HÂL RENKLE ANLATILMAZ. Her satır hâlini KELİMEYLE
                         yazar (`.site-claim-state`); geometri ve renk yalnız
                         o kelimeyi destekler. Rengi göremeyen bir okuyucu
                         için tek başına yeşil bir kenar hiçbir şey demez —
                         eksik sözleşme bandıyla aynı karar
                         (`site-shell.css`).

                         `<ul>` DEĞİL `<dl>` DE DEĞİL, düz bölümler: her
                         iddia bir başlık, bir hâl ve bir paragraftır; bir
                         tanım listesine sıkıştırmak ekran okuyucuda hâli
                         terimin bir parçası gibi okuturdu. --}}
                    <div class="site-claims">
                        @foreach ($section->claims as $claim)
                            <div class="site-claim" data-claim-state="{{ $claim->state->value }}">
                                <p class="site-claim-head">
                                    <strong class="site-claim-subject">{{ $claim->subject }}</strong>
                                    <span class="site-claim-state">{{ $claimStateLabels[$claim->state->value] }}</span>
                                </p>
                                <p class="site-claim-detail">{{ $claim->detail }}</p>
                                @if ($claim->evidence !== null)
                                    {{-- KANIT BİR SONUÇ DEĞİL, ÖLÇENİN ADIDIR.
                                         Etiket kendi satırında: araya sabit bir
                                         iki nokta yazmak şablona çevrilemez bir
                                         noktalama gömerdi (`public.legal` ile
                                         aynı gerekçe). --}}
                                    <p class="site-claim-evidence">
                                        <span class="site-claim-evidence-label">{{ $st['assuranceEvidence'] }}</span>
                                        <code>{{ $claim->evidence }}</code>
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </main>
@endsection
