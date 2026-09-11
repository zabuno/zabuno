@section('title', 'Where do I put my restaurant name and address?')
@section('description', 'Your name goes on the brand form, your address goes on the location form, and the menu belongs to the location.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'Where do I put my restaurant name and address?',
        'prologueLead' => 'In two places, and they are different on purpose: the name your guests read is on the brand form, the address they walk to is on the location form.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        You fill in the brand once; you add a location for every place you serve from. The
        split is not paperwork — a second branch later reuses the same menu but needs its own
        address, its own tables and its own printed codes.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-address-name" aria-labelledby="help-address-name-heading" class="flex flex-col gap-3">
        <h2 id="help-address-name-heading" class="text-2xl font-bold">The name your guests read</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>On the home screen, use <strong>Name your restaurant</strong>.</li>
            <li>
                On <strong>Create your brand</strong>, fill in <strong>Brand name</strong> —
                this is the name printed on your table cards and shown at the top of your
                menu.
            </li>
            <li>
                Pick your <strong>Main market</strong>. Zabuno sets your time zone and
                <strong>Currency</strong> from it, and you can change both afterwards.
            </li>
            <li>Save.</li>
        </ol>
        <p class="text-fg-secondary">
            Nothing here is final. The screen says so itself: you can add locations and menus
            in the next steps, and change any of this later.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-location" aria-labelledby="help-address-location-heading" class="flex flex-col gap-3">
        <h2 id="help-address-location-heading" class="text-2xl font-bold">The place guests scan from</h2>
        <p class="text-fg-secondary">
            A location is a real address: it holds the street, the opening hours and the time
            zone your menu is served under. Your tables and your printed codes belong to it.
        </p>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Back on the home screen, use <strong>Add your location</strong>.</li>
            <li>
                On <strong>Create your location</strong>, four things are required:
                <strong>Display name</strong>, the country, <strong>City</strong> and
                <strong>Address line 1</strong>. A postal code and a second address line are
                optional.
            </li>
            <li>
                Tick <strong>This location has opening hours</strong> if you want your menu to
                say when you are open. A closing time earlier than the opening time means the
                next day — 18:00 to 02:00 closes at two in the morning.
            </li>
            <li>Press <strong>Create</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Opened a second branch? Open <strong>Locations</strong> and use
            <strong>Add location</strong>. Every location has its own tables and its own QR
            codes; the menu is shared.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-limits" aria-labelledby="help-address-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-address-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot start a menu before you have a location.</em> The
                <strong>Menu</strong> screen will say so and offer you the location form
                instead. A menu belongs to a location, and a location belongs to a brand.
            </li>
            <li>
                <em>You cannot change your panel address.</em> Under
                <strong>Settings</strong> → <strong>Workspace</strong> the
                <strong>Panel address</strong> is shown but locked: every link your team has
                saved depends on it.
            </li>
            <li>
                <em>The address is not a map pin.</em> Zabuno stores what you typed and prints
                it on the card; it does not look the place up or place it on a map.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-address-stuck" aria-labelledby="help-address-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-address-stuck-heading" class="text-2xl font-bold">If you typed it wrong</h2>
        <p class="text-fg-secondary">
            Nothing you type here reaches a guest until you publish, so a typo on the first day
            costs you nothing. Open <strong>Locations</strong>, use <strong>Edit</strong> on the
            card, and correct it. Guests keep seeing the last menu you published until you
            publish again.
        </p>
        <p class="text-fg-secondary">
            If the form refuses to save and you cannot see why, the missing field is named
            under the box you left empty.
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
