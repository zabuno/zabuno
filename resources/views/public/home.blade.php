@extends('public.layout')

{{-- METİN KATALOGDAN gelir, Blade'e gömülmez (`docs/100` Faz 2).
     Bir dize şablonda kaldığı sürece sahibi onu hiçbir PO dosyasında
     bulamaz ve çeviremez; borç `lang/untranslatable-debt.json` içinde
     sayılıyordu. --}}
@section('title', $st['homeMetaTitle'])
@section('description', $st['homeMetaDescription'])

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl px-4 py-10">
        <section class="flex flex-col gap-6">
            <h1 class="text-3xl font-bold" style="font-size: var(--font-size-display)">
                {{ $st['homeHeroHeading'] }}
            </h1>
            <p class="max-w-2xl text-fg-secondary">{{ $st['homeHeroLead'] }}</p>
            <nav aria-label="{{ $st['homeHeroActionsLabel'] }}" class="flex flex-wrap gap-3">
                <a href="/app" class="inline-flex items-center rounded bg-action px-4 py-2 font-medium text-white hover:underline">{{ $st['homeOpenApp'] }}</a>
                <a href="/login" class="inline-flex items-center rounded border border-border px-4 py-2 font-medium text-fg hover:underline">{{ $st['navLogin'] }}</a>
                <a href="/register" class="inline-flex items-center rounded border border-border px-4 py-2 font-medium text-fg hover:underline">{{ $st['navRegister'] }}</a>
            </nav>
        </section>

        <hr class="my-10 border-border" role="separator">

        <section id="features" aria-labelledby="features-heading" class="flex flex-col gap-6">
            <h2 id="features-heading" class="text-2xl font-bold">{{ $st['homeFeaturesHeading'] }}</h2>
            <div class="grid gap-6" style="grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr))">
                @foreach ([
                    ['title' => $st['homeFeatureWorkspaceTitle'], 'body' => $st['homeFeatureWorkspaceBody']],
                    ['title' => $st['homeFeatureMenuTitle'], 'body' => $st['homeFeatureMenuBody']],
                    ['title' => $st['homeFeaturePublicationTitle'], 'body' => $st['homeFeaturePublicationBody']],
                    ['title' => $st['homeFeatureMediaTitle'], 'body' => $st['homeFeatureMediaBody']],
                ] as $feature)
                    <div>
                        <h3 class="font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-1 text-fg-secondary">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <hr class="my-10 border-border" role="separator">

        <section id="how-it-works" aria-labelledby="how-it-works-heading" class="flex flex-col gap-6">
            <h2 id="how-it-works-heading" class="text-2xl font-bold">{{ $st['homeHowItWorksHeading'] }}</h2>
            <ol class="flex flex-col gap-4">
                @foreach ([
                    ['title' => $st['homeStepSetupTitle'], 'body' => $st['homeStepSetupBody']],
                    ['title' => $st['homeStepBuildTitle'], 'body' => $st['homeStepBuildBody']],
                    ['title' => $st['homeStepPublishTitle'], 'body' => $st['homeStepPublishBody']],
                    ['title' => $st['homeStepUpdateTitle'], 'body' => $st['homeStepUpdateBody']],
                ] as $step)
                    <li><strong>{{ $step['title'] }}</strong> — {{ $step['body'] }}</li>
                @endforeach
            </ol>
        </section>

        <hr class="my-10 border-border" role="separator">

        @include('public.partials.pricing')

        <hr class="my-10 border-border" role="separator">

        <section id="faq" aria-labelledby="faq-heading" class="flex flex-col gap-4">
            <h2 id="faq-heading" class="text-2xl font-bold">{{ $st['homeFaqHeading'] }}</h2>
            <dl class="flex flex-col gap-4">
                <div>
                    <dt class="font-semibold">{{ $st['homeFaqWhatQuestion'] }}</dt>
                    <dd class="mt-1 text-fg-secondary">{{ $st['homeFaqWhatAnswer'] }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ $st['homeFaqAccountQuestion'] }}</dt>
                    <dd class="mt-1 text-fg-secondary">{{ $st['homeFaqAccountAnswer'] }}</dd>
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
