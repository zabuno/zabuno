@section('title', 'Misafirlerim için neden hiçbir şey değişmedi?')
@section('description', 'Düzenlemek taslağı kaydeder. Siz yeniden yayınlayana kadar misafir en son yayınladığınız sürümü görür.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Bu makalenin okuru KEŞFETMİYOR:
         menüsünü düzeltmiş, kaydetmiş ve masadaki misafirde hiçbir şeyin
         değişmediğini görmüştür. Başlık bandın İÇİNDE; sayfada ikinci bir
         h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Misafirlerim için neden hiçbir şey değişmedi?',
        'prologueLead' => 'Çünkü düzenlemek taslağı kaydeder. Menünüz masaya siz yayınladığınızda ulaşır — aşağıda adı geçen her ekran ve her düğme BUGÜN var.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Hiçbir şey kaybolmadı, hiçbir şey bozulmadı. Değişikliğiniz kaydedildi — yalnız sizin
        gördüğünüz taslağınıza.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-publication-two-menus" aria-labelledby="help-publication-two-menus-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-two-menus-heading" class="text-2xl font-bold">Bir değil, iki menünüz var</h2>
        <p class="text-fg-secondary">
            Biri <strong>Taslak</strong>: düzenlediğiniz menü. Değiştirdiğiniz her fiyat,
            düzelttiğiniz her ad, eklediğiniz her fotoğraf kaydettiğiniz anda oraya iner.
        </p>
        <p class="text-fg-secondary">
            Diğeri <strong>Yayında</strong> olan: en son yayınladığınız anda alınmış DONMUŞ bir
            kopya. Misafir masadaki kodu okuttuğunda gördüğü şey o kopyadır ve kendiliğinden
            değişmez.
        </p>
        <p class="text-fg-secondary">
            Bu bilerek böyle ve sebebi mutfağınkiyle aynı: yarım tabak servise çıkmaz. Bütün bir
            fiyat listesini ya da bütün bir bölümün fotoğraflarını, hiçbir misafir yarısını
            görmeden bir öğleden sonra boyunca düzeltebilirsiniz. Aynı kural yayın başarısız
            olduğunda da korur: misafir en son yayınladığınız menüyü görmeye devam eder, masada
            hiçbir şey bozulmaz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-publish" aria-labelledby="help-publication-publish-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-publish-heading" class="text-2xl font-bold">Taslağınızı masaya gönderin</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Çalışma alanında <strong>Yayın</strong> ekranını açın.</li>
            <li>
                Önce <strong>Yayınlanmayı bekleyen değişiklikler</strong> bölümünü okuyun. Orada
                misafiriniz için GERÇEKTEN ne değişeceği yazar: bir fiyat, bir ad, eklenen bir
                ürün, gizlenen bir ürün. Bekleyen bir şey yoksa misafirleriniz zaten son hâlini
                görüyor demektir.
            </li>
            <li>
                <strong>Yayın hazırlık listesi</strong>ne bakın. Her satır <strong>Hazır</strong>
                demeli. <strong>Eksik var</strong> diyen satırın yanında <strong>Düzelt</strong>
                durur ve sizi doğrudan düzeltebileceğiniz ekrana götürür.
            </li>
            <li><strong>Yayın kontrol listesini gözden geçirdim</strong> kutusunu işaretleyin.</li>
            <li><strong>Yayınla</strong> deyin.</li>
        </ol>
        <p class="text-fg-secondary">
            Kutu da düğme de ancak liste temizlendiğinde uyanır. Bu ekranın zorluk çıkarması
            değildir: adsız bir kategori ya da fiyatsız bir ürün, aksi hâlde gerçek bir masaya
            o hâliyle giderdi.
        </p>
        <p class="text-fg-secondary">
            Yayın geçtiğinde sürüm numarası artar ve menüyü açan bir sonraki misafir yeni sürümü
            alır. Basılı kodlarınıza dokunulmaz — işaret ettikleri adres siz yayınladınız diye
            değişmez, yani yayınlamak size hiçbir zaman yeni bir baskıya mal olmaz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-preview" aria-labelledby="help-publication-preview-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-preview-heading" class="text-2xl font-bold">Misafirinizden önce siz bakın</h2>
        <p class="text-fg-secondary">
            Aynı ekrandaki <strong>Bir misafirin göreceği şey bu</strong> bölümü taslağınızı dar
            bir sütunda, telefonun okuduğu genişlikte gösterir — uzun bir ürün adının tuhaf
            kırıldığını ya da bir fiyatın yanlış yere düştüğünü orada fark edersiniz.
        </p>
        <p class="text-fg-secondary">
            Elinize almak için <strong>Önizleme bağlantısını aç</strong> deyip kendi telefonunuzda
            açın. O bağlantı 15 dakika çalışır ve arama motorlarına kapalıdır. Misafirlerinizin
            adresi değildir: siz taslağınıza bakarken masadaki basılı kod yayındaki menüyü
            göstermeye devam eder.
        </p>
        <p class="text-fg-secondary">
            O bağlantıyı asla bastırmayın ve kimseye göndermeyin. Süresi dolar; onunla bastırılmış
            bir kart aynı gün ölü kâğıda dönerdi. Misafirinizin aldığı tek adres karekodlarınızın
            gösterdiği adrestir.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-schedule" aria-labelledby="help-publication-schedule-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-schedule-heading" class="text-2xl font-bold">Bilerek sonraya bırakın</h2>
        <p class="text-fg-secondary">
            Yeni fiyatlar pazartesiden mi geçerli? Pazartesi masanızın başında olmanız gerekmiyor.
            <strong>Yayını zamanla</strong> hazır saatler sunar — <strong>Bu gece 03:00</strong>,
            <strong>Yarın 09:00</strong>, <strong>Pazartesi 09:00</strong> — ve bu saatler bizim
            değil, ŞUBENİZİN kendi saat dilimindedir.
        </p>
        <p class="text-fg-secondary">
            Zamanlanmış bir yayın da yayındır: sonraki sürüm numarasını alır ve basılı karekodunuz
            aynı kalır. Ayrıca şu an gördüğünüzü dondurur; bu yüzden önce hazırlık listesini
            bitirin — bitirmediğiniz sürece ekran size saat sunmak yerine bunu söyler.
        </p>
        <p class="text-fg-secondary">
            Vakti gelmeden fikrinizi mi değiştirdiniz? <strong>Bu zamanlamayı iptal et</strong>
            deyin. Zamanlanmış bir yayın çıkmazsa da ekran bunu açıkça yazar: menü değişmedi,
            misafirleriniz hâlâ önceki sürümü görüyor, şimdi yayınlayabilir ya da yeniden
            zamanlayabilirsiniz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-undo" aria-labelledby="help-publication-undo-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-undo-heading" class="text-2xl font-bold">Yanlış olanı yayınladıysanız</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Yayın</strong> ekranını açıp <strong>Yayınlanmış sürümler</strong> listesini bulun.</li>
            <li>Geri istediğiniz sürümü seçip <strong>Bu sürüme dön</strong> deyin.</li>
        </ol>
        <p class="text-fg-secondary">
            Geri alma da bir yayındır: yeni sürüm numarası alır, QR aynı kalır. Hiçbir şey
            silinmez — pişman olduğunuz sürüm de listede durur, ilk kararınız doğruymuş diye
            düşünürseniz ona da aynı kolaylıkla dönersiniz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-without-publishing" aria-labelledby="help-publication-without-publishing-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-without-publishing-heading" class="text-2xl font-bold">Yayınlamadan misafire ulaşan tek şey</h2>
        <p class="text-fg-secondary">
            <strong>Menü</strong> ekranında ürün satırındaki <strong>Tükendi</strong>. Ürün,
            misafirin menüsünde fiyatıyla birlikte kalır, bugün alınamayacağı yazar ve işaret
            ertesi gün kendiliğinden düşer.
        </p>
        <p class="text-fg-secondary">
            Böyle olmasının sebebini zaten biliyorsunuz: balık servisin ortasında biter. O anda
            sizi yayınlamaya zorlamak hem yavaş hem riskli olurdu, çünkü taslağınızda yarım
            bıraktığınız bir fiyat düzenlemesi duruyor olabilir.
        </p>
        <p class="text-fg-secondary">
            Geri kalan her şey donmuş kopyanın parçasıdır ve yayın ister: fiyatlar, ürün adları,
            açıklamalar, kategoriler, menünün sırası, ürün fotoğrafları, logonuz ve marka adınız,
            adresiniz, telefonunuz.
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
