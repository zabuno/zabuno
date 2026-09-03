@extends('public.layout')

{{-- Sekme ve paylaşım adı: bu sayfa tam olarak PAYLAŞILMAK için var —
     fiyatı biri arkadaşına gönderir ve "— Zabuno" hangi sayfa olduğunu
     söylemez (`docs/89`). --}}
@section('title', $st['pricingHeading'])
@section('description', $st['pricingLead'])

@section('content')
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10">
        <p class="text-fg-secondary">{{ $st['pricingLead'] }}</p>

        @include('public.partials.pricing', ['pricingHeadingTag' => 'h1'])
    </main>
@endsection
