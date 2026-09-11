@section('title', 'Planımı nasıl değiştirir ya da iptal ederim, faturam nerede?')
@section('description', 'Parayla ilgili her şey tek ekranda: planınız, iptal düğmesi ve her ödeme için numaralı bir belge.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Planımı nasıl değiştirir ya da iptal ederim, faturam nerede?',
        'prologueLead' => 'Hepsi tek ekranda: Ayarlar → Plan ve fatura. Planınız en üstte, onu değiştiren düğmeler altında, her ödeme de indirebileceğiniz bir belge olarak en aşağıda.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        <strong>Mevcut plan</strong> hangi planda olduğunuzu ve hangi tarihe kadar geçerli
        olduğunu söyler. Buradaki hiçbir şey misafirlerinizin gördüğüne dokunmaz: basılı bir
        kodun arkasındaki menü, bunların öncesinde de sonrasında da yayınladığınız şeyi
        göstermeye devam eder.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-billing-change" aria-labelledby="help-billing-change-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-change-heading" class="text-2xl font-bold">Başka bir plana geçmek</h2>
        <p class="text-fg-secondary">
            Yukarı çıkmak ile aşağı inmek farklı işlerdir, çünkü biri bugün para ister, öteki
            istemez.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Yukarı:</strong> büyük planın ücretini <strong>Abone ol</strong>
                bölümünden ödeyin. Ödeme başarılı olur olmaz başlar — ayın bitmesini
                beklemezsiniz.
            </li>
            <li>
                <strong>Aşağı:</strong> <strong>Daha ucuz plana geç</strong> ve
                <strong>Değişikliği zamanla</strong> deyin. Ücretini zaten ödediğiniz dönem
                bitince yürürlüğe girer. Onaylamadan önce ekran
                <strong>… tarihinde kaybedecekleriniz</strong> listesini adıyla ve tarihiyle
                gösterir.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-cancel" aria-labelledby="help-billing-cancel-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-cancel-heading" class="text-2xl font-bold">İptal etmek</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Aboneliği iptal et</strong> deyin ve size verdiği tarihi okuyun. Planınız
                o tarihe kadar tam olarak etkin kalır; yalnız yenileme durur.
            </li>
            <li>
                <strong>Evet, iptal et</strong> ile onaylayın ya da
                <strong>Aboneliğimi koru</strong> ile geri adım atın.
            </li>
        </ol>
        <p class="text-fg-secondary">
            O tarihten önce fikrinizi mi değiştirdiniz? <strong>İptali geri al</strong> onu geri
            getirir ve ekran bunu açıkça söyler:
            <em>yeniden ödeme istenmeyecek.</em>
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-invoices" aria-labelledby="help-billing-invoices-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-invoices-heading" class="text-2xl font-bold">Faturanız</h2>
        <p class="text-fg-secondary">
            <strong>Faturalar</strong> bölümüne inin. Tahsil edilen her ödeme orada numaralı bir
            belge olarak durur; <strong>PDF indir</strong> muhasebecinizin istediği dosyayı
            verir.
        </p>
        <p class="text-fg-secondary">
            Bir belge hiç düzenlenmez ve hiç silinmez. İade, aslı değiştirilerek değil ayrı bir
            <strong>Alacak dekontu</strong> olarak kaydedilir — numaralandırmayı güvenilir yapan
            şey budur.
        </p>
        <p class="text-fg-secondary">
            Ödeme yapmadan önce <strong>Fatura bilgileri</strong>ni doldurun: şirket adı, vergi
            numarası, vergi dairesi, adres. Bundan önce yapılan bir ödemenin belgesinde satıcı
            alanları boş kalır ve ekran bunu gizlemek yerine size söyler.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-limits" aria-labelledby="help-billing-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-limits-heading" class="text-2xl font-bold">Burada yapamayacaklarınız</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Başlamış bir dönemin parasını geri alamazsınız.</em> İptal, bir SONRAKİ
                ödemeyi durdurur; şu ankini iade etmez.
                <a class="site-inline-action" href="/refund-policy">İptal ve İade Politikası</a>
                da aynı şeyi söyler ve ekran siz onaylamadan önce bunu tekrarlar.
            </li>
            <li>
                <em>Daha ucuz planı satın alarak ona geçemezsiniz.</em> Dönem ortasında o satın
                alma, sizi sessizce düşürmek yerine reddedilir — bunun yerine zamanlanmış
                değişikliği kullanın.
            </li>
            <li>
                <em>Zabuno faturanızı vergi dairesine göndermez.</em> Bağlı bir e-Arşiv ya da
                e-Fatura sağlayıcısı yok. İndirdiğiniz belge gerçek ve numaralıdır, ama onu
                beyan etmek hâlâ muhasebecinizin işidir — ekran bunu, beyan edildiğini
                varsaymanıza izin vermek yerine açıkça söyler.
            </li>
            <li>
                <em>Zabuno kartınızı ne görür ne saklar.</em> Kart bilgileri bizim sayfamızda
                değil, ödeme sağlayıcısının kendi sayfasında yazılır.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-trouble" aria-labelledby="help-billing-trouble-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-trouble-heading" class="text-2xl font-bold">Ödeme başarısız olursa ya da gecikirse</h2>
        <p class="text-fg-secondary">
            Başarısız bir ödeme sizden hiçbir ücret almaz ve sebebini söyler:
            <strong>Ücret alınmadı. Tekrar deneyebilirsiniz.</strong> Hazır olduğunuzda
            <strong>Ödemeye geç</strong> deyin — yarım kalmış bir ödeme için
            <strong>Ödeme sayfasına devam et</strong> görünür, yani baştan başlamak yerine
            kaldığı yerden sürdürülür.
        </p>
        <p class="text-fg-secondary">
            Bir yenileme geçmezse planınız aynı gün durmaz. Önce bir ödemesiz süre vardır ve bu
            konuda size e-posta gelir — konu satırı
            <em>“Zabuno — ödemeniz gecikti”</em> — içinde iki tarihle: dönemin bittiği gün ve
            ücretli özelliklerin açık kalacağı son gün. Öğrenmek için panele bakıyor olmanız
            gerekmez.
        </p>
        <p class="text-fg-secondary">
            <strong>Test modu: gerçek ücret alınmaz</strong> yazısını mı görüyorsunuz? O zaman
            bu kurulum hâlâ ödeme sağlayıcısının test ortamına bağlıdır. Orada yaptığınız hiçbir
            şey gerçek para hareket ettirmez ve ondan gerçek bir fatura çıkmaz.
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
