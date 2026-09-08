@section('title', 'I changed the menu but my guests still see the old one. Why?')
@section('description', 'Editing changes your draft. Guests keep seeing the last published version until you publish again.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">I changed the menu but my guests still see the old one. Why?</h1>
        <p class="text-fg-secondary">
            Because the change is still in your draft. Editing a price, a name or a photo saves
            it for you; guests keep seeing the version you published last until you publish
            again. Two taps fix it: <strong>Preview &amp; publish</strong>, then
            <strong>Publish</strong>.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="why" aria-labelledby="why-heading" class="flex flex-col gap-3">
        <h2 id="why-heading" class="text-2xl font-bold">Why it works this way</h2>
        <p class="text-fg-secondary">
            It is deliberate, and it is on your side. A whole price list takes twenty minutes to
            retype. If every keystroke went straight to the table, guests would spend those
            twenty minutes reading a half-changed menu. So you finish first, then you publish
            once.
        </p>
        <p class="text-fg-secondary">
            After you save a price the screen tells you the same thing in one line:
            <strong>Saved. Guests still see the last published menu.</strong> Next to it is
            <strong>Publish now</strong>. That line is the whole answer to this question.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="publish" aria-labelledby="publish-heading" class="flex flex-col gap-3">
        <h2 id="publish-heading" class="text-2xl font-bold">Publishing it</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Menu</strong> and use <strong>Preview &amp; publish</strong>.</li>
            <li>
                Read the short checklist. Every line says <strong>Ready</strong> or
                <strong>Needs attention</strong>; a line that needs attention has a
                <strong>Fix</strong> button that takes you to the thing it is about.
            </li>
            <li>
                Want to see it the way a guest will? Use <strong>Preview on a phone</strong>.
                The preview link works for fifteen minutes and is closed to search engines.
            </li>
            <li>Tick <strong>I reviewed the publish checklist</strong> and press <strong>Publish</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Your printed cards do not change and do not need reprinting. A code always points at
            whatever is published right now.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="undo" aria-labelledby="undo-heading" class="flex flex-col gap-3">
        <h2 id="undo-heading" class="text-2xl font-bold">Published the wrong list</h2>
        <p class="text-fg-secondary">
            Open <strong>Publication</strong>, find the version you want under
            <strong>Published versions</strong> and use <strong>Go back to this version</strong>.
            Nothing is deleted. Going back is itself a publish: it takes the next version number,
            and the QR stays the same.
        </p>
        <p class="text-fg-secondary">
            Ran out of one dish tonight? You do not need any of this. Use
            <strong>Sold out</strong> on the dish; it stays on the menu with its price, marked as
            unavailable, and the mark clears itself the next day.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>There is no “unpublish”.</em> You cannot take the menu off the table and
                leave the code showing nothing. What you can do is go back to an earlier
                version, which is a new publish of that older list.
            </li>
            <li>
                <em>You cannot delete a published version.</em> The list of published versions
                only grows, so a version you regret can always be gone back to.
            </li>
            <li>
                <em>Guests never see a draft.</em> Not by accident, not through the printed
                code, not through a search engine. The only way to show a draft to somebody is
                the preview link, and it expires.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If the publish fails</h2>
        <p class="text-fg-secondary">
            Nothing breaks at the table. If publishing fails, guests keep seeing the menu you
            published last — the screen says so before you press the button, and it is true
            afterwards. Try again; if it keeps failing, the change is still safely in your draft.
        </p>
        <p class="text-fg-secondary">
            Still stuck? From inside the panel, open <strong>Support</strong>, use
            <strong>New request</strong> and <strong>Send request</strong>: you get a reference
            number and a copy by email, and the request stays listed with its status. If you
            cannot sign in at all, use the link below instead.
        </p>
        <p>
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/contact">Write to us</a>
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
