@section('title', 'Çalışanlarımı nasıl eklerim, her biri neler yapabilir?')
@section('description', 'Kişileri e-posta ile davet edip birer rol verirsiniz. Herkes yalnızca işinin gerektirdiğini görür.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Çalışanlarımı nasıl eklerim, her biri neler yapabilir?',
        'prologueLead' => 'E-posta ile davet eder, bir rol seçersiniz. Rol ne göreceğini belirler — ve ekranın kendisi her rolün neyi yapıp yapamadığını listeler.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Bir yemeği “bugün tükendi” diye işaretlemek için kimsenin fiyatlarınıza emanet
        edilmesi gerekmez; menüye yardım ediyor diye kimse faturanızı görmez. Rollerin bütün
        amacı budur.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-team-invite" aria-labelledby="help-team-invite-heading" class="flex flex-col gap-3">
        <h2 id="help-team-invite-heading" class="text-2xl font-bold">Birini davet etmek</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Ekip</strong> ekranını açın.</li>
            <li><strong>E-posta ile davet et</strong> altına adresini yazın.</li>
            <li>
                Bir <strong>Rol</strong> seçin. Her rolün altında ne yapabileceğini söyleyen
                tek bir satır vardır.
            </li>
            <li><strong>Davet et</strong> düğmesine basın.</li>
        </ol>
        <p class="text-fg-secondary">
            Kişiye bağlantı içeren bir e-posta gider. O bağlantıyı kullanana kadar davet
            <strong>Bekleyen davetler</strong> altında durur; <strong>Tekrar gönder</strong>
            ya da <strong>Daveti iptal et</strong> diyebilirsiniz. Tekrar göndermek bağlantıyı
            DEĞİŞTİRİR — yalnız en yeni e-posta çalışır.
        </p>
        <p class="text-fg-secondary">
            İlk sırada önerilen rol bilerek dar rollerden biridir. Aceleniz varsa okumadan
            kabul edeceğiniz rol odur; o yüzden en geniş olan değildir.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-roles" aria-labelledby="help-team-roles-heading" class="flex flex-col gap-3">
        <h2 id="help-team-roles-heading" class="text-2xl font-bold">Hangi rol ne yapabilir</h2>
        <p class="text-fg-secondary">
            Aynı liste ekranda da var: <strong>Hangi rol ne yapabilir?</strong> Her satır
            <strong>Bu rolün tam olarak neleri yapıp yapamayacağını görün</strong> ile açılır —
            ve o bölme <strong>Yapamaz</strong> tarafını da listeler, ki genelde gerçekten
            ihtiyacınız olan yarı odur.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Sahip</strong> — her şey: fatura, ekip, yayınlama. Yani siz.
            </li>
            <li>
                <strong>Yönetici</strong> — menü, karekodlar, yayınlama. Faturaya dokunamaz.
                Salonu çeviren kişinin ne ödediğinizi görmeden çalışabileceği rol.
            </li>
            <li>
                <strong>Editör</strong> — ürünler, fiyatlar ve fotoğraflar. Yayınlayamaz; yani
                yazdığı hiçbir şey, bir başkası onay vermeden masaya ulaşmaz.
            </li>
            <li>
                <strong>Mutfak</strong> — alerjenler ve “bugün tükendi”. Başka bir şey görmez.
                Ocağın yanında duran telefon için doğru rol: balığın bittiğini bilen kişi bunu
                söyleyebilmeli, ama menüyü yeniden fiyatlandıramamalı.
            </li>
        </ul>
        <p class="text-fg-secondary">
            Kişinin işini yapmasına yeten EN DAR rolü seçin. Sonradan yukarı almak, satırındaki
            tek bir değişikliktir.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-limits" aria-labelledby="help-team-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-team-limits-heading" class="text-2xl font-bold">Burada yapamayacaklarınız</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Birini Sahip olarak davet edemezsiniz.</em> Ekran bunu açıkça söylüyor:
                <strong>Sahiplik davetle verilmez, devredilir.</strong> Restoranı devretmek
                ayrı bir işlemdir — o kişinin satırındaki
                <strong>Sahipliği devret</strong> — ve kendi onayı vardır, çünkü ondan sonra
                çalışma alanı onundur.
            </li>
            <li>
                <em>Düğmeyi gizlemek koruma değildir.</em> Bir rolün yapamadığı şey ekrandan
                çıkarılmakla kalmaz, SUNUCUDA reddedilir. Menü düzenleme adresine bir şekilde
                ulaşan Mutfak telefonu yine geri çevrilir.
            </li>
            <li>
                <em>Birini Üye olarak davet edemezsiniz.</em> O rol hâlâ var ama yalnız çok
                önce açılmış hesaplar için; salt okunurdur ve hiçbir yeni davet onu önermez.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-stuck" aria-labelledby="help-team-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-team-stuck-heading" class="text-2xl font-bold">Davet hiç ulaşmadıysa</h2>
        <p class="text-fg-secondary">
            Önce <strong>Bekleyen davetler</strong> listesine bakın: davet hâlâ oradaysa kişi
            bağlantıyı henüz açmamıştır. <strong>Tekrar gönder</strong> deyin — ve eski
            e-postanın artık çalışmadığını kendisine söyleyin.
        </p>
        <p class="text-fg-secondary">
            Satırda <strong>Bu e-postanın hiç gönderilip gönderilmediğini söyleyemiyoruz</strong>
            yazıyorsa, bu bir tahminin gerçek gibi sunulması değildir: teslim sonucu gerçekten
            kaydedilmemiştir. Tekrar gönderin; ikincisi de bilinmiyorsa bize haber verin —
            o bizim tarafımızdaki bir sorundur, ekrandan düzeltebileceğiniz bir şey değil.
        </p>
        <p class="text-fg-secondary">
            Artık orada olmaması gereken biri mi var? Satırındaki <strong>Çıkar</strong> ile
            kaldırın.
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
