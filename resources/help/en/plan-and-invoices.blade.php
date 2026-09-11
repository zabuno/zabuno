@section('title', 'How do I change or cancel my plan, and where is my invoice?')
@section('description', 'Everything about money is on one screen: your plan, the cancel button, and a numbered document for every payment.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'How do I change or cancel my plan, and where is my invoice?',
        'prologueLead' => 'All of it is on one screen: Settings → Plan &amp; billing. Your plan at the top, the buttons that change it under that, and every payment as a document you can download.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        <strong>Current plan</strong> tells you which plan you are on and the date it runs
        until. Nothing here touches what your guests see: the menu behind a printed code keeps
        showing exactly what you published, before and after any of this.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-billing-change" aria-labelledby="help-billing-change-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-change-heading" class="text-2xl font-bold">Moving to another plan</h2>
        <p class="text-fg-secondary">
            Moving up and moving down are different, because one of them costs money today and
            the other does not.
        </p>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <strong>Up:</strong> pay for the bigger plan under <strong>Subscribe</strong>.
                It starts as soon as the payment succeeds — you do not wait for the month to
                end.
            </li>
            <li>
                <strong>Down:</strong> use <strong>Move to a cheaper plan</strong> and
                <strong>Schedule the change</strong>. It takes effect when the period you have
                already paid for ends. Before you confirm, the screen lists
                <strong>You will lose on …</strong> — by name, with the date.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-cancel" aria-labelledby="help-billing-cancel-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-cancel-heading" class="text-2xl font-bold">Cancelling</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                Press <strong>Cancel subscription</strong> and read the date it gives you. Your
                plan stays fully active until then; only the renewal stops.
            </li>
            <li>
                Confirm with <strong>Yes, cancel</strong>, or step back with
                <strong>Keep my subscription</strong>.
            </li>
        </ol>
        <p class="text-fg-secondary">
            Changed your mind before that date? <strong>Undo the cancellation</strong> puts it
            back, and the screen says it plainly:
            <em>you will not be asked to pay again.</em>
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-invoices" aria-labelledby="help-billing-invoices-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-invoices-heading" class="text-2xl font-bold">Your invoice</h2>
        <p class="text-fg-secondary">
            Scroll to <strong>Invoices</strong>. Every collected payment is there as a numbered
            document, and <strong>Download PDF</strong> gives you the file your accountant
            wants.
        </p>
        <p class="text-fg-secondary">
            A document is never edited and never deleted. A refund is recorded as a separate
            <strong>Credit note</strong> rather than by changing the original — that is what
            makes the numbering trustworthy.
        </p>
        <p class="text-fg-secondary">
            Fill in <strong>Billing details</strong> — company name, tax number, tax office,
            address — before you pay. A payment made before that has a document with the seller
            fields still empty, and the screen says so instead of hiding it.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-limits" aria-labelledby="help-billing-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
        <ul class="flex list-disc flex-col gap-2 ps-5 text-fg-secondary">
            <li>
                <em>You cannot get money back for a period that has started.</em> Cancelling
                stops the next payment; it does not refund the current one. The
                <a class="site-inline-action" href="/refund-policy">Cancellation and Refund Policy</a>
                says the same thing, and the screen repeats it before you confirm.
            </li>
            <li>
                <em>You cannot drop to a cheaper plan by buying it.</em> Mid-period, that
                purchase is refused rather than quietly downgrading you — use the scheduled
                change instead.
            </li>
            <li>
                <em>Zabuno does not send your invoice to the tax authority.</em> No e-Arşiv or
                e-Fatura provider is connected. The document you download is real and numbered,
                but filing it is still your accountant's job — the screen says this rather than
                letting you assume it was filed.
            </li>
            <li>
                <em>Zabuno never sees or stores your card.</em> The card details are typed on
                the payment provider's own page, not on ours.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-billing-trouble" aria-labelledby="help-billing-trouble-heading" class="flex flex-col gap-3">
        <h2 id="help-billing-trouble-heading" class="text-2xl font-bold">If a payment fails or is late</h2>
        <p class="text-fg-secondary">
            A failed payment charges you nothing and says why:
            <strong>Nothing was charged. You can try again.</strong> Use
            <strong>Proceed to payment</strong> when you are ready — and a payment left half
            finished shows <strong>Continue to the payment page</strong>, so it can be carried
            on instead of started over.
        </p>
        <p class="text-fg-secondary">
            If a renewal does not go through, your plan does not stop on the same day. There is
            a grace period first, and you get an email about it — subject line
            <em>“Zabuno — your payment is overdue”</em> — with two dates in it: when the period
            ended, and the last day the paid features stay on. You do not have to be watching
            the panel to find out.
        </p>
        <p class="text-fg-secondary">
            Seeing <strong>Test mode: no real money is charged</strong>? Then this installation
            is still connected to the payment provider's sandbox. Nothing you do there moves
            real money, and no real invoice comes out of it.
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
