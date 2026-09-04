<!DOCTYPE html>
{{-- ZABUNO SERVICE PASS — FF-117, yönerge §8.

     Bir restoran mutfağındaki servis fişi: hangi sayfa, hangi aşamada, en son
     ne zaman dokunuldu. Sakin, kurumsal ve İKONSUZ (yönerge madde 10).

     Kritik metin ve çıkış bağlantıları JavaScript'e BAĞLI DEĞİLDİR: bu sayfa
     çoğunlukla 404 gövdesi olarak sunulur ve bir tarayıcı betiği çalıştırmasa
     bile ziyaretçi ne olduğunu okuyabilmeli, çıkabilmeli.

     Sahte ilerleme yüzdesi ve uydurma geri sayım YOK: tutulmayacak bir söz,
     hiç söz vermemekten kötüdür. --}}
<html lang="{{ \App\Support\Localization\DocumentLocale::tag($page->locale) }}" dir="{{ \App\Support\Localization\DocumentLocale::direction($page->locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['pageState.title'] }} — {{ $page->title }}</title>
    {{-- Kapı zaten `X-Robots-Tag` gönderiyor; etiket burada TEKRARLANMAZ,
         yoksa iki kaynak bir gün ayrışır. --}}
    <style nonce="{{ $cspNonce ?? '' }}">
        :root {
            color-scheme: light dark;
            --pass-bg: #0f172a;
            --pass-panel: #f8fafc;
            --pass-fg: #0f172a;
            --pass-muted: #475569;
            --pass-line: rgba(15, 23, 42, 0.12);
            --pass-accent: #312e81;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1rem, 5vw, 3rem);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--pass-fg);
            /* Atmosfer katmanları: yavaş, düşük kontrastlı, kurumsal.
               Tek bir CSS gradyanı — WebGL yok, shader yok, üçüncü taraf
               kütüphane yok. Bu sayfa hatanın gövdesi; ağır olamaz. */
            background:
                radial-gradient(60rem 40rem at 20% -10%, #1e1b4b 0%, transparent 60%),
                radial-gradient(50rem 40rem at 90% 110%, #312e81 0%, transparent 55%),
                var(--pass-bg);
        }

        .pass {
            width: 100%;
            max-width: 32rem;
            background: var(--pass-panel);
            border-radius: 0.25rem;
            padding: clamp(1.5rem, 5vw, 2.5rem);
            /* Servis fişi hissi: üstte tırtıklı bir kenar. */
            border-top: 3px double var(--pass-accent);
        }

        .pass-brand {
            margin: 0;
            font-size: 0.75rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--pass-muted);
        }

        .pass-headline {
            margin: 0.75rem 0 0;
            font-size: clamp(1.35rem, 5vw, 1.9rem);
            line-height: 1.25;
        }

        .pass-lede {
            margin: 0.75rem 0 0;
            color: var(--pass-muted);
            line-height: 1.6;
        }

        .pass-promise {
            margin: 1.25rem 0 0;
            padding-left: 0.85rem;
            border-left: 2px solid var(--pass-accent);
            line-height: 1.55;
        }

        .pass-rows {
            margin: 1.75rem 0 0;
            border-top: 1px solid var(--pass-line);
        }

        .pass-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            justify-content: space-between;
            padding: 0.7rem 0;
            border-bottom: 1px solid var(--pass-line);
        }

        .pass-row dt {
            margin: 0;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--pass-muted);
        }

        .pass-row dd {
            margin: 0;
            font-weight: 600;
        }

        .pass-row code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.9em;
            /* Uzun bir yol dar ekranda satırı taşırmaz. */
            overflow-wrap: anywhere;
        }

        .pass-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.75rem;
        }

        .pass-actions a {
            /* Dokunma hedefi 44 px: altındaki bir bağlantı parmakla
               vurulamaz. */
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            padding: 0 1rem;
            border: 1px solid var(--pass-line);
            border-radius: 0.25rem;
            color: inherit;
            text-decoration: none;
        }

        .pass-actions a:first-child {
            background: var(--pass-accent);
            border-color: var(--pass-accent);
            color: #f8fafc;
        }

        .pass-actions a:focus-visible {
            outline: 2px solid var(--pass-accent);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
<main role="main" class="pass">
    <p class="pass-brand">{{ $st['brand'] }}</p>

    <h1 class="pass-headline">
        {{ $isMaintenance ? $st['pageState.maintenanceHeadline'] : $st['pageState.headline'] }}
    </h1>

    <p class="pass-lede">
        {{ $isMaintenance ? $st['pageState.maintenanceLede'] : $st['pageState.lede'] }}
    </p>

    @if ($page->title !== '')
        {{-- Site haritasındaki cümle bir BAŞLIK değil bir TARİF: "QR, dijital,
             mobil ve temassız menü özelliklerini tek sayfada anlatır". Onu
             "Sayfa:" satırına koymak, bir fişe paragraf yazmak olurdu. Burada
             ne olacağını anlatan bir cümle olarak duruyor; kimliği ise
             adresin kendisi. --}}
        <p class="pass-promise">{{ $page->title }}</p>
    @endif

    <dl class="pass-rows">
        <div class="pass-row">
            <dt>{{ $st['pageState.pageLabel'] }}</dt>
            {{-- Kimlik ADRESTİR: kısa, tek anlamlı ve fişe yazılabilir. --}}
            <dd><code>{{ $page->canonical_path }}</code></dd>
        </div>
        <div class="pass-row">
            <dt>{{ $st['pageState.stageLabel'] }}</dt>
            <dd>{{ $stage }}</dd>
        </div>
        @if ($page->updated_at !== null)
            <div class="pass-row">
                <dt>{{ $st['pageState.updatedLabel'] }}</dt>
                {{-- Gerçek tarih. Uydurma bir geri sayım ya da sahte bir
                     ilerleme yüzdesi yok. --}}
                <dd><time datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->toDateString() }}</time></dd>
            </div>
        @endif
    </dl>

    {{-- Çıkmaz sokak yok. --}}
    <div class="pass-actions">
        <a href="/">{{ $st['pageState.home'] }}</a>
        <a href="/pricing">{{ $st['pageState.explore'] }}</a>
        <a href="/contact">{{ $st['pageState.contact'] }}</a>
    </div>
</main>
</body>
</html>
