@section('title', 'Your first 15 minutes')
@section('description', 'Import your menu, print QR codes, and change a price.')

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
        'prologueHeading' => 'Your first 15 minutes',
        'prologueLead' => 'Three things every restaurant does on day one. Each one describes a screen that exists today — nothing here is planned or coming soon.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <section id="help-import" aria-labelledby="help-import-heading" class="flex flex-col gap-3">
        <h2 id="help-import-heading" class="text-2xl font-bold">Import your menu</h2>
        <p class="text-fg-secondary">
            You do not have to type 60 dishes one by one. The menu screen takes a CSV file and
            creates everything in one go.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Menu</strong> in the workspace.</li>
            <li>
                Use <strong>Download menu (CSV)</strong> once to get a file with the right
                columns, even while the menu is still empty:
                <code class="rounded bg-surface px-1">category, product, price, currency, allergens, description, visible</code>.
            </li>
            <li>Fill it in your spreadsheet. Separate allergens with a semicolon (<code>milk;gluten</code>).</li>
            <li>Come back and use <strong>Import a CSV menu</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Rows that cannot be read are listed with their line number, and the good rows are
            still imported — you fix only what failed. Nothing reaches your guests until you
            publish.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-qr" aria-labelledby="help-qr-heading" class="flex flex-col gap-3">
        <h2 id="help-qr-heading" class="text-2xl font-bold">Print your QR codes</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Publish the menu first — a QR code needs something to point at.</li>
            <li>Open <strong>QR codes</strong> in the workspace and create the codes for your tables; there is a bulk option for a whole room of tables.</li>
            <li>Download the cards as PDF for the printer, or SVG for a designer.</li>
        </ol>
        <p class="text-fg-secondary">
            Print once. If you reorganise the menu later, the printed code keeps working: you
            can move where it points, and a code you disabled by mistake can be re-enabled.
            The paper on the table never becomes waste.
        </p>
        <p class="text-fg-secondary">
            Forty tables, sizes, designs and areas?
            <a class="site-inline-action" href="/help/table-cards-and-areas">Here is the whole table-card screen</a>.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-price" aria-labelledby="help-price-heading" class="flex flex-col gap-3">
        <h2 id="help-price-heading" class="text-2xl font-bold">Change a price</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Menu</strong>, find the dish, use <strong>Price</strong>.</li>
            <li>Open <strong>Publication</strong> and publish.</li>
        </ol>
        <p class="text-fg-secondary">
            The second step is the one people forget. Editing changes your draft; guests keep
            seeing the last published version until you publish again. That is deliberate — it
            lets you fix a whole price list before any guest sees half of it.
        </p>
        <p class="text-fg-secondary">
            Published the wrong list? Open <strong>Publication</strong>, find the version you
            want under <strong>Published versions</strong>, and go back to it. Nothing is
            deleted and your printed codes are untouched.
        </p>
        <p class="text-fg-secondary">
            Saved it and your guests still see the old price?
            <a class="site-inline-action" href="/help/nothing-changed-for-my-guests">That is the draft, and here is how to publish it</a>.
        </p>
        <p class="text-fg-secondary">
            Ran out of something tonight? Use <strong>Sold out</strong> on the dish. It stays
            on the menu with its price, marked as unavailable, and the mark clears itself the
            next day — no publishing needed.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-more" aria-labelledby="help-more-heading" class="flex flex-col gap-3">
        <h2 id="help-more-heading" class="text-2xl font-bold">Once those three are done</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <a class="site-inline-action" href="/help/nothing-changed-for-my-guests">Why has nothing changed for my guests?</a>
                — the answer to the step above: editing saves a draft, and the menu reaches the
                table when you publish it.
            </li>
            <li>
                <a class="site-inline-action" href="/help/a-photo-on-a-dish">How do I put a photo on a dish?</a>
                — the photo goes to Media first and the dish afterwards, and the last step is
                publishing.
            </li>
            <li>
                <a class="site-inline-action" href="/help/table-cards-and-areas">How do I print cards for my tables?</a>
                — one code per table, four ready sizes, and the areas that let you reprint the
                garden without reprinting the whole restaurant.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Something else in your way?
        <a class="site-inline-action" href="/contact">Write to us</a>.
    </p>
    </div>
</main>
