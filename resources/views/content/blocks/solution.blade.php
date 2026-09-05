{{-- Zabuno çözümü. `problem` ile aynı biçim: ikisi aynı okuma ritmini
     paylaşır ve ayrı bir düzen icat etmek okuyucuyu yavaşlatırdı. --}}
<section class="flex flex-col gap-3">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    @foreach ($block->entries as $entry)
        <p class="leading-relaxed text-fg-secondary">{{ $entry->text }}</p>
    @endforeach
</section>
