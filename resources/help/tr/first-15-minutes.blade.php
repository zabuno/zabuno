@section('title', 'İlk 15 dakikanız')
@section('description', 'Menünüzü aktarın, karekodları bastırın, fiyat değiştirin.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold">İlk 15 dakikanız</h1>
        <p class="text-fg-secondary">
            Her restoranın ilk gün yaptığı üç iş. Her biri BUGÜN var olan bir ekranı anlatır;
            burada planlanan ya da yakında gelecek hiçbir şey yok.
        </p>
    </div>

    <section id="help-import" aria-labelledby="help-import-heading" class="flex flex-col gap-3">
        <h2 id="help-import-heading" class="text-2xl font-bold">Menünüzü aktarın</h2>
        <p class="text-fg-secondary">
            60 ürünü tek tek yazmanız gerekmiyor. Menü ekranı bir CSV dosyası alır ve hepsini
            tek işlemde oluşturur.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Çalışma alanında <strong>Menu</strong> ekranını açın.</li>
            <li>
                Menü boşken bile bir kez <strong>Download menu (CSV)</strong> deyin; doğru
                sütunları taşıyan bir dosya iner:
                <code class="rounded bg-surface px-1">category, product, price, currency, allergens, description, visible</code>.
            </li>
            <li>Dosyayı Excel'de doldurun. Alerjenleri noktalı virgülle ayırın (<code>süt;gluten</code>).</li>
            <li>Geri dönüp <strong>Import a CSV menu</strong> ile yükleyin.</li>
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
            <li><strong>Publication</strong> ekranını açıp kod oluşturun; bütün bir salonun masaları için toplu seçenek var.</li>
            <li>Matbaa için <strong>PDF</strong>, tasarımcı için PNG/SVG olarak dışa aktarın.</li>
        </ol>
        <p class="text-fg-secondary">
            Bir kez bastırın. Menüyü sonradan yeniden düzenlerseniz basılı kod çalışmaya devam
            eder: nereyi gösterdiğini taşıyabilir, yanlışlıkla kapattığınız bir kodu geri
            açabilirsiniz. Masadaki kâğıt çöpe dönüşmez.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-price" aria-labelledby="help-price-heading" class="flex flex-col gap-3">
        <h2 id="help-price-heading" class="text-2xl font-bold">Fiyat değiştirin</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li><strong>Menu</strong> ekranını açın, ürünü bulun, <strong>Price</strong> deyin.</li>
            <li><strong>Publication</strong> ekranını açıp yayınlayın.</li>
        </ol>
        <p class="text-fg-secondary">
            Unutulan adım ikincisidir. Düzenleme TASLAĞI değiştirir; siz yeniden yayınlayana
            kadar misafir son yayınlanan sürümü görmeye devam eder. Bu bilerek böyle: bütün bir
            fiyat listesini, hiçbir misafir yarısını görmeden düzeltebilirsiniz.
        </p>
        <p class="text-fg-secondary">
            Yanlış listeyi mi yayınladınız? <strong>Publication</strong> ekranında
            <strong>Published versions</strong> altından istediğiniz sürümü bulup ona dönün.
            Hiçbir şey silinmez ve basılı kodlarınıza dokunulmaz.
        </p>
        <p class="text-fg-secondary">
            Bu akşam bir şey mi bitti? Ürün satırında <strong>Sold out</strong> deyin. Ürün
            fiyatıyla birlikte menüde kalır, bugün alınamayacağı yazar ve işaret ertesi gün
            kendiliğinden düşer — yayınlamanız gerekmez.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-more" aria-labelledby="help-more-heading" class="flex flex-col gap-3">
        <h2 id="help-more-heading" class="text-2xl font-bold">Daha fazla yardım</h2>
        <p class="text-fg-secondary">
            Her biri, bugün var olan bir ekranla ilgili tek bir soruyu cevaplar.
        </p>
        <ul class="flex flex-col gap-3 text-fg-secondary">
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/add-your-restaurant">Where do I put my restaurant name and address?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/nothing-changed-for-my-guests">I changed the menu but my guests still see the old one. Why?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/table-cards-and-areas">How do I print a card for every table?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/a-photo-on-a-dish">How do I put a photo on a dish?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/menus-that-change-during-the-day">How do I serve a breakfast menu and a dinner menu?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/who-can-do-what">How do I let my staff in, and what can each one do?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/guest-ratings">A guest scored a dish badly. Can I remove it?</a>
            </li>
            <li>
                <a class="flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help/plan-and-invoices">How do I change or cancel my plan, and where is my invoice?</a>
            </li>
        </ul>
        <p class="text-fg-secondary">
            Makaleler İngilizcedir; ürünün kendi dili de İngilizce. Her makale, o ekranda NE YAPILAMADIĞINI da yazar.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Önünüzde başka bir şey mi var?
        <a class="underline underline-offset-2" href="/contact">Bize yazın</a>.
    </p>
</main>
