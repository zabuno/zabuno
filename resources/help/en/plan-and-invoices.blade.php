@section('title', 'How do I change or cancel my plan, and where is my invoice?')
@section('description', 'Everything about money is on one screen: your plan, the cancel button, and a numbered document for every payment.')

<main class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-10">
    <div class="flex flex-col gap-2">
        <p class="text-fg-secondary">
            <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">Help articles</a>
        </p>
        <h1 class="text-3xl font-bold">How do I change or cancel my plan, and where is my invoice?</h1>
        <p class="text-fg-secondary">
            All of it is on one screen: <strong>Settings</strong> →
            <strong>Plan &amp; billing</strong>. Your current plan and its end date are at the
            top, the buttons that change it are under that, and every payment you have made is
            listed at the bottom as a document you can download.
        </p>
        <p class="text-fg-secondary" data-source-language-notice>
            Zabuno is written in English, so this article is in English too.
        </p>
    </div>

    <section id="change" aria-labelledby="change-heading" class="flex flex-col gap-3">
        <h2 id="change-heading" class="text-2xl font-bold">Moving to another plan</h2>
        <p class="text-fg-secondary">
            Moving up and moving down are different, because one of them costs money today and
            the other does not.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>Up:</em> pay for the bigger plan under <strong>Billing</strong>. It starts as
                soon as the payment succeeds — you do not wait for the month to end.
            </li>
            <li>
                <em>Down:</em> use <strong>Move to a cheaper plan</strong> and
                <strong>Schedule the change</strong>. It takes effect when the period you have
                already paid for ends. Before you confirm, the screen names what you will lose
                and on which date.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="cancel" aria-labelledby="cancel-heading" class="flex flex-col gap-3">
        <h2 id="cancel-heading" class="text-2xl font-bold">Cancelling</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Press <strong>Cancel subscription</strong>.</li>
            <li>
                Read the date it gives you. Your plan stays fully active until then; only the
                renewal stops.
            </li>
            <li>Confirm with <strong>Yes, cancel</strong>, or step back with <strong>Keep my subscription</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Changed your mind before that date? <strong>Undo the cancellation</strong> puts it
            back and you are not asked to pay again.
        </p>
        <p class="text-fg-secondary">
            Your guests are never told any of this. The menu behind a printed code keeps showing
            exactly what you published, before and after.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="invoices" aria-labelledby="invoices-heading" class="flex flex-col gap-3">
        <h2 id="invoices-heading" class="text-2xl font-bold">Your invoice</h2>
        <p class="text-fg-secondary">
            Scroll to <strong>Invoices</strong>. Every collected payment is there as a numbered
            document; <strong>Download PDF</strong> gives you the file your accountant wants. A
            document is never edited or deleted, and a refund is recorded as a separate credit
            note rather than by changing the original.
        </p>
        <p class="text-fg-secondary">
            A payment made before you filled in <strong>Billing details</strong> has no document,
            and the screen counts those for you instead of hiding them. Use
            <strong>Add billing details</strong> — company name, tax number, address — so the
            next one is complete.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="what-you-cannot-do" aria-labelledby="what-you-cannot-do-heading" class="flex flex-col gap-3">
        <h2 id="what-you-cannot-do-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot get money back for a period that has started.</em> Cancelling
                stops the next payment; it does not refund the current one. The Cancellation and
                Refund Policy says the same thing, and the screen repeats it before you confirm.
            </li>
            <li>
                <em>You cannot drop to a cheaper plan by buying it.</em> Mid-period, that purchase
                is refused rather than quietly downgrading you — use the scheduled change
                instead.
            </li>
            <li>
                <em>Zabuno does not send your invoice to the tax authority.</em> No e-Arşiv or
                e-Fatura provider is connected, and the screen says so rather than letting you
                assume it was filed.
            </li>
            <li>
                <em>Zabuno never sees or stores your card.</em> The card details are typed on the
                payment provider’s own page, not on ours.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="if-it-goes-wrong" aria-labelledby="if-it-goes-wrong-heading" class="flex flex-col gap-3">
        <h2 id="if-it-goes-wrong-heading" class="text-2xl font-bold">If a payment fails or is late</h2>
        <p class="text-fg-secondary">
            A failed payment charges you nothing and says why. Use
            <strong>Proceed to payment</strong> again when you are ready — a payment left half
            finished can be continued instead of started over.
        </p>
        <p class="text-fg-secondary">
            If the money simply does not arrive, nothing switches off that day: your features
            stay on for a grace period, the screen tells you the date, and paying brings
            everything back without anyone touching it by hand. Even after a subscription ends,
            your published menus stay online and your data is untouched.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <p class="text-fg-secondary">
        <a class="inline-flex min-h-[var(--density-hit-area-min)] items-center underline underline-offset-2" href="/help">All help articles</a>
    </p>
</main>
