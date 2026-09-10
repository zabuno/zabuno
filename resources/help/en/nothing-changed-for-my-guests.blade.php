@section('title', 'Why has nothing changed for my guests?')
@section('description', 'Editing saves a draft. Guests keep seeing the last version you published until you publish again.')

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
        'prologueHeading' => 'Why has nothing changed for my guests?',
        'prologueLead' => 'Because editing saves a draft. Your menu reaches the table when you publish it — and every screen and button named below exists today.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        Nothing is lost and nothing is broken. Your change was saved — into your draft, which
        only you can see.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-publication-two-menus" aria-labelledby="help-publication-two-menus-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-two-menus-heading" class="text-2xl font-bold">You have two menus, not one</h2>
        <p class="text-fg-secondary">
            One is your <strong>Draft</strong>: the menu you edit. Every price you change, every
            name you fix, every photo you attach lands there the moment you save it.
        </p>
        <p class="text-fg-secondary">
            The other is what is <strong>Live</strong>: a frozen copy, taken at the moment you
            last published. That copy is what a guest gets when they scan the code on the table,
            and it does not move on its own.
        </p>
        <p class="text-fg-secondary">
            This is deliberate, and it is the same reason a kitchen does not send a half-plated
            dish. You can correct a whole price list, or photograph a whole section, over an
            afternoon without any guest seeing it half-done. It also means a failed publish is
            harmless: guests keep seeing the menu you published last, so nothing breaks at the
            table.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-publish" aria-labelledby="help-publication-publish-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-publish-heading" class="text-2xl font-bold">Send your draft to the table</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Publication</strong> in the workspace.</li>
            <li>
                Read <strong>Changes waiting to be published</strong> first. It lists what will
                actually change for your guests — a price, a name, a dish added, a dish hidden.
                If it says nothing is waiting, your guests are already seeing your latest work.
            </li>
            <li>
                Check the <strong>Publish readiness checklist</strong>. Every line has to say
                <strong>Ready</strong>. A line that says <strong>Needs attention</strong> has
                <strong>Fix</strong> next to it, and that takes you straight to the screen where
                you can fix it.
            </li>
            <li>Tick <strong>I reviewed the publish checklist</strong>.</li>
            <li>Press <strong>Publish</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            The checkbox and the button only wake up once the checklist is clean. That is not the
            screen being difficult: a menu with a nameless category or a priceless dish would
            reach a real table that way.
        </p>
        <p class="text-fg-secondary">
            Once it goes through, the version number goes up and the next guest to open the menu
            gets the new one. Your printed codes are untouched — the address they point at never
            changes because you published, so publishing never costs you a reprint.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-preview" aria-labelledby="help-publication-preview-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-preview-heading" class="text-2xl font-bold">Look at it before your guests do</h2>
        <p class="text-fg-secondary">
            On the same screen, <strong>This is what a guest will see</strong> shows your draft in
            a narrow column, at the width a phone reads it — that is where you notice a long dish
            name breaking oddly or a price landing in the wrong place.
        </p>
        <p class="text-fg-secondary">
            To hold it in your hand, press <strong>Open the preview link</strong> and open it on
            your own phone. That link works for 15 minutes and is closed to search engines. It is
            not your guests' address: while you look at your draft, the printed code on the table
            keeps showing the published menu.
        </p>
        <p class="text-fg-secondary">
            Never print or send that link to anyone. It expires, and a card printed with it would
            be dead paper the same day. The only address your guests get is the one on your QR
            codes.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-schedule" aria-labelledby="help-publication-schedule-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-schedule-heading" class="text-2xl font-bold">Publish later, on purpose</h2>
        <p class="text-fg-secondary">
            New prices from Monday? You do not have to be at your desk on Monday.
            <strong>Schedule the publish</strong> offers ready times — <strong>Tonight 03:00</strong>,
            <strong>Tomorrow 09:00</strong>, <strong>Monday 09:00</strong> — shown in your own
            location's time zone, not ours.
        </p>
        <p class="text-fg-secondary">
            A scheduled publish is a publish: it takes the next version number and your printed QR
            code stays the same. It also freezes what you see now, so finish the readiness list
            first; until you do, the screen says so instead of offering you a time.
        </p>
        <p class="text-fg-secondary">
            Changed your mind before it runs? <strong>Cancel this schedule</strong>. And if a
            scheduled publish ever does not go through, the screen tells you plainly: the menu did
            not change, your guests still see the previous version, and you can publish now or
            schedule it again.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-undo" aria-labelledby="help-publication-undo-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-undo-heading" class="text-2xl font-bold">Published the wrong thing</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Publication</strong> and find <strong>Published versions</strong>.</li>
            <li>Pick the version you want back and use <strong>Go back to this version</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Going back is a publish too: it gets a new version number, and the QR stays the same.
            Nothing is deleted — the version you regretted stays in the list, and you can return
            to it just as easily if it turns out you were right the first time.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-publication-without-publishing" aria-labelledby="help-publication-without-publishing-heading" class="flex flex-col gap-3">
        <h2 id="help-publication-without-publishing-heading" class="text-2xl font-bold">The one thing that reaches guests without publishing</h2>
        <p class="text-fg-secondary">
            <strong>Sold out</strong>, on the dish in your <strong>Menu</strong>. The dish stays on
            the guest's menu with its price, marked as unavailable, and the mark clears itself the
            next day.
        </p>
        <p class="text-fg-secondary">
            It works that way for a reason you already know: the fish runs out in the middle of
            service. Making you publish then would be both slow and risky, because your draft may
            hold a price edit you have not finished.
        </p>
        <p class="text-fg-secondary">
            Everything else is part of the frozen copy and needs a publish — prices, dish names,
            descriptions, categories, the order of the menu, dish photos, your logo, and your
            brand name, address and phone number.
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
