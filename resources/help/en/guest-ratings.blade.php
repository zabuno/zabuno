@section('title', 'A guest scored a dish badly. Can I remove it?')
@section('description', 'No. You can reply, and your reply is shown to guests under the dish.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">A guest scored a dish badly. Can I remove it?</h1>
        <p class="text-fg-secondary">
            No, and that is deliberate. What you can do is answer: your reply appears under the
            dish on the guest’s own screen. An average the restaurant could delete would be an
            advertisement, not a measurement — and guests know the difference.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="reply" aria-labelledby="reply-heading" class="flex flex-col gap-3">
        <h2 id="reply-heading" class="text-2xl font-bold">Answering a score</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Ratings</strong> and pick the branch and the menu.</li>
            <li>Find the dish. You see its score and how many guests have voted.</li>
            <li>Write under <strong>Your reply</strong> — this is read by guests, under the dish.</li>
            <li>Press <strong>Publish reply</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Changed your mind about the wording? <strong>Update reply</strong> replaces it, and
            <strong>Withdraw reply</strong> takes it down. Your reply is yours; the score is not.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="numbers" aria-labelledby="numbers-heading" class="flex flex-col gap-3">
        <h2 id="numbers-heading" class="text-2xl font-bold">Why some dishes show no number</h2>
        <p class="text-fg-secondary">
            A dish with two votes says <strong>Not enough ratings yet</strong> instead of showing
            a score. Two guests are not a verdict, and a number built on two votes would say
            something your guests never said. The score appears on its own once enough people
            have voted.
        </p>
        <p class="text-fg-secondary">
            The screen also names the method that produced the numbers. If a score moves, that
            tells you whether it moved because new guests voted or because the way scores are
            worked out changed.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot delete a rating, and you cannot hide a score.</em> There is no
                button for it anywhere, on any plan.
            </li>
            <li>
                <em>You cannot add a rating from the panel.</em> A score only exists because
                somebody scanned the code at one of your tables and left it there.
            </li>
            <li>
                <em>You cannot see who left a score.</em> Guests do not sign in to rate, so there
                is no name to show you.
            </li>
            <li>
                <em>Ratings are not part of every role.</em> If the screen says so, ask the
                workspace owner.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If the score is about a dish you changed</h2>
        <p class="text-fg-secondary">
            A rating belongs to the dish, and it stays with the dish through price changes and
            new photos. If you have genuinely replaced a dish with a different one, add it as a
            new item on the <strong>Menu</strong> rather than renaming the old one — the new
            dish starts with no votes, which is the honest place to start.
        </p>
        <p class="text-fg-secondary">
            If a rating breaks the law where you are — a threat, or somebody’s personal data —
            that is not a product setting. Open <strong>Support</strong> from the panel and tell
            us which dish and when.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
