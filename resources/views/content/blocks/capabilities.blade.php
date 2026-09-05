{{-- Öne çıkan yetenekler. Ad ve açıklama bir TANIM listesidir: ikisi
     arasındaki ilişki görsel değil anlamsaldır.

     İkon yok (`docs/118` E6): ayrım tipografi ve boşlukla kuruluyor. --}}
<section class="flex flex-col gap-3">
    <h2 class="text-xl font-semibold text-fg">{{ $block->heading }}</h2>
    <dl class="flex flex-col gap-4">
        @foreach ($block->entries as $entry)
            <div class="flex flex-col gap-1 border-t border-border pt-3">
                <dt class="font-semibold text-fg">{{ $entry->term }}</dt>
                <dd class="leading-relaxed text-fg-secondary">{{ $entry->text }}</dd>
            </div>
        @endforeach
    </dl>
</section>
