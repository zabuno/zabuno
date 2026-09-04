@extends('public.layout')

@section('title', $title)
@section('description', $st['legalPendingDescription'])

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-3xl font-bold" style="font-size: var(--font-size-display)">{{ $title }}</h1>
        <p class="mt-6 max-w-2xl text-fg-secondary">
{{ $st['legalPending'] }}
        </p>
    </main>
@endsection
