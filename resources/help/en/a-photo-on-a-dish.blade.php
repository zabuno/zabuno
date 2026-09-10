@section('title', 'How do I put a photo on a dish?')
@section('description', 'Upload the photo on the Media screen first, then attach it to the dish from the menu.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'How do I put a photo on a dish?',
        'prologueLead' => 'Two steps, in this order: the photo goes to Media first, and you attach it to the dish afterwards. Every screen named here exists today.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        Photos live in one place so the same picture can be used twice without being uploaded
        twice — and so a picture that is too small for the menu is caught before a guest sees
        it, not after.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-photo-upload" aria-labelledby="help-photo-upload-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-upload-heading" class="text-2xl font-bold">Step one — get the photo in</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Media</strong> in the workspace.</li>
            <li>
                Under <strong>Add photos</strong> you can
                <strong>Drop an image here, or choose a file</strong>. On a phone there is also
                <strong>Take a photo</strong>.
            </li>
            <li>
                Answer <strong>Where will this image be used?</strong> — for a dish that is
                <strong>List/card/detail item</strong>. The screen then tells you the smallest
                size and the frame shape that place accepts.
            </li>
            <li>
                Write the <strong>Alt text</strong>: a short description for guests who cannot
                see the picture, for example “grilled lamb chops on a wooden board”. It is
                required for a dish photo.
            </li>
            <li>
                Press <strong>Upload</strong> and wait until the photo says
                <strong>Ready</strong>.
            </li>
        </ol>
        <p class="text-fg-secondary">
            While it still says <strong>Processing</strong> the photo is being checked and
            resized, and it is not usable yet — a dish will not offer it until it is
            <strong>Ready</strong>.
        </p>
        <p class="text-fg-secondary">
            About file types: the upload area names JPEG, PNG and WebP, and
            <strong>Supported types</strong> on the same screen lists every type Zabuno takes
            and says what happens to it. Read that table rather than guessing what your phone
            saved. A photo larger than it needs to be is shrunk on your own device first — the
            screen says <strong>Shrunk on your phone</strong> and shows what will be sent, and
            nothing leaves your device until you press <strong>Upload</strong>.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-attach" aria-labelledby="help-photo-attach-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-attach-heading" class="text-2xl font-bold">Step two — put it on the dish</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Open <strong>Menu</strong> and find the dish. One with no picture says
                <strong>No photo</strong>.
            </li>
            <li>Use <strong>Photo &amp; text</strong> on that row.</li>
            <li>
                Pick your photo, write the line guests read under the name, and press
                <strong>Save presentation</strong>.
            </li>
        </ol>
        <p class="text-fg-secondary">
            Your logo works the same way: upload it on <strong>Media</strong> as a
            <strong>Logo</strong>, then open <strong>Brand</strong> and choose it there. It is
            shown at the top of your guest menu next to the brand name.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-publish" aria-labelledby="help-photo-publish-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-publish-heading" class="text-2xl font-bold">Step three — the one people forget</h2>
        <p class="text-fg-secondary">
            Saving is not publishing. The photo is on your draft now; guests keep seeing the
            last version you published until you publish again. That is deliberate — it lets
            you photograph a whole section before any guest sees half of it.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Publication</strong>.</li>
            <li>
                Use <strong>Preview on a phone</strong> first. A photo that looked right on a
                laptop is judged on a phone, because that is where your guest reads it.
            </li>
            <li>
                Then, under <strong>Publication status</strong>, tick
                <strong>I reviewed the publish checklist</strong> and press
                <strong>Publish</strong>.
            </li>
        </ol>
        <p class="text-fg-secondary">
            The preview link works for fifteen minutes and is closed to search engines. It is
            not your guests' address — your printed QR codes keep pointing at the published
            menu the whole time, so previewing never puts a half-finished menu on the table.
        </p>
        <p class="text-fg-secondary">
            Regret it once it is live? Open <strong>Publication</strong>, find the version you
            want under <strong>Published versions</strong>, and go back to it. Going back is a
            publish too: it gets a new version number, and the QR stays the same.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-limits" aria-labelledby="help-photo-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot upload from the dish itself.</em> The dish only offers photos
                that are already in <strong>Media</strong> and already <strong>Ready</strong>.
                If that list is empty the screen says so and gives you a link to the Media
                page — it is not broken, it is telling you which step is missing.
            </li>
            <li>
                <em>A small photo is not stretched.</em> Zabuno never enlarges a picture,
                because an enlarged one looks blurred on a phone. If yours is too small the
                screen refuses it before the upload and tells you the size it needs.
            </li>
            <li>
                <em>You cannot delete a photo that is on a live menu.</em> It has to leave the
                menu first: publish a menu without it, then delete. Everywhere else, deleting
                moves the photo to <strong>Trash</strong> and it can be restored — the items
                that used it show a placeholder in the meantime.
            </li>
            <li>
                <em>Zabuno does not make the picture for you.</em> There is no image generation
                and no retouching here; the photo is yours.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-photo-stuck" aria-labelledby="help-photo-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-photo-stuck-heading" class="text-2xl font-bold">If the photo does not stick</h2>
        <p class="text-fg-secondary">
            If the picture fails to attach, your words are not lost: the screen tells you the
            description was saved and the photo was not, so you only redo the picture.
        </p>
        <p class="text-fg-secondary">
            A photo can also come back as <strong>Processing failed</strong> or
            <strong>Rejected — failed security scan</strong>. Neither is something you did
            wrong at the table; the file did not survive a check on our side. Try a different
            copy of the picture, and if it keeps happening tell us the file name.
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
