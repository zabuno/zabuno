{{-- Düz metin (`support-request-acknowledged` ile aynı gerekçe). ÇERÇEVE
     katalogdan gelir; ARADAKİ gövde süperadminin o an yazdığı metindir ve
     olduğu gibi yazılır — biçimlenmez, kısaltılmaz. --}}
{{ $greeting }}

{{ $intro }}

{{ $body }}

{{ $closing }}
@if ($reply !== null)

{{ $reply }}
@endif
