@section('title', 'How do I serve a breakfast menu and a dinner menu?')
@section('description', 'Make a second menu for the same place and give each one its serving hours. One printed card, two menus.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'How do I serve a breakfast menu and a dinner menu?',
        'prologueLead' => 'Make a second menu for the same place and give each one its hours. One printed card, two menus, and nobody switching anything at seven in the morning.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        A guest who scans at nine in the morning gets breakfast; the same code at eight in the
        evening gets dinner. The card on the table never changes.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-hours-second" aria-labelledby="help-hours-second-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-second-heading" class="text-2xl font-bold">Make the second menu</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Open <strong>Menu</strong>. Along the top, <strong>Menus at this location</strong>
                shows what you already have.
            </li>
            <li>
                Use <strong>New menu</strong>, type a <strong>Menu name</strong> —
                “Breakfast” is a good name — and press <strong>Create menu</strong>.
            </li>
            <li>Fill it the usual way: a category, then dishes.</li>
            <li>Use <strong>Preview &amp; publish</strong> when it is ready.</li>
        </ol>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-window" aria-labelledby="help-hours-window-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-window-heading" class="text-2xl font-bold">Give it its hours</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>With the menu open, set <strong>Starts at</strong> and <strong>Ends at</strong>.</li>
            <li>
                Fill in both, or leave both empty. One of the two on its own is refused — the
                screen says so before anything is saved.
            </li>
            <li>
                A window may cross midnight: <strong>22:00 to 02:00</strong> is a late menu, not
                a mistake.
            </li>
        </ol>
        <p class="text-fg-secondary">
            At the end time, whichever menu covered that hour before comes back — so every hour
            of the day always has a menu. The times are your location's own clock, not ours and
            not the guest's phone.
        </p>
        <p class="text-fg-secondary">
            Each menu carries a small word that tells you where it stands right now:
            <strong>open now</strong>, <strong>closed</strong>, or <strong>draft</strong> for one
            that has never been published.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-limits" aria-labelledby="help-hours-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot give a menu a start time without an end time.</em> Half a window
                would leave an hour of the day with no answer, so the screen refuses it and
                nothing is saved.
            </li>
            <li>
                <em>Hours are not per weekday.</em> A window is the same every day. A
                Sunday-only menu is not something you can set here.
            </li>
            <li>
                <em>Taking a menu out of the rotation is not deleting it.</em>
                <strong>Close this menu</strong> — or clearing both times — keeps everything in
                it; it simply stops being served.
            </li>
            <li>
                <em>Guests do not choose between your menus.</em> They get the one being served
                at that moment. If no menu covers the hour, they are told that none is being
                served right now — not shown an empty page.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-hours-wrong" aria-labelledby="help-hours-wrong-heading" class="flex flex-col gap-3">
        <h2 id="help-hours-wrong-heading" class="text-2xl font-bold">If guests see the wrong one</h2>
        <p class="text-fg-secondary">
            Check the clock first: the hours follow the location's time zone, and a location set
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
        Something else in your way?
        <a class="site-inline-action" href="/contact">Write to us</a>, or go
        <a class="site-inline-action" href="/help">back to your first 15 minutes</a>.
    </p>
    </div>
</main>
