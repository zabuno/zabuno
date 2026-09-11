@section('title', 'A guest scored a dish badly. Can I remove it?')
@section('description', 'No — and that is deliberate. What you can do is reply, and your reply appears under the dish on the guest’s own screen.')

{{-- KABUĞUN BIRAKTIĞI BOŞLUK, KURUMSAL YÜZEY DİLİYLE.
     Sayfa gövdesi okuma sütununda; bant tam kanamalı. Dolgu BİR kez
     uygulanır (`.site-measure-prose`): iç içe kapların dolgusu birikirse
     320 pikselde metin dar bir şeride sıkışır. --}}
<main id="main-content" class="site-page">
    {{-- SAHNE, SAKİN KİPTE (`docs/146` §12). Makale sayfası: buraya gelen
         kişi keşfetmiyor, CEVAP ARIYOR. Başlık bandın İÇİNDE; sayfada
         ikinci bir h1 yok. --}}
    @include('public.partials.prologue', [
        'prologueHeading' => 'A guest scored a dish badly. Can I remove it?',
        'prologueLead' => 'No, and that is deliberate. What you can do is answer — your reply appears under the dish on the guest’s own screen.',
        'prologueVariant' => 'calm',
        'prologueMeasure' => 'site-measure-prose',
    ])

    <div class="site-measure-prose site-page-body">
    <p class="text-fg-secondary">
        <a class="site-inline-action" href="/help">Back to your first 15 minutes</a>
    </p>

    <p class="text-fg-secondary">
        An average the restaurant could delete would be an advertisement, not a measurement —
        and guests know the difference. The score is theirs. The reply is yours.
    </p>

    <hr class="border-border" role="separator">

    <section id="help-ratings-reply" aria-labelledby="help-ratings-reply-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-reply-heading" class="text-2xl font-bold">Answering a score</h2>
        <ol class="flex list-decimal flex-col gap-2 ps-5 text-fg-secondary">
            <li>Open <strong>Ratings</strong> and pick the branch and the menu.</li>
            <li>
                Find the dish. You see its score and <strong>Votes so far</strong> — how many
                guests have voted.
            </li>
            <li>
                Write under <strong>Your reply</strong>. The screen says it plainly:
                <em>guests read this under the dish, on their own screen.</em>
            </li>
            <li>Press <strong>Publish reply</strong>.</li>
        </ol>
        <p class="text-fg-secondary">
            Changed your mind about the wording? <strong>Update reply</strong> replaces it, and
            <strong>Withdraw reply</strong> takes it down. Guests see it labelled
            <strong>From the restaurant</strong>, so nobody mistakes your answer for another
            guest's.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-threshold" aria-labelledby="help-ratings-threshold-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-threshold-heading" class="text-2xl font-bold">Why some dishes show no number</h2>
        <p class="text-fg-secondary">
            A dish with only a handful of votes says <strong>Not enough ratings yet</strong>
            instead of showing a score. A few guests are not a verdict, and a number built on
            them would say something your guests never said.
        </p>
        <p class="text-fg-secondary">
            Two things have to be true before a score appears: <em>enough people</em> must have
            voted, and those votes must be <em>recent enough</em> to still carry weight. The
            second one matters more than it sounds — without it, a dish nobody has ordered in
            two years would keep an old score on the screen forever.
        </p>
        <p class="text-fg-secondary">
            Below the threshold you do not see zero stars. Zero is a measurement, and it cannot
            stand in for the unknown: writing zero would show a dish nobody has rated as a bad
            dish.
        </p>
        <p class="text-fg-secondary">
            The screen also names the <strong>Scoring method</strong> that produced the numbers
            and when they were <strong>Worked out</strong>. If a score moves, that tells you
            whether it moved because new guests voted or because the way scores are worked out
            changed.
        </p>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-limits" aria-labelledby="help-ratings-limits-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-limits-heading" class="text-2xl font-bold">What you cannot do here</h2>
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
                <em>You cannot see who left a score.</em> Guests do not sign in to rate, so
                there is no name to show you.
            </li>
            <li>
                <em>Ratings are not part of every role.</em> If the screen says
                <strong>Ratings are not part of your role</strong>, ask the workspace owner —
                that is the role doing its job, not a fault.
            </li>
        </ul>
    </section>

    <hr class="border-border" role="separator">

    <section id="help-ratings-changed" aria-labelledby="help-ratings-changed-heading" class="flex flex-col gap-3">
        <h2 id="help-ratings-changed-heading" class="text-2xl font-bold">If the score is about a dish you changed</h2>
        <p class="text-fg-secondary">
            A rating belongs to the dish, and it stays with the dish through price changes and
            new photos. If you have genuinely replaced a dish with a different one, add it as a
            new item on the <strong>Menu</strong> rather than renaming the old one — the new
            dish starts with no votes, which is the honest place to start.
        </p>
        <p class="text-fg-secondary">
            If a rating breaks the law where you are — a threat, or somebody's personal data —
            that is not a product setting.
            <a class="site-inline-action" href="/contact">Write to us</a> and say which dish and
            roughly when.
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
