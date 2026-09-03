@extends('public.layout')

@section('title', 'Restaurant menu & workspace')
@section('description', "Zabuno gives your team a shared workspace to manage a restaurant's menu and catalog, publish it as a stable QR-linked page, and keep it updated as things change.")

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl px-4 py-10">
        <section class="flex flex-col gap-6">
            <h1 class="text-3xl font-bold" style="font-size: var(--font-size-display)">
                Run your restaurant's menu and workspace from one place
            </h1>
            <p class="max-w-2xl text-fg-secondary">
                Zabuno gives your team a shared workspace to manage a restaurant's menu and
                catalog, publish it as a stable QR-linked page, and keep it updated as things
                change.
            </p>
            <nav aria-label="Account actions" class="flex flex-wrap gap-3">
                <a href="/app" class="inline-flex items-center rounded bg-action px-4 py-2 font-medium text-white hover:underline">Open workspace app</a>
                <a href="/login" class="inline-flex items-center rounded border border-border px-4 py-2 font-medium text-fg hover:underline">Log in</a>
                <a href="/register" class="inline-flex items-center rounded border border-border px-4 py-2 font-medium text-fg hover:underline">Create account</a>
            </nav>
        </section>

        <hr class="my-10 border-border" role="separator">

        <section id="features" aria-labelledby="features-heading" class="flex flex-col gap-6">
            <h2 id="features-heading" class="text-2xl font-bold">Features</h2>
            <div class="grid gap-6" style="grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr))">
                <div>
                    <h3 class="font-semibold">Restaurant &amp; workspace context</h3>
                    <p class="mt-1 text-fg-secondary">
                        Keep a restaurant's workspace, team, and settings organized in one
                        tenant-scoped place.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold">Menu &amp; catalog operations</h3>
                    <p class="mt-1 text-fg-secondary">
                        Create and edit menu items, categories, and catalog details from the
                        workspace app.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold">Publication &amp; stable QR</h3>
                    <p class="mt-1 text-fg-secondary">
                        Publish a menu to a stable, shareable page that a printed QR code can
                        keep pointing to.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold">Media intake &amp; analytics</h3>
                    <p class="mt-1 text-fg-secondary">
                        Media uploads go through quarantined media intake and review before they
                        are available, alongside basic usage analytics for the published page.
                    </p>
                </div>
            </div>
        </section>

        <hr class="my-10 border-border" role="separator">

        <section id="how-it-works" aria-labelledby="how-it-works-heading" class="flex flex-col gap-6">
            <h2 id="how-it-works-heading" class="text-2xl font-bold">How it works</h2>
            <ol class="flex flex-col gap-4">
                <li><strong>Set up</strong> — complete your workspace and restaurant setup.</li>
                <li><strong>Build the menu</strong> — add categories, items, prices, visibility, and allergens to your catalog.</li>
                <li><strong>Publish &amp; get a QR</strong> — publish the menu to a stable page with a QR code.</li>
                <li><strong>Update anytime</strong> — edit the menu and the published page and QR code stay the same.</li>
            </ol>
        </section>

        <hr class="my-10 border-border" role="separator">

        @include('public.partials.pricing')

        <hr class="my-10 border-border" role="separator">

        <section id="faq" aria-labelledby="faq-heading" class="flex flex-col gap-4">
            <h2 id="faq-heading" class="text-2xl font-bold">FAQ</h2>
            <dl class="flex flex-col gap-4">
                <div>
                    <dt class="font-semibold">What is Zabuno?</dt>
                    <dd class="mt-1 text-fg-secondary">
                        A workspace app for managing a restaurant's menu and catalog and
                        publishing it to a stable QR-linked page.
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold">Do I need an account to try it?</dt>
                    <dd class="mt-1 text-fg-secondary">Yes, create an account or log in to open the workspace app.</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ $st['faqCostQuestion'] }}</dt>
                    <dd class="mt-1 text-fg-secondary">
                        <a class="underline underline-offset-2" href="/pricing">{{ $st['pricingHeading'] }}</a>
                        — {{ $st['faqCostAnswer'] }}
                    </dd>
                </div>
            </dl>
        </section>

        <hr class="my-10 border-border" role="separator">

        <section id="contact" aria-labelledby="contact-heading" class="flex flex-col gap-4">
            <h2 id="contact-heading" class="text-2xl font-bold">{{ $st['contactHeading'] }}</h2>
            <p class="text-fg-secondary">
                {{ $st['homeContactLead'] }}
                <a class="underline underline-offset-2" href="/contact">{{ $st['homeContactCta'] }}</a>
            </p>
        </section>
    </main>
@endsection
