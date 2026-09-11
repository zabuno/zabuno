@section('title', 'Restoranımın adını ve adresini nereye yazarım?')
@section('description', 'Adınız marka formuna, adresiniz şube formuna gider; menü de şubeye aittir.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Restoranımın adını ve adresini nereye yazarım?',
        'prologueLead' => 'İki ayrı yere ve bu ayrım bilinçli: misafirin OKUDUĞU ad marka formunda, YÜRÜDÜĞÜ adres şube formundadır.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Markayı bir kez doldurursunuz; hizmet verdiğiniz her yer için bir şube eklersiniz.
        Bu ayrım evrak işi değil: ikinci şubeyi açtığınızda aynı menüyü kullanır ama kendi
        adresi, kendi masaları ve kendi basılı karekodları olur.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-address-name" aria-labelledby="help-address-name-heading" class="flex flex-col gap-3">
        <h2 id="help-address-name-heading" class="text-2xl font-bold">Misafirin okuduğu ad</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Ana ekranda <strong>Restoranınıza ad verin</strong> adımını açın.</li>
            <li>
                <strong>Markanızı oluşturun</strong> ekranında <strong>Marka adı</strong>
                alanını doldurun — bu ad masa kartlarınıza basılır ve menünüzün en üstünde
                görünür.
            </li>
            <li>
                <strong>Ana pazar</strong> seçin. Zabuno saat diliminizi ve
                <strong>Para birimi</strong> değerini bundan belirler; ikisini de sonradan
                değiştirebilirsiniz.
            </li>
            <li>Kaydedin.</li>
        </ol>
        <p class="text-fg-secondary">
            Buradaki hiçbir şey nihai değil. Ekranın kendisi de bunu söylüyor: sonraki
            adımlarda şube ve menü ekleyebilir, bunların hepsini daha sonra
            değiştirebilirsiniz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-location" aria-labelledby="help-address-location-heading" class="flex flex-col gap-3">
        <h2 id="help-address-location-heading" class="text-2xl font-bold">Misafirin karekodu okuttuğu yer</h2>
        <p class="text-fg-secondary">
            Şube gerçek bir adrestir: sokağı, çalışma saatlerini ve menünüzün sunulduğu saat
            dilimini taşır. Masalarınız ve bastırdığınız karekodlar ona aittir.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Ana ekrana dönüp <strong>Şubenizi ekleyin</strong> adımını açın.</li>
            <li>
                <strong>Şubenizi oluşturun</strong> ekranında dört alan zorunlu:
                <strong>Görünen ad</strong>, ülke, <strong>Şehir</strong> ve
                <strong>Adres satırı 1</strong>. Posta kodu ve ikinci adres satırı isteğe
                bağlıdır.
            </li>
            <li>
                Menünüzün ne zaman açık olduğunuzu söylemesini istiyorsanız
                <strong>Bu şubenin çalışma saatleri var</strong> kutusunu işaretleyin.
                Kapanış saati açılıştan erkense ERTESİ GÜN demektir — 18.00–02.00, gece
                ikide kapanır.
            </li>
            <li><strong>Oluştur</strong> düğmesine basın.</li>
        </ol>
        <p class="text-fg-secondary">
            İkinci şubeyi mi açtınız? <strong>Şubeler</strong> ekranını açıp
            <strong>Şube ekle</strong> deyin. Her şubenin kendi masaları ve kendi karekodları
            olur; menü ortaktır.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-limits" aria-labelledby="help-address-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-address-limits-heading" class="text-2xl font-bold">Burada yapamayacaklarınız</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Şubeniz olmadan menüye başlayamazsınız.</em> <strong>Menü</strong> ekranı
                bunu size söyler ve yerine şube formunu önerir. Menü bir şubeye, şube de bir
                markaya aittir.
            </li>
            <li>
                <em>Panel adresinizi değiştiremezsiniz.</em> <strong>Ayarlar</strong> →
                <strong>Çalışma alanı</strong> altında <strong>Panel adresi</strong> görünür
                ama kilitlidir: ekibinizin kaydettiği her bağlantı ona bağlıdır.
            </li>
            <li>
                <em>Adres bir harita işareti değildir.</em> Zabuno yazdığınızı saklar ve karta
                basar; yeri aramaz, haritaya yerleştirmez.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-stuck" aria-labelledby="help-address-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-address-stuck-heading" class="text-2xl font-bold">Yanlış yazdıysanız</h2>
        <p class="text-fg-secondary">
            Burada yazdığınız hiçbir şey siz yayınlamadan misafire ulaşmaz; yani ilk gün
            yapılan bir yazım hatasının size maliyeti yoktur. <strong>Şubeler</strong>
            ekranını açın, kartın üzerindeki <strong>Düzenle</strong> ile düzeltin. Siz
            yeniden yayınlayana kadar misafirler son yayınladığınız menüyü görmeye devam eder.
        </p>
        <p class="text-fg-secondary">
            Form kaydetmeyi reddediyor ve sebebini göremiyorsanız, eksik alanın adı boş
            bıraktığınız kutunun altında yazar.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Başka bir şey mi engelliyor?
        <a class="site-inline-action" href="/contact">Bize yazın</a> ya da
        <a class="site-inline-action" href="/help">ilk 15 dakikanıza dönün</a>.
    </p>
    </div>
</main>
