@section('title', 'Bir ürüne fotoğraf nasıl eklerim?')
@section('description', 'Fotoğrafı önce Görseller ekranına yükleyin, sonra menüden ürüne bağlayın.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Bir ürüne fotoğraf nasıl eklerim?',
        'prologueLead' => 'İki adım ve sırası önemli: fotoğraf önce Görseller ekranına gider, ürüne sonra bağlanır. Burada adı geçen her ekran BUGÜN var.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Fotoğraflar tek bir yerde durur: aynı görseli ikinci kez yüklemeden iki yerde
        kullanabilirsiniz ve menüye küçük gelen bir fotoğraf, misafir görmeden ÖNCE fark
        edilir — sonra değil.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-photo-upload" aria-labelledby="help-photo-upload-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-upload-heading" class="text-2xl font-bold">Birinci adım — fotoğrafı içeri alın</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Çalışma alanında <strong>Görseller</strong> ekranını açın.</li>
            <li>
                <strong>Fotoğraf ekle</strong> altında
                <strong>Bir görseli buraya bırakın ya da dosya seçin</strong> yazar.
                Telefondaysanız <strong>Fotoğraf çek</strong> de var.
            </li>
            <li>
                <strong>Bu görsel nerede kullanılacak?</strong> sorusuna cevap verin: bir ürün
                için <strong>Ürün görseli</strong>. Menü ekranı aynı yeri
                <strong>Liste/kart/detay öğesi</strong> diye anar; ikisi aynı yerdir. Seçtikten
                sonra ekran, o yerin kabul ettiği en küçük boyutu ve çerçeve oranını size
                söyler.
            </li>
            <li>
                <strong>Alt metin</strong> yazın: görseli göremeyen bir misafir için kısa bir
                tarif, örneğin “tahta tabakta ızgara kuzu pirzola”. Ürün fotoğrafında bu alan
                zorunludur.
            </li>
            <li>
                <strong>Yükle</strong> deyin ve fotoğraf <strong>Hazır</strong> yazana kadar
                bekleyin.
            </li>
        </ol>
        <p class="text-fg-secondary">
            <strong>İşleniyor</strong> yazdığı sürece fotoğraf kontrol edilip yeniden
            boyutlandırılıyordur ve henüz kullanılamaz — <strong>Hazır</strong> olmadan hiçbir
            ürün onu size önermez.
        </p>
        <p class="text-fg-secondary">
            Dosya türleri: yükleme alanı JPEG, PNG ve WebP adlarını yazar; aynı ekrandaki
            <strong>Desteklenen türler</strong> başlığı ise Zabuno'nun aldığı her türü ve o
            dosyaya ne olacağını sıralar. Telefonunuzun ne kaydettiğini tahmin etmek yerine o
            tabloya bakın. Gereğinden büyük bir fotoğraf önce KENDİ CİHAZINIZDA küçültülür —
            ekran <strong>Telefonunuzda küçültüldü</strong> der ve ne gönderileceğini gösterir;
            siz <strong>Yükle</strong> deyene kadar cihazınızdan hiçbir şey çıkmaz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-attach" aria-labelledby="help-photo-attach-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-attach-heading" class="text-2xl font-bold">İkinci adım — ürüne bağlayın</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Menü</strong> ekranını açıp ürünü bulun. Görseli olmayan bir üründe
                <strong>Fotoğraf yok</strong> yazar.
            </li>
            <li>O satırda <strong>Fotoğraf ve metin</strong> deyin.</li>
            <li>
                Fotoğrafınızı seçin, misafirin ürün adının altında okuyacağı satırı yazın ve
                <strong>Sunumu kaydet</strong> deyin.
            </li>
        </ol>
        <p class="text-fg-secondary">
            Logonuz da aynı yoldan geçer: <strong>Görseller</strong> ekranına
            <strong>Logo</strong> olarak yükleyin, sonra <strong>Marka</strong> ekranından
            seçin. Misafir menünüzün üstünde, marka adının yanında görünür.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-publish" aria-labelledby="help-photo-publish-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-publish-heading" class="text-2xl font-bold">Üçüncü adım — unutulan adım</h2>
        <p class="text-fg-secondary">
            Kaydetmek yayınlamak DEĞİLDİR. Fotoğraf şimdi taslağınızdadır; siz yeniden
            yayınlayana kadar misafir en son yayınladığınız sürümü görmeye devam eder. Bu
            bilerek böyle: bütün bir bölümün fotoğrafını, hiçbir misafir yarısını görmeden
            çekip yerleştirebilirsiniz.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Yayın</strong> ekranını açın.</li>
            <li>
                Önce <strong>Telefonda önizle</strong> deyin. Dizüstünde doğru görünen bir
                fotoğraf telefonda yargılanır, çünkü misafiriniz onu orada okur.
            </li>
            <li>
                Sonra <strong>Yayın durumu</strong> bölümünde
                <strong>Yayın kontrol listesini gözden geçirdim</strong> kutusunu
                işaretleyip <strong>Yayınla</strong> deyin.
            </li>
        </ol>
        <p class="text-fg-secondary">
            Önizleme bağlantısı on beş dakika çalışır ve arama motorlarına kapalıdır.
            Misafirlerinizin adresi DEĞİLDİR: basılı karekodlarınız bu sırada yayındaki menüyü
            göstermeye devam eder, yani önizlemek masaya yarım bir menü koymaz.
        </p>
        <p class="text-fg-secondary">
            Yayınladıktan sonra vazgeçtiniz mi? <strong>Yayın</strong> ekranında
            <strong>Yayınlanmış sürümler</strong> altından istediğiniz sürümü bulup ona dönün.
            Geri alma da bir yayındır: yeni sürüm numarası alır, karekod aynı kalır.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-limits" aria-labelledby="help-photo-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-limits-heading" class="text-2xl font-bold">Burada yapamayacağınız şeyler</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Ürünün üstünden yükleme yapamazsınız.</em> Ürün yalnız
                <strong>Görseller</strong> ekranında ZATEN duran ve ZATEN
                <strong>Hazır</strong> olan fotoğrafları önerir. O liste boşsa ekran bunu yazar
                ve size Görseller sayfasının bağlantısını verir — bozuk değildir, hangi adımın
                eksik olduğunu söylüyordur.
            </li>
            <li>
                <em>Küçük bir fotoğraf büyütülmez.</em> Zabuno bir görseli hiçbir zaman
                büyütmez, çünkü büyütülen görsel telefonda bulanık görünür. Fotoğrafınız
                küçükse ekran onu yükleme başlamadan geri çevirir ve gereken boyutu yazar.
            </li>
            <li>
                <em>Yayındaki bir menüde görünen fotoğrafı silemezsiniz.</em> Önce menüden
                çıkması gerekir: onsuz bir menü yayınlayın, sonra silin. Diğer her durumda
                silmek fotoğrafı <strong>Çöp</strong> bölümüne taşır ve geri alınabilir; o
                sırada onu kullanan öğelerde yer tutucu görünür.
            </li>
            <li>
                <em>Zabuno fotoğrafı sizin yerinize üretmez.</em> Burada görsel üretme ya da
                rötuş yoktur; fotoğraf sizindir.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-stuck" aria-labelledby="help-photo-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-stuck-heading" class="text-2xl font-bold">Fotoğraf bir türlü yapışmıyorsa</h2>
        <p class="text-fg-secondary">
            Fotoğraf eklenemezse yazdıklarınız kaybolmaz: ekran açıklamanın kaydedildiğini ama
            fotoğrafın eklenemediğini söyler, yani yalnız fotoğrafı yeniden denersiniz.
        </p>
        <p class="text-fg-secondary">
            Bir fotoğraf <strong>İşleme başarısız</strong> ya da
            <strong>Reddedildi — güvenlik taramasından geçemedi</strong> olarak da dönebilir.
            İkisi de masada yaptığınız bir yanlış değildir; dosya bizim tarafımızdaki bir
            kontrolden geçemedi. Fotoğrafın başka bir kopyasını deneyin, tekrarlıyorsa dosya
            adını bize bildirin.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Önünüzde başka bir şey mi var?
        <a class="site-inline-action" href="/contact">Bize yazın</a> ya da
        <a class="site-inline-action" href="/help">ilk 15 dakikanıza dönün</a>.
    </p>
    </div>
</main>
