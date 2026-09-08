@section('title', 'How do I serve a breakfast menu and a dinner menu?')
@section('description', 'Make a second menu for the same place and give each one its serving hours.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">How do I serve a breakfast menu and a dinner menu?</h1>
        <p class="text-fg-secondary">
            Make a second menu for the same place and give each one its hours. A guest who scans
            at nine in the morning gets breakfast; the same code at eight in the evening gets
            dinner. One printed card, two menus, no staff doing anything at the switchover.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="second-menu" aria-labelledby="second-menu-heading" class="flex flex-col gap-3">
        <h2 id="second-menu-heading" class="text-2xl font-bold">Make the second menu</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Menu</strong>. Along the top, <strong>Menus at this location</strong> shows what you already have.</li>
            <li>Use <strong>New menu</strong>, type a <strong>Menu name</strong> — “Breakfast” is a good name — and press <strong>Create menu</strong>.</li>
            <li>Fill it the usual way: a category, then dishes. Or bring the whole thing in from a CSV file.</li>
            <li>Use <strong>Preview &amp; publish</strong> when it is ready.</li>
        </ol>
    </section>

    <hr class="border-border" role="separator">

    <section id="hours" aria-labelledby="hours-heading" class="flex flex-col gap-3">
        <h2 id="hours-heading" class="text-2xl font-bold">Give it its hours</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>With the menu open, set <strong>Starts at</strong> and <strong>Ends at</strong>.</li>
            <li>Fill in both, or leave both empty. One of the two on its own is refused.</li>
            <li>A window may cross midnight: 22:00 to 02:00 is a late menu, not a mistake.</li>
        </ol>
        <p class="text-fg-secondary">
            At the end time, whichever menu covered that hour before comes back — so every hour
            of the day always has a menu. The times are your location’s own clock, not ours and
            not the guest’s phone.
        </p>
        <p class="text-fg-secondary">
            Each menu carries a small word that tells you where it stands right now:
            <strong>open now</strong>, <strong>closed</strong>, or <strong>draft</strong> for one
            that has never been published.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot give a menu a start time without an end time.</em> Half a window
                would leave an hour of the day with no answer, so the screen refuses it and
                nothing is saved.
            </li>
            <li>
                <em>Hours are not per weekday.</em> A window is the same every day. A Sunday-only
                menu is not something you can set here.
            </li>
            <li>
                <em>Taking a menu out of the rotation is not deleting it.</em>
                <strong>Close this menu</strong> — or clearing both times — keeps everything in
                it; it simply stops being served.
            </li>
            <li>
                <em>Guests do not choose between your menus.</em> They get the one being served
                at that moment. If no menu covers the hour, they read that no menu is being
                served right now and are told when the next service starts.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If guests see the wrong one</h2>
        <p class="text-fg-secondary">
            Check the clock first: the hours follow the location’s time zone, and a location set
            up in the wrong zone will hand out breakfast in the evening. You set that under the
            location, and changing it changes nothing else.
        </p>
        <p class="text-fg-secondary">
            Second, check that the menu you expect is published. A menu marked
            <strong>draft</strong> has never reached a guest, whatever its hours say.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
