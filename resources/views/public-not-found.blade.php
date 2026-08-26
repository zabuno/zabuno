<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menü bulunamadı</title>
    <meta name="robots" content="noindex, nofollow">
    @include('partials.theme-bootstrap')
    <style nonce="{{ $cspNonce ?? '' }}">
        :root {
            color-scheme: light dark;
            --qr-bg: #ffffff;
            --qr-fg: #1f2937;
            --qr-muted: #6b7280;
        }

        :root.dark,
        :root[data-theme="dark"] {
            --qr-bg: #111827;
            --qr-fg: #f9fafb;
            --qr-muted: #9ca3af;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1rem, 5vw, 2rem);
            background: var(--qr-bg);
            color: var(--qr-fg);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        main {
            max-width: 32rem;
            text-align: center;
        }

        h1 {
            font-size: clamp(1.25rem, 5vw, 1.75rem);
            margin: 0 0 0.75rem;
        }

        p {
            margin: 0;
            color: var(--qr-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
<main role="main">
    {{-- Metin BİLEREK tek bir durumu anlatmaz: bilinmeyen, bozuk ve devre
         dışı kod aynı yanıtı alır (QR-PUBLIC-404-UNIFORM-01). Hangisi
         olduğunu söylemek, hangi kodların var olduğunu ölçülebilir yapardı. --}}
    <h1>Bu menü şu anda görüntülenemiyor</h1>
    <p>
        Karekod bir menüye bağlı değil. Lütfen restoran personeline bildirin;
        size güncel menüyü ulaştırabilirler.
    </p>
</main>
</body>
</html>
