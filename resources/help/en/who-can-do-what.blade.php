@section('title', 'How do I let my staff in, and what can each one do?')
@section('description', 'Invite people by email and give each one a role. Everyone sees only what their job needs.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">How do I let my staff in, and what can each one do?</h1>
        <p class="text-fg-secondary">
            You invite them by email and pick a role. The role decides what they see. Nobody has
            to be trusted with your prices to mark a dish sold out, and nobody sees your bank
            details because they help with the menu.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="invite" aria-labelledby="invite-heading" class="flex flex-col gap-3">
        <h2 id="invite-heading" class="text-2xl font-bold">Inviting someone</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Team</strong>.</li>
            <li>Type their address under <strong>Invite by email</strong>.</li>
            <li>Choose a <strong>Role</strong>. Under each one, a single line says what it can do.</li>
            <li>Press <strong>Invite</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            They get an email with a link. Until they use it, the invitation sits in your list
            and you can <strong>Send again</strong> or <strong>Cancel invitation</strong>.
            Sending again replaces the link — only the newest email works.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="roles" aria-labelledby="roles-heading" class="flex flex-col gap-3">
        <h2 id="roles-heading" class="text-2xl font-bold">What each role can do</h2>
        <p class="text-fg-secondary">
            The same list is on the screen, under <strong>What can each role do?</strong>
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Owner</strong> — everything: billing, team, publishing. That is you,
                until you hand it over.
            </li>
            <li>
                <strong>Manager</strong> — runs the daily work: menus, locations, QR codes and
                publishing. Cannot touch billing.
            </li>
            <li>
                <strong>Editor</strong> — products, prices and photos. Cannot publish, so nothing
                they type reaches a table until someone else says so.
            </li>
            <li>
                <strong>Kitchen</strong> — allergens and “sold out today”, and nothing else. The
                right role for a phone that lives next to the pass.
            </li>
        </ul>
        <p class="text-fg-secondary">
            Pick the smallest role that lets the person do their job. Moving someone up later is
            one tap on their row.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot invite a second owner.</em> Ownership is transferred, not given
                out: <strong>Transfer ownership</strong> makes that person the owner and turns
                you into an editor. The new owner can hand it back.
            </li>
            <li>
                <em>Only the owner can remove people.</em> A manager who tries is told to ask
                you, by name, instead of getting a button that fails.
            </li>
            <li>
                <em>You cannot give someone one branch and not another.</em> A role covers the
                whole workspace today.
            </li>
            <li>
                <em>You cannot see whether they read the email.</em> Zabuno can tell you the mail
                provider accepted it — it cannot see an inbox.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If the invitation never arrives</h2>
        <p class="text-fg-secondary">
            The screen is honest about this: if the email did not go out it says so, and the
            invitation is still valid — use <strong>Send again</strong>. If it went out and they
            still cannot find it, look in their spam folder before you do anything else.
        </p>
        <p class="text-fg-secondary">
            Someone left the restaurant? Open <strong>Team</strong>, find their row and use
            <strong>Remove</strong>. Everything they wrote stays; only the person goes.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
