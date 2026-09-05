@extends('public.layout')

{{-- ZABUNO SERVICE PASS — FF-117, yönerge §8.

     Bir restoran mutfağındaki servis fişi: hangi sayfa, hangi aşamada, en son
     ne zaman dokunuldu. Sakin, kurumsal ve İKONSUZ (`docs/118` E6).

     ── NE DEĞİŞTİ ────────────────────────────────────────────────────────

     Bu ekran kendi belge kökünü kuruyordu: kendi belge başlığı, kendi satır
     içi stil bloğu, kendi sabit renkleri ve HİÇ gezintisi yoktu. Yani
     sitenin ikinci bir kabuğuydu ve üst çubuğa eklenen hiçbir şey buraya
     ulaşmıyordu. Artık aynı kabuğu giyiyor; ziyaretçi 404 alsa bile sitenin
     geri kalanını gezebiliyor.

     Renkler de şablondan çıktı: fişin görünümü artık `--space-*`,
     `--radius-*` ve tema jetonlarından besleniyor (`docs/119` §18: renk
     bileşende sabit yazılmaz).

     ── DEĞİŞMEYEN ────────────────────────────────────────────────────────

     Kritik metin ve çıkış bağlantıları JavaScript'e BAĞLI DEĞİLDİR
     (`docs/119` §17.3, §19): bu sayfa çoğunlukla 404 gövdesi olarak sunulur
     ve bir tarayıcı betiği çalıştırmasa bile ziyaretçi ne olduğunu
     okuyabilmeli, çıkabilmeli. Kabuk da betiksiz çalışır.

     Sahte ilerleme yüzdesi ve uydurma geri sayım YOK: tutulmayacak bir söz,
     hiç söz vermemekten kötüdür. --}}

@section('title', $st['pageState.title'])

@section('content')
    <main id="main-content" class="site-main site-pass-main">
        <div class="site-pass">
            <p class="site-pass-brand">{{ $st['brand'] }}</p>

            <h1 class="site-pass-headline">
                {{ $isMaintenance ? $st['pageState.maintenanceHeadline'] : $st['pageState.headline'] }}
            </h1>

            <p class="site-pass-lede">
                {{ $isMaintenance ? $st['pageState.maintenanceLede'] : $st['pageState.lede'] }}
            </p>

            @if ($page->title !== '')
                {{-- Site haritasındaki cümle bir BAŞLIK değil bir TARİF: "QR, dijital,
                     mobil ve temassız menü özelliklerini tek sayfada anlatır". Onu
                     "Sayfa:" satırına koymak, bir fişe paragraf yazmak olurdu. Burada
                     ne olacağını anlatan bir cümle olarak duruyor; kimliği ise
                     adresin kendisi. --}}
                <p class="site-pass-promise">{{ $page->title }}</p>
            @endif

            <dl class="site-pass-rows">
                <div class="site-pass-row">
                    <dt>{{ $st['pageState.pageLabel'] }}</dt>
                    {{-- Kimlik ADRESTİR: kısa, tek anlamlı ve fişe yazılabilir. --}}
                    <dd><code>{{ $page->canonical_path }}</code></dd>
                </div>
                <div class="site-pass-row">
                    <dt>{{ $st['pageState.stageLabel'] }}</dt>
                    <dd>{{ $stage }}</dd>
                </div>
                @if ($page->updated_at !== null)
                    <div class="site-pass-row">
                        <dt>{{ $st['pageState.updatedLabel'] }}</dt>
                        {{-- Gerçek tarih. Uydurma bir geri sayım ya da sahte bir
                             ilerleme yüzdesi yok. --}}
                        <dd><time datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->toDateString() }}</time></dd>
                    </div>
                @endif
            </dl>

            {{-- Çıkmaz sokak yok. Üst çubuktaki menü bir DOKUNUŞ uzakta duruyor
                 ama 404 alan bir ziyaretçiye çıkışı aramak düşmemeli: en olası
                 üç yol burada, açıkça yazılı. --}}
            <div class="site-pass-actions">
                <a href="/" data-emphasis="true">{{ $st['pageState.home'] }}</a>
                <a href="/pricing">{{ $st['pageState.explore'] }}</a>
                <a href="/contact">{{ $st['pageState.contact'] }}</a>
            </div>
        </div>
    </main>
@endsection
