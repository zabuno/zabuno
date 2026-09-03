@extends('public.layout')

@section('content')
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10">
        <h1 class="text-3xl font-bold">{{ $st['pricingHeading'] }}</h1>
        <p class="text-fg-secondary">{{ $st['pricingLead'] }}</p>

        @include('public.partials.pricing')
    </main>
@endsection
