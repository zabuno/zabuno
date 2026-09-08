{{-- Düz metin: bir bildirim e-postasının tek işi okunmak. --}}
Someone from the Zabuno platform team opened a support access session for {{ $workspaceName }}.

Started: {{ $startedAt }}
Ends automatically: {{ $expiresAt }}
Reason given: {{ $reason }}
@if ($actorEmail !== null)
Opened by: {{ $actorEmail }}
@endif

The session is read-only: nothing can be changed, paid, published, invited or deleted while it is open.
You can see this record, and every earlier one, under Settings → Audit trail in your Zabuno panel.
