@section('title', 'Your first 15 minutes')
@section('description', 'Import your menu, print QR codes, and change a price.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold">Your first 15 minutes</h1>
        <p class="text-fg-secondary">
            Three things every restaurant does on day one. Each one describes a screen that
            exists today — nothing here is planned or coming soon.
        </p>
    </div>

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
            <li>Open <strong>Publication</strong> and create codes; there is a bulk option for a whole room of tables.</li>
            <li>Export as <strong>PDF</strong> for the printer, or PNG/SVG for a designer.</li>
        </ol>
        <p class="text-fg-secondary">
            Print once. If you reorganise the menu later, the printed code keeps working: you
            can move where it points, and a code you disabled by mistake can be re-enabled.
            The paper on the table never becomes waste.
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
            Ran out of something tonight? Use <strong>Sold out</strong> on the dish. It stays
            on the menu with its price, marked as unavailable, and the mark clears itself the
            next day — no publishing needed.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-more" aria-labelledby="help-more-heading" class="flex flex-col gap-3">
        <h2 id="help-more-heading" class="text-2xl font-bold">More help</h2>
        <p class="text-fg-secondary">
            Each one answers a single question about a screen that exists today.
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
            Every article says what you cannot do there as well — the fastest way to stop looking for something that is not in the product.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Something else in your way?
        <a class="underline underline-offset-2" href="/contact">Write to us</a>.
    </p>
</main>
