@section('title', 'Masalarım için kart nasıl bastırırım?')
@section('description', 'Her masaya bir kod oluşturun, ölçü ve tasarım seçin, kartları PDF ya da SVG olarak indirin.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Bu makalenin okuru bir BASKI
         SİPARİŞİ vermeye gelmiştir: kırk masası, bir mukavvası ve bir
         yazıcısı vardır. Başlık bandın İÇİNDE; sayfada ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Masalarım için kart nasıl bastırırım?',
        'prologueLead' => 'Karekodlar ekranı bir ayar paneli değil, bir baskı siparişidir: ne basacaksın, hangi masalar, nasıl görünsün. Aşağıda adı geçen her ekran ve düğme BUGÜN var.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <hr class="border-border" role="separator">

    <section id="help-cards-first" aria-labelledby="help-cards-first-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-first-heading" class="text-2xl font-bold">Önce menüyü yayınlayın</h2>
        <p class="text-fg-secondary">
            Kod, yayınlanmış menünüzü açar; yani açacağı yayınlanmış bir menü olmalı. O menü
            olmadan <strong>Karekodlar</strong> size kart önermez, bunu söyler; masa kodlarını
            oluşturan düğme de kapalı kalır ve sebebi hemen altında yazar.
        </p>
        <p class="text-fg-secondary">
            Sıra şu: menüyü kurun, <strong>Yayın</strong> ekranını açıp yayınlayın, sonra çalışma
            alanındaki <strong>Karekodlar</strong> ekranına gelin.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-create" aria-labelledby="help-cards-create-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-create-heading" class="text-2xl font-bold">Her masaya bir kod</h2>
        <p class="text-fg-secondary">
            Kırk kodu tek tek oluşturmazsınız. <strong>Hangi masalar?</strong> adımının altında
            <strong>Yeni masa ekle</strong> bölümünü açın.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Masa sayısı</strong> sorusunu yanıtlayın — 1 ile 500 arasında bir tam
                sayı. Yanıtlamak zorunda olduğunuz tek soru budur.
            </li>
            <li>
                Gerisinin makul bir yanıtı zaten var ve <strong>Gelişmiş ayarlar</strong> altında
                bekler: <strong>Bölüm sayısı</strong> (1-50, varsayılan bir),
                <strong>Masa başına sandalye</strong> (1-20, varsayılan dört),
                <strong>Ad ön eki</strong> (en fazla 10 karakter; boş bırakılırsa adlar T1 ile
                başlar), <strong>Numaralandırma başlangıcı</strong> ve
                <strong>Numaralandırma aralığı</strong>.
            </li>
            <li><strong>Masa karekodlarını oluştur</strong> deyin.</li>
        </ol>
        <p class="text-fg-secondary">
            Ekran sonra oluşturduğu her masayı listeler; her biri bir bağlantıdır, telefonunuzdan
            açıp menünüze düştüğünü görebilirsiniz. Numaralandırma aralığı masa sayısıyla uyuşmak
            zorundadır — 20 masa isteyip 1-30 numaralandırması vermek, daha hiçbir şey
            oluşturulmadan reddedilir; yani elinizde yarım bir salon kalmaz.
        </p>
        <p class="text-fg-secondary">
            Kodları toplu oluşturmak bazı planlara dahildir, bazılarına değil. Sizinkine dahil
            değilse bozulan bir şey yoktur: ekran bunu açıkça söyler ve
            <strong>Planları gör</strong> der.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-print" aria-labelledby="help-cards-print-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-print-heading" class="text-2xl font-bold">Üç soru, sizin sıranızla</h2>
        <p class="text-fg-secondary">
            Önce <strong>Ne basacaksın?</strong> gelir, çünkü kâğıt boyutu bu yanıtın sorusu değil
            SONUCUDUR. Dört hazır çıktı var: pleksiglas için <strong>Masa kartı</strong>, uzun masa
            için <strong>Büyük masa kartı</strong>, kasa yanı için <strong>Duvar afişi</strong> ve
            dışarıdan okunacak <strong>Vitrin / kapı</strong>. Sırasıyla A6, A5, A4, A3 ve hepsi
            dikey.
        </p>
        <p class="text-fg-secondary">
            Başka bir şey mi gerekiyor? <strong>Başka bir ölçü gerekiyor</strong> tam listeyi açar:
            A3'ten A6'ya ve B3'ten B6'ya kâğıt boyutları, 1:2, 4:3 ve 16:9 serbest oranları, yön ve
            <strong>Dosya biçimi</strong> — baskıya hazır PDF ya da sınırsız büyüyen SVG. PNG kart
            yoktur ve sebebi ekranda yazar: raster bir görsel küçük bir kodun modül kenarlarını
            bulanıklaştırır.
        </p>
        <p class="text-fg-secondary">
            Sonra <strong>Hangi masalar?</strong>: <strong>Tüm masalar</strong>,
            <strong>Bir bölge</strong> (bölgeniz olduğunda çizilir) ya da <strong>Tek masa</strong>.
            Yalnız açık olan kodlar basılır — devre dışı bir kod hiçbir zaman kâğıda dökülmez.
        </p>
        <p class="text-fg-secondary">
            En sonda <strong>Nasıl görünsün?</strong> durur; beş tasarım <strong>Sade</strong>,
            <strong>Çerçeve</strong>, <strong>Markalı</strong>, <strong>Koyu</strong> ve
            <strong>Tabela</strong>. Hangisini seçerseniz seçin kod açık zemin üzerine koyu basılır,
            çünkü birçok telefon ters çevrilmiş kodu okumaz. Masa adı her kartta basılır, yani iki
            kart karışmaz. <strong>Kartın üstündeki yazı</strong> kodun altındaki satırı yalnız bu
            baskı için değiştirir; boş bırakırsanız kart hazır cümleyi taşır.
        </p>
        <p class="text-fg-secondary">
            Adımların yanındaki panel gerçek kartı çizer: ölçüsünü milimetreyle ve kodun kaç
            milimetre çıkacağını yazar. Bir seçim kodu güvenle okunamayacak kadar küçültüyorsa
            bunu orada söyler — kâğıttan önce, sonra değil.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-download" aria-labelledby="help-cards-download-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-download-heading" class="text-2xl font-bold">Dosyaları almak</h2>
        <p class="text-fg-secondary">
            Ekranın altındaki çubuk sizinle birlikte kayar ve siparişinizin tamamını tek cümlede
            söyler: kaç kart, hangi ölçü, hangi tasarım, hangi biçim. İndirmeden önce onu okuyun;
            matbaanın eline geçecek olan o cümledir.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Tek kart: <strong>Yazdır</strong> kartı yeni bir sekmede açar, doğrudan kendi
                yazıcınıza gönderirsiniz; <strong>İndir</strong> dosyayı kaydeder.
            </li>
            <li>
                Birden çok kart: her kart ayrı bir dosya olacak şekilde tek bir zip iner ve dosya
                adı masanın adıdır. Matbaanın istediği biçim budur. Bir arşivde en fazla 48 kart
                olur; daha fazlaysa ekran bunu söyler, kalanı ikinci bir partide basarsınız.
            </li>
            <li>
                <strong>Kesilecek tabaka (PDF)</strong> ayrı bir iştir ve birden çok kodunuz
                olduğunda çizilir: sayfa başına 12 kart ve kesme çizgileri, evde kesmek için. Kendi
                yerleşimi vardır — yukarıda seçtiğiniz ölçüyü ve tasarımı taşımaz.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-areas" aria-labelledby="help-cards-areas-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-areas-heading" class="text-2xl font-bold">Bahçe, üst kat, teras</h2>
        <p class="text-fg-secondary">
            Toplu üretim bölümlerinizi Area 1, Area 2 diye adlandırır; bunlar birer yer tutucudur,
            ad değil. <strong>Kod yönetimi ve gelişmiş baskı</strong> bölümünü açın,
            <strong>Salonunuzdaki bölümler</strong> listesini bulun ve ekibinizin ağzından çıktığı
            gibi yazın. Adı yazıp <strong>Kaydet</strong> deyin.
        </p>
        <p class="text-fg-secondary">
            Bu iki dakika şuna değer: sonradan seçtiğiniz şey o addır. Yağmurlu bir haftadan sonra
            bahçenin kartlarını yenilemek, <strong>Bir bölge</strong> deyip listede "bahçe"yi
            görmektir — Area 1 ile Area 3'ten hangisinin dışarısı olduğunu tahmin etmek değil.
        </p>
        <p class="text-fg-secondary">
            Bölümü yeniden adlandırmak basılı hiçbir kartı bozmaz. Ad sizin okumanız içindir;
            karttaki adres ondan yapılmış değildir.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-manage" aria-labelledby="help-cards-manage-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-manage-heading" class="text-2xl font-bold">Kartlar masaya konduktan sonra</h2>
        <p class="text-fg-secondary">
            Bir kez bastırın. Yeni bir menü yayınlamak kodun nereyi gösterdiğini değiştirmez; yani
            bir fiyat değişikliği size yeniden baskıya mal olmaz.
        </p>
        <p class="text-fg-secondary">
            Gerisi <strong>Kod yönetimi ve gelişmiş baskı</strong> bölümündedir.
            <strong>Devre dışı bırak</strong> bir kodun menünüzü açmasını durdurur — kartın adresi
            değişmez ve <strong>Yeniden etkinleştir</strong> onu hiçbir şey yeniden bastırmadan geri
            getirir. <strong>Başka bir şubeye taşı</strong> ise elinizdeki kartı başka bir şubeye
            yönlendirir; kart kaybolduğunda değil, masa taşındığında istediğiniz şey budur.
        </p>
        <p class="text-fg-secondary">
            Aynı bölümde <strong>Kodun ham dosyasını indir (PNG, SVG, PDF)</strong> durur: etrafında
            kart olmadan kodun kendisi — menü panosuna ya da kendi afişine koyacak bir tasarımcı
            için.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Önünüzde başka bir şey mi var?
        <a class="site-inline-action" href="/contact">Bize yazın</a>, ya da
        <a class="site-inline-action" href="/help">ilk 15 dakikanıza dönün</a>.
    </p>
    </div>
</main>
