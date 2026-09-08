@extends('public.layout')

{{-- YARDIM GİRİŞİ — makaleyi SARAN yüzey (FF-240).

     ── Makale değişmedi ──────────────────────────────────────────────────

     Makale DİLE GÖRE DOSYADAN gelir (`docs/89`): belge, arayüz etiketi
     değil. Başlık ve açıklama da makalenin kendi içinden. Bu paket tek bir
     makale cümlesi yazmadı ve `resources/help/` altına dokunmadı.

     ── Eklenen iki şey ve NEDEN ──────────────────────────────────────────

       1. DİZİN — başka ne yazıldığını gösterir. Liste `HelpDirectory`den,
          yani makale DOSYALARININ kendisinden gelir; adlar katalogda değil,
          makalenin `@section('title')` bildirimindedir.
       2. ÇIKMAZ SOKAK PANELİ — aradığını bulamayan biri ne yapacağını
          bilir. Bugüne kadar makalenin sonundaki tek bir cümleydi.

     ── Dizin NEDEN iki makaleden AZINDA çizilmiyor ───────────────────────

     Tek maddelik bir dizin, okuyucunun zaten üzerinde olduğu sayfayı
     listeler: bir bilgi değil, bir gürültü. Bugün depoda TEK makale var
     (`resources/help/en/` ölçüldü), dolayısıyla bant bugün çizilmiyor.
     İkinci makale yazıldığı gün — kod değişmeden — belirir.

     ── Denetleyici NEDEN değişmedi ───────────────────────────────────────

     Liste `ShowHelpController` üzerinden geçirilmedi, çünkü o denetleyici
     ve `HelpLibrary` şu anda ayrı bir pakette (yardım merkezi) yeniden
     yazılıyor. Yüzeyi oradan beslemek iki paketi aynı satırlarda
     çarpıştırırdı. `layout.blade.php` de `DocumentLocale`i doğrudan
     çağırıyor; saf bir okuma yardımcısını şablondan çağırmak bu depoda
     yeni bir şekil değil. --}}

@section('content')
    @php
        $helpArticles = \App\Support\Site\HelpDirectory::articles();
        // Okunan makale KENDİ listesinde işaretlidir. Adres sunucudan
        // gelir; ikinci bir "hangi sayfadayım" kaynağı tutmak, ikisinin
        // ayrışabileceği bir yer açardı.
        $helpCurrentPath = '/'.trim(request()->getPathInfo(), '/');
    @endphp

    {{-- ATLAMA BAĞLANTISININ hedefi (kabuktaki `skipToContent`). Bu sayfada
         yoktu: makalenin `<main>` etiketi `id` taşımıyor ve klavyeyle gezen
         biri bağlantıya basınca hiçbir yere gitmiyordu. Sarmalayıcı hem o
         hedefi verir hem de dizini makaleyle aynı kapsama alır. --}}
    <div id="main-content">
        @if (count($helpArticles) > 1)
            <div class="mx-auto w-full max-w-3xl px-4 pt-6">
                {{-- KAPALI BAŞLAR — altbilginin içerik katıyla aynı gerekçe
                     (`docs/136` §6.4): dar ekranda çok satırlı bir liste,
                     ilk ekranı GERÇEK içerikten önce doldurur. `<details>`
                     tarayıcının kendi düğmesidir: klavyeyle çalışır,
                     durumunu ekran okuyucuya söyler, betiksiz açılır ve
                     içindeki her bağlantı sunucu HTML'inde zaten durur.
                     Geniş ekranda da kapalı başlar — tek kod yolu. --}}
                <details class="site-help-index">
                    <summary class="site-help-index-toggle">
                        <span>{{ $st['helpArticlesHeading'] }}</span>
                        <x-phosphor name="caret-down" />
                    </summary>

                    <ul class="site-help-index-list">
                        @foreach ($helpArticles as $article)
                            <li>
                                {{-- BAŞLIK VE TARİF TEK HEDEF İÇİNDE: ikisini
                                     iki bağlantı yapmak, aynı yere giden iki
                                     komşu hedef üretirdi ve dar ekranda
                                     yanlış dokunmayı olasılık hâline
                                     getirirdi. --}}
                                <a href="{{ $article['path'] }}" class="site-help-article"
                                   @if ($article['path'] === $helpCurrentPath) aria-current="page" @endif>
                                    <span class="site-help-article-title">{{ $article['title'] }}</span>
                                    @if ($article['summary'] !== null)
                                        <span class="site-help-article-summary">{{ $article['summary'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </div>
        @endif

        @include($helpView)

        {{-- ÇIKMAZ SOKAK. Aradığını bulamayan biri sayfanın sonuna gelir ve
             bugüne kadar orada tek bir cümle vardı. Panel bir HIZ SÖZÜ
             VERMEZ — yanıt taahhüdü ayrı bir anahtardır ve yalnız sahibi
             bir sayı girdiyse `/contact` üzerinde çizilir (`docs/125`). --}}
        <div class="mx-auto w-full max-w-3xl px-4 pb-10">
            <section aria-labelledby="help-stuck-heading" class="site-panel">
                <h2 id="help-stuck-heading" class="site-panel-title">{{ $st['helpStuckHeading'] }}</h2>
                <p>{{ $st['helpStuckBody'] }}</p>
                <a href="/contact" class="site-cta" data-emphasis="true">{{ $st['aboutReachCta'] }}</a>
            </section>
        </div>
    </div>
@endsection
