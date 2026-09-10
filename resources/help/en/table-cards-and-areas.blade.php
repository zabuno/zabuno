@section('title', 'How do I print cards for my tables?')
@section('description', 'Create a code for every table, pick a size and a design, and download the cards as PDF or SVG.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Bu makalenin okuru bir BASKI
         SİPARİŞİ vermeye gelmiştir: kırk masası, bir mukavvası ve bir
         yazıcısı vardır. Başlık bandın İÇİNDE; sayfada ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'How do I print cards for my tables?',
        'prologueLead' => 'The QR codes screen is a print order, not a settings panel: what are you printing, which tables, how should it look. Every screen and button named below exists today.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <hr class="border-border" role="separator">

    <section id="help-cards-first" aria-labelledby="help-cards-first-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-first-heading" class="text-2xl font-bold">Publish the menu first</h2>
        <p class="text-fg-secondary">
            A code opens your published menu, so there has to be a published menu for it to open.
            Until there is one, <strong>QR codes</strong> says so instead of offering you a card,
            and the button that creates table codes stays closed with the reason written under it.
        </p>
        <p class="text-fg-secondary">
            So the order is: build the menu, open <strong>Publication</strong> and publish, then
            come back to <strong>QR codes</strong> in the workspace.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-create" aria-labelledby="help-cards-create-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-create-heading" class="text-2xl font-bold">One code for every table</h2>
        <p class="text-fg-secondary">
            You do not create forty codes one by one. Under <strong>Which tables?</strong>, open
            <strong>Add new tables</strong>.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Answer <strong>Table count</strong> — a whole number from 1 to 500. That is the
                only question you have to answer.
            </li>
            <li>
                Everything else already has a sensible answer and waits under
                <strong>Advanced options</strong>: <strong>Area/section count</strong> (1 to 50,
                one by default), <strong>Seat count per table</strong> (1 to 20, four by default),
                <strong>Naming prefix</strong> (up to 10 characters; names start at T1 without one),
                <strong>Naming sequence start</strong> and <strong>Naming range</strong>.
            </li>
            <li>Press <strong>Create table QR codes</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            The screen then lists every table it made, each one a link you can open on your phone
            to check that it lands on your menu. A naming range has to match the table count —
            asking for 20 tables numbered 1-30 is refused before anything is created, so you never
            end up with half a dining room.
        </p>
        <p class="text-fg-secondary">
            Creating codes in bulk is part of some plans and not others. If yours does not include
            it, nothing breaks: the screen says so plainly and offers <strong>See plans</strong>.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-print" aria-labelledby="help-cards-print-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-print-heading" class="text-2xl font-bold">Three questions, in your own order</h2>
        <p class="text-fg-secondary">
            <strong>What are you printing?</strong> comes first, because the paper size is the
            consequence of that answer, not the question. Four ready outputs:
            <strong>Table card</strong> for a plexiglass stand, <strong>Large table card</strong>
            for a long table, <strong>Wall poster</strong> for beside the till, and
            <strong>Window or door</strong> to be read from outside. They run A6, A5, A4, A3, all
            portrait.
        </p>
        <p class="text-fg-secondary">
            Need something else? <strong>I need another size</strong> opens the full list: paper
            sizes A3 to A6 and B3 to B6, the free ratios 1:2, 4:3 and 16:9, orientation, and
            <strong>File format</strong> — PDF, ready to print, or SVG, which scales without limit.
            There is no PNG card, and the screen says why: a raster image blurs the module edges of
            a small code.
        </p>
        <p class="text-fg-secondary">
            <strong>Which tables?</strong> is next: <strong>All tables</strong>,
            <strong>One area</strong> (offered once you have areas), or
            <strong>A single table</strong>. Only codes that are switched on are printed — a
            disabled code is never put on paper.
        </p>
        <p class="text-fg-secondary">
            <strong>How should it look?</strong> is last, and the five designs are
            <strong>Plain</strong>, <strong>Framed</strong>, <strong>Branded</strong>,
            <strong>Dark</strong> and <strong>Signage</strong>. Whatever you pick, the code itself
            is printed dark on light, because many phones do not read an inverted one. The table
            name is printed on every card, so two cards cannot be mixed up.
            <strong>Sentence on the card</strong> changes the line below the code for this print
            only; leave it empty and the card carries the ready sentence.
        </p>
        <p class="text-fg-secondary">
            The panel beside the steps draws the real card, with its size in millimetres and how
            big the code will come out. If a choice makes the code too small to be read reliably,
            it tells you there — before the paper, not after.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-download" aria-labelledby="help-cards-download-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-download-heading" class="text-2xl font-bold">Getting the files</h2>
        <p class="text-fg-secondary">
            The bar at the bottom of the screen stays with you and says your whole order in one
            sentence — how many cards, what size, which design, which format. Read it before you
            download; it is the sentence the printer will end up working from.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                One card: <strong>Print</strong> opens it in a new tab so you can send it straight
                to your own printer, and <strong>Download</strong> saves the file.
            </li>
            <li>
                More than one: you get a single zip file with one file per card, named after the
                table. That is the shape a print shop asks for. One archive holds at most 48 cards;
                if you have more, the screen says so and you print the rest in a second batch.
            </li>
            <li>
                <strong>Sheet to cut out (PDF)</strong> is a different thing and appears once you
                have more than one code: 12 cards to a page with cut lines, for cutting at home.
                It has its own layout — it does not carry the size or the design you picked above.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-areas" aria-labelledby="help-cards-areas-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-areas-heading" class="text-2xl font-bold">Garden, upstairs, terrace</h2>
        <p class="text-fg-secondary">
            Bulk creation names your areas Area 1, Area 2 — placeholders, not names. Open
            <strong>Code management and advanced printing</strong>, find
            <strong>Areas in your dining room</strong>, and rename them the way your team says them
            out loud. Type the name and press <strong>Save</strong>.
        </p>
        <p class="text-fg-secondary">
            This is worth the two minutes because the name is what you pick from later. Reprinting
            the garden after a wet week means choosing <strong>One area</strong> and seeing the
            word garden — not guessing which of Area 1 and Area 3 is outside.
        </p>
        <p class="text-fg-secondary">
            Renaming an area never breaks a printed card. The name is yours to read; the address on
            the card is not made of it.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-cards-manage" aria-labelledby="help-cards-manage-heading" class="flex flex-col gap-3">
        <h2 id="help-cards-manage-heading" class="text-2xl font-bold">After the cards are on the tables</h2>
        <p class="text-fg-secondary">
            Print once. Publishing a new menu does not change where a code points, so a price
            change never costs you a reprint.
        </p>
        <p class="text-fg-secondary">
            The rest lives under <strong>Code management and advanced printing</strong>.
            <strong>Disable</strong> stops a code from opening your menu — the card keeps its
            address, and <strong>Re-enable</strong> brings it back without reprinting anything.
            <strong>Move to another location</strong> points an existing card at a different
            branch, which is what you want when a table moves rather than when a card is lost.
        </p>
        <p class="text-fg-secondary">
            The same section holds <strong>Download the bare code file (PNG, SVG, PDF)</strong>:
            the code on its own, with no card around it, for a designer putting it into a menu
            board or a poster of their own.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        Something else in your way?
        <a class="site-inline-action" href="/contact">Write to us</a>, or go
        <a class="site-inline-action" href="/help">back to your first 15 minutes</a>.
    </p>
    </div>
</main>
