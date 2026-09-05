@props(['surface' => 'guest'])
{{-- "ZABUNO" ÇERÇEVESİ — görünmez sahiplik sınırı. FF-175, `docs/113` §6.

     NE YAPAR, ÜÇ MADDE:

     1. BAĞLAM KURAR. Yüzeyin adını tek bir kök öğeye yazar. Tema, dil ve yön
        BELGE KÖKÜNDE zaten çözülüyor (`partials/theme-bootstrap` ve şablonun
        kendi `lang`/`dir` öznitelikleri); onları burada TEKRAR yazmak, aynı
        gerçeğin iki sahibi olması demekti.
     2. YUVA AÇAR. `header` ve `footer` için iki yuva tanımlar. Yarın zabuno
        kendi başlığını ve altbilgisini misafir menüsüne koyduğunda, o kod
        DÖRT şablona değil buraya girer.
     3. SINIR ÇİZER. İçerideki hiçbir parça çerçevenin dışını bilmez; çerçeve
        de içeridekilerin ne olduğunu bilmez (`docs/37` §2.4).

     NE YAPMAZ: stil vermez, kutu üretmez, `z-index` tüketmez, ölçü dayatmaz.
     `[data-zabuno]{display:contents}` (bkz. `partials/guest-surface-style`)
     ile bu öğe düzen ağacında HİÇ kutu oluşturmaz — yani içindeki yapışkan
     başlığın `top` hesabı, grid akışı ve yığılma bağlamı çerçeve varmış gibi
     davranmaz. "Görünmez" ile "görünmeyen" arasındaki fark budur.

     BOŞ YUVA HİÇBİR DÜĞÜM ÜRETMEZ. `display:none` taşıyan boş bir `<div>`
     bile bırakmayız: ekran okuyucuda boş bir bölge, testte yanlış bir "var"
     ve bir gün yanlışlıkla biçimlenecek bir kap olurdu.

     HAFİFLİĞİ BOZMAZ. Çerçeve bir Blade parçasıdır; ne JS ne ayrı bir CSS
     dosyası getirir. Ölçülebilir kabul ölçütü `docs/113` §6.3'te yazılı ve
     `GuestMenuDesignLanguageTest` içinde dondurulmuştur: çerçeve girdikten
     sonra misafir sayfasının istek sayısı ve JS baytı DEĞİŞMEZ — ikisi de
     bugün sıfırdır. --}}
<div data-zabuno data-zabuno-surface="{{ $surface }}">
    @if (isset($header) && trim((string) $header) !== '')
        <div data-zabuno-slot="header">{{ $header }}</div>
    @endif

    {{ $slot }}

    @if (isset($footer) && trim((string) $footer) !== '')
        <div data-zabuno-slot="footer">{{ $footer }}</div>
    @endif
</div>
