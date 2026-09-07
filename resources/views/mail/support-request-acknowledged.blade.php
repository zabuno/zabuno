{{-- Düz metin (`contact-message-received` ile aynı gerekçe): bir alındı
     e-postasının tek işi okunmak ve referansı elde tutmak. Her cümle
     katalogdan gelir; burada sabit metin yok. --}}
{{ $greeting }}

{{ $received }}
{{ $subjectLine }}

{{ $keep }}
@if ($commitment !== null)

{{ $commitment }}
@endif
@if ($panel !== null)

{{ $panel }}
@endif
@if ($reply !== null)

{{ $reply }}
@endif
