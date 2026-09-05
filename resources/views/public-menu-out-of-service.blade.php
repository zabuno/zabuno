<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
{{--
    SERVİS DIŞI SAAT (FF-139).

    Bu sayfa `public-not-found` DEĞİLDİR ve ondan kopyalanmış gibi görünse de
    ayrı durmak zorundadır: o sayfa "burada menü yok" der, bu sayfa "menü var,
    bu saatte servis edilmiyor" der. Masadaki misafir için aradaki fark kalkıp
    gitmekle personele sormak arasındaki farktır.

    BOŞ MENÜ ÇİZİLMEZ. Kategorisiz bir menü iskeleti göstermek, restoranın
    menüsünü sildiğini sandırırdı; ürün bilmediğini söyler, uydurmaz.

    `noindex, follow`: arama motoruna geçici bir hâl indekslettirmenin anlamı
    yok, ama menünün kalıcı adresi hâlâ geçerli olduğu için bağlantılar
    izlenmeye açık kalır.
--}}
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text['title'] }}</title>
    <meta name="robots" content="noindex, follow">
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

        .brand {
            margin: 0 0 0.5rem;
            font-size: 0.875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--qr-muted);
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

        .next-service {
            margin-top: 1rem;
            color: var(--qr-fg);
            font-weight: 600;
        }
    </style>
</head>
<body>
{{-- Durum MAKİNEYE de okunur biçimde yazılır: testler ve ölçüm, hangi hâlin
     çizildiğini metnin çevirisine bakmadan ayırt edebilmeli. --}}
<main role="main" data-guest-state="out-of-service">
    @if ($brandName !== '')
        {{-- Marka adı, misafirin doğru restoranda olduğunu görmesi için:
             adsız bir "servis dışı" ekranı, yanlış kod okuttum sanısı verirdi. --}}
        <p class="brand">{{ $brandName }}</p>
    @endif
    <h1>{{ $text['heading'] }}</h1>
    <p>{{ $text['body'] }}</p>
    @isset($text['nextService'])
        {{-- Saat YALNIZ gerçek bir geçişten geldiyse basılır; anahtar yoksa
             satır hiç çizilmez (bkz. `GuestText::outOfService`). --}}
        <p class="next-service">{{ $text['nextService'] }}</p>
    @endisset
</main>
</body>
</html>
