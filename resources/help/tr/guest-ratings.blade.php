@section('title', 'Misafir bir yemeğe kötü puan verdi. Silebilir miyim?')
@section('description', 'Hayır — ve bu bilinçli. Yapabileceğiniz şey yanıt vermek; yanıtınız misafirin kendi ekranında yemeğin altında görünür.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Misafir bir yemeğe kötü puan verdi. Silebilir miyim?',
        'prologueLead' => 'Hayır ve bu bilinçli. Yapabileceğiniz şey yanıt vermek — yanıtınız misafirin kendi ekranında, yemeğin altında görünür.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Restoranın silebildiği bir ortalama, ölçüm değil REKLAMDIR — ve misafirler ikisinin
        farkını bilir. Puan onlarındır. Yanıt sizin.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-ratings-reply" aria-labelledby="help-ratings-reply-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-reply-heading" class="text-2xl font-bold">Bir puana yanıt vermek</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Puanlar</strong> ekranını açın, şubeyi ve menüyü seçin.</li>
            <li>
                Yemeği bulun. Puanını ve <strong>Şimdiye kadar verilen oy</strong> sayısını
                görürsünüz.
            </li>
            <li>
                <strong>Yanıtınız</strong> altına yazın. Ekran bunu açıkça söylüyor:
                <em>misafirler bunu kendi ekranlarında yemeğin altında okur.</em>
            </li>
            <li><strong>Yanıtı yayımla</strong> düğmesine basın.</li>
        </ol>
        <p class="text-fg-secondary">
            İfadeden vazgeçtiniz mi? <strong>Yanıtı güncelle</strong> onu değiştirir,
            <strong>Yanıtı geri çek</strong> kaldırır. Misafirler yanıtı
            <strong>Restoranın yanıtı</strong> etiketiyle görür; kimse sizin cevabınızı başka
            bir misafirinkiyle karıştırmaz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-threshold" aria-labelledby="help-ratings-threshold-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-threshold-heading" class="text-2xl font-bold">Bazı yemeklerde neden sayı yok</h2>
        <p class="text-fg-secondary">
            Yalnız birkaç oy almış bir yemek, puan göstermek yerine
            <strong>Henüz yeterli puan yok</strong> der. Birkaç misafir bir hüküm değildir ve
            onların üzerine kurulan bir sayı, misafirlerinizin hiç söylemediği bir şeyi
            söylerdi.
        </p>
        <p class="text-fg-secondary">
            Puanın görünmesi için iki şeyin birden doğru olması gerekir: <em>yeterince kişi</em>
            oy vermiş olmalı ve o oylar hâlâ ağırlık taşıyacak kadar <em>yeni</em> olmalı.
            İkincisi kulağa geldiğinden önemlidir — o olmasaydı iki yıldır kimsenin sipariş
            etmediği bir yemek, eski puanını sonsuza kadar ekranda tutardı.
        </p>
        <p class="text-fg-secondary">
            Eşiğin altında sıfır yıldız GÖRMEZSİNİZ. Sıfır bir ÖLÇÜMDÜR ve bilinmeyenin yerine
            geçemez: sıfır yazmak, hiç oy almamış bir yemeği kötü yemek gibi göstermek olurdu.
        </p>
        <p class="text-fg-secondary">
            Ekran ayrıca sayıları üreten <strong>Puanlama yöntemi</strong>ni ve ne zaman
            <strong>Hesaplandı</strong>ğını yazar. Bir puan oynadığında bu size, oynamasının
            yeni misafirler oy verdiği için mi yoksa puanlama yönteminin değişmesi yüzünden mi
            olduğunu söyler.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-limits" aria-labelledby="help-ratings-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-limits-heading" class="text-2xl font-bold">Burada yapamayacaklarınız</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Bir puanı silemezsiniz, bir puanı gizleyemezsiniz.</em> Bunun düğmesi
                hiçbir yerde, hiçbir planda yok.
            </li>
            <li>
                <em>Panelden puan ekleyemezsiniz.</em> Bir puan yalnızca birileri
                masalarınızdan birindeki kodu okutup orada bıraktığı için vardır.
            </li>
            <li>
                <em>Kimin puan verdiğini göremezsiniz.</em> Misafirler puan vermek için giriş
                yapmaz; gösterilecek bir ad yoktur.
            </li>
            <li>
                <em>Puanlar her rolün kapsamında değildir.</em> Ekran
                <strong>Rolünüz puanları kapsamıyor</strong> diyorsa çalışma alanı sahibine
                söyleyin — bu bir arıza değil, rolün işini yapmasıdır.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-changed" aria-labelledby="help-ratings-changed-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-changed-heading" class="text-2xl font-bold">Puan, değiştirdiğiniz bir yemekle ilgiliyse</h2>
        <p class="text-fg-secondary">
            Puan yemeğe aittir ve fiyat değişikliklerinde, yeni fotoğraflarda yemekle birlikte
            kalır. Bir yemeği gerçekten başka bir yemekle değiştirdiyseniz eskisinin adını
            değiştirmek yerine <strong>Menü</strong> ekranına YENİ bir ürün olarak ekleyin —
            yeni yemek hiç oyu olmadan başlar, ki dürüst başlangıç noktası budur.
        </p>
        <p class="text-fg-secondary">
            Bir puan bulunduğunuz yerde kanunu çiğniyorsa — bir tehdit ya da birinin kişisel
            verisi — bu bir ürün ayarı değildir.
            <a class="site-inline-action" href="/contact">Bize yazın</a>, hangi yemek ve
            yaklaşık ne zaman olduğunu söyleyin.
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
