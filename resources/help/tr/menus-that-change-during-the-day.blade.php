@section('title', 'Kahvaltı menüsü ile akşam menüsünü nasıl ayrı sunarım?')
@section('description', 'Aynı şube için ikinci bir menü açın ve her birine kendi servis saatlerini verin. Tek basılı kart, iki menü.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Kahvaltı menüsü ile akşam menüsünü nasıl ayrı sunarım?',
        'prologueLead' => 'Aynı şube için ikinci bir menü açın ve her birine kendi saatlerini verin. Tek basılı kart, iki menü — ve sabahın yedisinde kimse bir şey değiştirmez.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">İlk 15 dakikanıza dönün</a>
    </p>

    <p class="text-fg-secondary">
        Sabah dokuzda kodu okutan misafir kahvaltıyı görür; aynı kod akşam sekizde akşam
        menüsünü verir. Masadaki kart hiç değişmez.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-hours-second" aria-labelledby="help-hours-second-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-second-heading" class="text-2xl font-bold">İkinci menüyü oluşturun</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Menü</strong> ekranını açın. Üst kısımda
                <strong>Bu şubedeki menüler</strong> hâlihazırda neyiniz olduğunu gösterir.
            </li>
            <li>
                <strong>Yeni menü</strong> deyin, bir <strong>Menü adı</strong> yazın —
                “Kahvaltı” iyi bir addır — ve <strong>Menü oluştur</strong> düğmesine basın.
            </li>
            <li>Her zamanki gibi doldurun: önce bir kategori, sonra ürünler.</li>
            <li>Hazır olduğunda <strong>Önizle ve yayınla</strong> deyin.</li>
        </ol>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-window" aria-labelledby="help-hours-window-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-window-heading" class="text-2xl font-bold">Saatlerini verin</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Menü açıkken <strong>Başlangıç</strong> ve <strong>Bitiş</strong> saatlerini
                girin.
            </li>
            <li>
                İkisini de doldurun ya da ikisini de boş bırakın. Yalnız birini girmek
                reddedilir — ekran bunu hiçbir şey kaydedilmeden önce söyler.
            </li>
            <li>
                Bir aralık gece yarısını geçebilir: <strong>22.00–02.00</strong> geç saat
                menüsüdür, hata değil.
            </li>
        </ol>
        <p class="text-fg-secondary">
            Bitiş saatinde, o saati daha önce kapsayan menü geri döner — böylece günün her
            saatinde mutlaka bir menü olur. Saatler sizin şubenizin kendi saatidir; bizimki de
            değil, misafirin telefonunki de değil.
        </p>
        <p class="text-fg-secondary">
            Her menü, o an nerede durduğunu söyleyen küçük bir sözcük taşır:
            <strong>şimdi açık</strong>, <strong>kapalı</strong> ya da hiç yayınlanmamışsa
            <strong>taslak</strong>.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-limits" aria-labelledby="help-hours-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-limits-heading" class="text-2xl font-bold">Burada yapamayacaklarınız</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Bitiş saati olmadan başlangıç saati veremezsiniz.</em> Yarım bir aralık,
                günün bir saatini cevapsız bırakırdı; ekran bunu reddeder ve hiçbir şey
                kaydedilmez.
            </li>
            <li>
                <em>Saatler haftanın gününe göre ayarlanmaz.</em> Bir aralık her gün aynıdır.
                Yalnız pazar günü sunulan bir menüyü buradan kuramazsınız.
            </li>
            <li>
                <em>Bir menüyü sıradan çıkarmak onu silmek değildir.</em>
                <strong>Bu menüyü kapat</strong> — ya da iki saati de boşaltmak — içindeki her
                şeyi korur; menü yalnızca sunulmayı bırakır.
            </li>
            <li>
                <em>Misafirler menüleriniz arasında seçim yapmaz.</em> O an sunulan menüyü
                görürler. Hiçbir menü o saati kapsamıyorsa boş bir sayfa değil, şu anda menü
                sunulmadığı bilgisini alırlar.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-wrong" aria-labelledby="help-hours-wrong-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-wrong-heading" class="text-2xl font-bold">Misafirler yanlış menüyü görüyorsa</h2>
        <p class="text-fg-secondary">
            Önce saate bakın: saatler şubenin saat dilimini izler ve yanlış dilimle kurulmuş bir
            şube akşam saatinde kahvaltı dağıtır. Bunu şubenin altından ayarlarsınız ve
            değiştirmek başka hiçbir şeyi değiştirmez.
        </p>
        <p class="text-fg-secondary">
            İkinci olarak, beklediğiniz menünün yayınlanmış olduğunu doğrulayın.
            <strong>taslak</strong> işaretli bir menü, saatleri ne derse desin bir misafire hiç
            ulaşmamıştır.
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
