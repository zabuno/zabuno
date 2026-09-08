{{-- Düz metin (`support-request-acknowledged` ile aynı gerekçe): bu
     e-postanın tek işi okunmak ve gerekiyorsa tek bir adrese götürmek.
     Her cümle katalogdan gelir; burada sabit metin yok. --}}
{{ $greeting }}

{{ $body }}
@if ($detail !== null)

{{ $detail }}
@endif
@if ($action !== null && $actionUrl !== null)

{{ $action }}
{{ $actionUrl }}
@endif
@if ($note !== null)

{{ $note }}
@endif
