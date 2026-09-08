@section('title', 'How do I put a photo on a dish?')
@section('description', 'Upload the photo on the Media screen first, then attach it to the dish from the menu.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">How do I put a photo on a dish?</h1>
        <p class="text-fg-secondary">
            In two steps, in this order: the photo goes to <strong>Media</strong> first, and you
            attach it to the dish afterwards. Photos live in one place so the same picture can be
            used twice without being uploaded twice — and so a blurred one is caught before a
            guest sees it.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="upload" aria-labelledby="upload-heading" class="flex flex-col gap-3">
        <h2 id="upload-heading" class="text-2xl font-bold">Step one — get the photo in</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Media</strong>.</li>
            <li>
                Under <strong>Add photos</strong> you can
                <strong>Drop an image here, or choose a file</strong>. Photos straight off a
                phone are fine.
            </li>
            <li>
                Answer <strong>Where will this image be used?</strong> — for a dish, that is
                <strong>List/card/detail item</strong>. Zabuno then tells you the smallest size
                it accepts for that place.
            </li>
            <li>
                Write the <strong>Alt text</strong>: a short description for guests who cannot
                see the picture, for example “grilled lamb chops on a wooden board”.
            </li>
            <li>Press <strong>Upload</strong> and wait until the photo says <strong>Ready</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            While it says <strong>Processing</strong> the photo is being checked and resized for
            phones. It is not usable yet, and that is a few seconds, not a few minutes.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="attach" aria-labelledby="attach-heading" class="flex flex-col gap-3">
        <h2 id="attach-heading" class="text-2xl font-bold">Step two — put it on the dish</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Menu</strong> and find the dish. One with no picture says <strong>No photo</strong>.</li>
            <li>Use <strong>Photo &amp; text</strong> on that row.</li>
            <li>Pick your photo, write the line guests read under the name, and press <strong>Save presentation</strong>.</li>
            <li>Use <strong>Preview &amp; publish</strong> — until you publish, the photo is yours alone.</li>
        </ol>
        <p class="text-fg-secondary">
            Your logo works the same way: upload it on <strong>Media</strong> as a
            <strong>Logo</strong>, then choose it in your brand settings. Your
            <strong>Brand colour</strong> is set there too, and it is the colour your menu is
            built from.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot upload from the dish itself.</em> The dish only offers photos that
                are already in <strong>Media</strong> and already <strong>Ready</strong>. If the
                list is empty, that is what it is telling you.
            </li>
            <li>
                <em>A small photo is not stretched.</em> Zabuno never enlarges a picture, because
                an enlarged one looks blurred on a phone. If yours is too small the screen says
                so and gives you the size it needs, before the upload.
            </li>
            <li>
                <em>You cannot delete a photo that is on a live menu.</em> It has to leave the
                menu first: publish a menu without it, then delete. Everywhere else, deleting
                sends the photo to the trash and it can be restored.
            </li>
            <li>
                <em>Zabuno does not take the photo for you.</em> There is no stock library and no
                image generator here; the picture is yours.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If the photo does not stick</h2>
        <p class="text-fg-secondary">
            If the picture fails to attach, your words are not lost — the screen tells you the
            description was saved and the photo was not, so you only redo the picture.
        </p>
        <p class="text-fg-secondary">
            If a photo never leaves <strong>Processing</strong>, or comes back rejected, that is
            a check on our side and not something you did wrong. Open
            <strong>Support</strong> from the panel and send us the file name.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
