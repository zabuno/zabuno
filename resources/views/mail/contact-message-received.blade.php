{{-- Düz metin: bir bildirim e-postasının tek işi okunmak. HTML şablonu,
     okunurluğa hiçbir şey katmadan spam puanı ve bakım yükü eklerdi. --}}
New support request {{ $reference }} ({{ $channel }}).

From: {{ $senderName }} <{{ $senderEmail }}>
Subject: {{ $requestSubject }}

{{ $body }}

--
Reply directly to this email; it goes back to the sender. Quote {{ $reference }} in your answer.
