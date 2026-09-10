@section('title', 'İlk 15 dakikanız')
@section('description', 'Menünüzü aktarın, karekodları bastırın, fiyat değiştirin.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12).

         Bu bir makale sayfası: buraya gelen kişi keşfetmiyor, CEVAP ARIYOR.
         Bandın markup'ı ortak parçadan geliyor (`public.partials.prologue`) —
         yani sahne bir yerde değişince burası da değişir; tekrar eden tek şey
         "bandı istiyorum" cümlesi, bandın kendisi değil.

         `calm`: tuval yok, düzlem yok, animasyon yok. Başlık ve giriş cümlesi
         bandın İÇİNE taşındı; sayfada ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'İlk 15 dakikanız',
        'prologueLead' => 'Her restoranın ilk gün yaptığı üç iş. Her biri BUGÜN var olan bir ekranı anlatır; burada planlanan ya da yakında gelecek hiçbir şey yok.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <section id="help-import" aria-labelledby="help-import-heading" class="flex flex-col gap-3">
        <h2 id="help-import-heading" class="text-2xl font-bold">Menünüzü aktarın</h2>
        <p class="text-fg-secondary">
            60 ürünü tek tek yazmanız gerekmiyor. Menü ekranı bir CSV dosyası alır ve hepsini
            tek işlemde oluşturur.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Çalışma alanında <strong>Menü</strong> ekranını açın.</li>
            <li>
                Menü boşken bile bir kez <strong>Menüyü indir (CSV)</strong> deyin; doğru
                sütunları taşıyan bir dosya iner:
                <code class="rounded bg-surface px-1">category, product, price, currency, allergens, description, visible</code>.
            </li>
            <li>Dosyayı Excel'de doldurun. Alerjenleri noktalı virgülle ayırın (<code>süt;gluten</code>).</li>
            <li>Geri dönüp <strong>CSV menü içe aktar</strong> ile yükleyin.</li>
        </ol>
        <p class="text-fg-secondary">
            Okunamayan satırlar dosyadaki SATIR NUMARASIYLA listelenir ve geçerli satırlar yine
            de aktarılır — yalnız hatalı olanı düzeltirsiniz. Siz yayınlayana kadar hiçbir şey
            misafire ulaşmaz.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-qr" aria-labelledby="help-qr-heading" class="flex flex-col gap-3">
        <h2 id="help-qr-heading" class="text-2xl font-bold">Karekodlarınızı bastırın</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Önce menüyü yayınlayın — karekodun işaret edeceği bir şey olmalı.</li>
            <li>Çalışma alanında <strong>Karekodlar</strong> ekranını açıp masalarınızın kodlarını oluşturun; bütün bir salonun masaları için toplu seçenek var.</li>
            <li>Kartları matbaa için PDF, tasarımcı için SVG olarak indirin.</li>
        </ol>
        <p class="text-fg-secondary">
            Bir kez bastırın. Menüyü sonradan yeniden düzenlerseniz basılı kod çalışmaya devam
            eder: nereyi gösterdiğini taşıyabilir, yanlışlıkla kapattığınız bir kodu geri
            açabilirsiniz. Masadaki kâğıt çöpe dönüşmez.
        </p>
        <p class="text-fg-secondary">
            Kırk masa, ölçüler, tasarımlar ve bölümler?
            <a class="site-inline-action" href="/help/table-cards-and-areas">Masa kartı ekranının tamamı burada</a>.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-price" aria-labelledby="help-price-heading" class="flex flex-col gap-3">
        <h2 id="help-price-heading" class="text-2xl font-bold">Fiyat değiştirin</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Menü</strong> ekranını açın, ürünü bulun, <strong>Fiyat</strong> deyin.</li>
            <li><strong>Yayın</strong> ekranını açıp yayınlayın.</li>
        </ol>
        <p class="text-fg-secondary">
            Unutulan adım ikincisidir. Düzenleme TASLAĞI değiştirir; siz yeniden yayınlayana
            kadar misafir son yayınlanan sürümü görmeye devam eder. Bu bilerek böyle: bütün bir
            fiyat listesini, hiçbir misafir yarısını görmeden düzeltebilirsiniz.
        </p>
        <p class="text-fg-secondary">
            Yanlış listeyi mi yayınladınız? <strong>Yayın</strong> ekranında
            <strong>Yayınlanmış sürümler</strong> altından istediğiniz sürümü bulup ona dönün.
            Hiçbir şey silinmez ve basılı kodlarınıza dokunulmaz.
        </p>
        <p class="text-fg-secondary">
            Kaydettiniz ama misafiriniz hâlâ eski fiyatı mı görüyor?
            <a class="site-inline-action" href="/help/nothing-changed-for-my-guests">Gördüğü şey taslak değil, yayındaki menü — nasıl yayınlanır</a>.
        </p>
        <p class="text-fg-secondary">
            Bu akşam bir şey mi bitti? Ürün satırında <strong>Tükendi</strong> deyin. Ürün
            fiyatıyla birlikte menüde kalır, bugün alınamayacağı yazar ve işaret ertesi gün
            kendiliğinden düşer — yayınlamanız gerekmez.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-more" aria-labelledby="help-more-heading" class="flex flex-col gap-3">
        <h2 id="help-more-heading" class="text-2xl font-bold">Bu üçü bittiğinde</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <a class="site-inline-action" href="/help/nothing-changed-for-my-guests">Misafirlerim için neden hiçbir şey değişmedi?</a>
                — yukarıdaki adımın cevabı: düzenlemek taslağı kaydeder, menü masaya siz
                yayınladığınızda ulaşır.
            </li>
            <li>
                <a class="site-inline-action" href="/help/a-photo-on-a-dish">Bir ürüne fotoğraf nasıl eklerim?</a>
                — fotoğraf önce Görseller ekranına, sonra ürüne gider; son adım yayınlamaktır.
            </li>
            <li>
                <a class="site-inline-action" href="/help/table-cards-and-areas">Masalarım için kart nasıl bastırırım?</a>
                — masa başına bir kod, dört hazır ölçü ve bütün restoranı yeniden bastırmadan
                yalnız bahçeyi yenilemenizi sağlayan bölümler.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Önünüzde başka bir şey mi var?
        <a class="site-inline-action" href="/contact">Bize yazın</a>.
    </p>
    </div>
</main>
