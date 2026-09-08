@section('title', 'How do I print a card for every table?')
@section('description', 'Create the codes for your tables in one go, pick a size, and download a print-ready file.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">How do I print a card for every table?</h1>
        <p class="text-fg-secondary">
            On the <strong>QR codes</strong> screen you say how many tables you have, Zabuno
            creates a code for each one, and you download a print-ready file. You do not create
            them one at a time, and you do not reprint later when the menu changes.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="create" aria-labelledby="create-heading" class="flex flex-col gap-3">
        <h2 id="create-heading" class="text-2xl font-bold">Make the codes</h2>
        <p class="text-fg-secondary">
            Publish your menu first. A code opens your published menu, so there has to be one
            for it to open — the screen will tell you if you are early.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>QR codes</strong>.</li>
            <li>Under <strong>Bulk codes for new tables</strong>, type your <strong>Table count</strong>.</li>
            <li>
                Press <strong>Create table QR codes</strong>. The rest is filled in for you: one
                area, four seats per table, and names starting at T1.
            </li>
        </ol>
        <p class="text-fg-secondary">
            The table name is printed on every card, so two cards can never be mixed up. Opened
            more tables later? Use <strong>Add new tables</strong>; the codes you already
            printed are untouched.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="print" aria-labelledby="print-heading" class="flex flex-col gap-3">
        <h2 id="print-heading" class="text-2xl font-bold">Choose the size and print</h2>
        <p class="text-fg-secondary">
            The screen asks three short questions: what you are printing, which tables, and how
            it should look. It shows the card as it will come out, at the real size, before you
            download anything.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Table card</strong> — the usual one, sized for a plexiglass stand.
                <strong>Large table card</strong> is for a long table with a card at each end.
            </li>
            <li>
                <strong>Wall poster</strong> goes beside the till or at the door;
                <strong>Window or door</strong> is meant to be read from outside.
            </li>
            <li>
                Print all of them with <strong>Download every card (PDF)</strong>, or narrow it
                down: <strong>All tables</strong>, <strong>One area</strong>, or
                <strong>A single table</strong> when one card goes missing.
            </li>
            <li>
                Printing on ordinary paper at the restaurant? <strong>Sheet to cut out (PDF)</strong>
                puts twelve cards on a page with cut lines.
            </li>
        </ul>
        <p class="text-fg-secondary">
            The sentence on the card reads <strong>Scan for the menu</strong> by default. Change
            it under <strong>Sentence on the card</strong> — keep it short, it is read from a
            distance.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="areas" aria-labelledby="areas-heading" class="flex flex-col gap-3">
        <h2 id="areas-heading" class="text-2xl font-bold">Garden, terrace, upstairs</h2>
        <p class="text-fg-secondary">
            Bulk creation names your rooms Area 1, Area 2. Under
            <strong>Areas in your dining room</strong> you can rename them the way your team says
            them out loud — garden, upstairs, terrace — and then print one area at a time.
            Renaming never breaks a printed card.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>There is no PNG of the card.</em> A raster image blurs the edges of a
                four-centimetre code and it stops scanning reliably. PDF goes straight to a
                printer; SVG opens in a design tool if your print shop asks for one.
            </li>
            <li>
                <em>You cannot print before you publish.</em> The screen holds the buttons closed
                and says why, rather than handing you cards that open nothing.
            </li>
            <li>
                <em>Making the codes in bulk is not on every plan.</em> If yours does not include
                it, the screen says so and shows you the plans; single codes still work.
            </li>
            <li>
                <em>One download file has a limit.</em> Very large dining rooms come out in two
                batches — the screen tells you how many were left out and you print the rest by
                picking another area.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If a printed card has to change</h2>
        <p class="text-fg-secondary">
            It almost never does. The paper on the table survives new prices, a new menu, even a
            move: you can point a code at another location with
            <strong>Move to another location</strong>, and the printed card stays valid.
        </p>
        <p class="text-fg-secondary">
            A card that must stop working — a table you removed — can be closed with
            <strong>Disable</strong>, and a code you disabled by mistake comes back with
            <strong>Re-enable</strong>. Neither one needs a reprint.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
