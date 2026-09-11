@section('title', 'How do I let my staff in, and what can each one do?')
@section('description', 'Invite people by email and give each one a role. Everyone sees only what their job needs.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'How do I let my staff in, and what can each one do?',
        'prologueLead' => 'You invite them by email and pick a role. The role decides what they see — and the screen itself lists what each one can and cannot do.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        Nobody has to be trusted with your prices to mark a dish sold out, and nobody sees your
        billing because they help with the menu. That is the whole point of the roles.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-team-invite" aria-labelledby="help-team-invite-heading" class="flex flex-col gap-3">
        <h2 id="help-team-invite-heading" class="text-2xl font-bold">Inviting someone</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Team</strong>.</li>
            <li>Type their address under <strong>Invite by email</strong>.</li>
            <li>Choose a <strong>Role</strong>. Under each one, a single line says what it can do.</li>
            <li>Press <strong>Invite</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            They get an email with a link. Until they use it, the invitation sits under
            <strong>Pending invitations</strong> and you can <strong>Send again</strong> or
            <strong>Cancel invitation</strong>. Sending again replaces the link — only the
            newest email works.
        </p>
        <p class="text-fg-secondary">
            The role offered first is deliberately one of the narrower ones. In a hurry it is
            the one you will accept without reading, so it is not the widest.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-roles" aria-labelledby="help-team-roles-heading" class="flex flex-col gap-3">
        <h2 id="help-team-roles-heading" class="text-2xl font-bold">What each role can do</h2>
        <p class="text-fg-secondary">
            The same list is on the screen, under <strong>What can each role do?</strong> Each
            row opens with <strong>See exactly what this role can and cannot do</strong> — and
            that panel lists the <strong>Cannot</strong> side too, which is usually the half
            you actually need.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Owner</strong> — everything: billing, team, publishing. That is you.
            </li>
            <li>
                <strong>Manager</strong> — menu, QR codes, publishing. Cannot touch billing.
                The role for whoever runs the floor without seeing what you pay.
            </li>
            <li>
                <strong>Editor</strong> — products, prices and photos. Cannot publish, so
                nothing they type reaches a table until someone else says so.
            </li>
            <li>
                <strong>Kitchen</strong> — allergens and “sold out today”. Sees nothing else.
                The right role for a phone that lives next to the pass: the person who knows
                the fish ran out should be able to say so without being able to reprice the
                menu.
            </li>
        </ul>
        <p class="text-fg-secondary">
            Pick the smallest role that lets the person do their job. Moving someone up later
            is one change on their row.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-limits" aria-labelledby="help-team-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-team-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot invite someone as Owner.</em> The screen says it plainly:
                <strong>Ownership is transferred, not given by invitation.</strong> Handing
                over the restaurant is a separate action —
                <strong>Transfer ownership</strong> on that person's row — with its own
                confirmation, because afterwards the workspace is theirs.
            </li>
            <li>
                <em>Hiding a button is not the protection.</em> What a role cannot do is
                refused by the server, not just left off the screen. A Kitchen phone that
                somehow reaches a menu-editing address is still turned away.
            </li>
            <li>
                <em>You cannot invite someone as Member.</em> That role still exists but only
                for accounts created long ago; it is read-only and no new invitation offers
                it.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-team-stuck" aria-labelledby="help-team-stuck-heading" class="flex flex-col gap-3">
        <h2 id="help-team-stuck-heading" class="text-2xl font-bold">If the invitation never arrives</h2>
        <p class="text-fg-secondary">
            First check <strong>Pending invitations</strong>: if it is still listed, the person
            has not opened the link yet. Use <strong>Send again</strong> — and tell them the
            older email no longer works.
        </p>
        <p class="text-fg-secondary">
            If the row says <strong>We cannot tell whether this email was ever sent</strong>,
            that is not a guess dressed up as a fact: the delivery result was genuinely not
            recorded. Send it again, and if the second one is also unknown, tell us — that is
            a problem on our side, not something you can fix from the screen.
        </p>
        <p class="text-fg-secondary">
            Someone who should not be there any more? Use <strong>Remove</strong> on their row.
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
