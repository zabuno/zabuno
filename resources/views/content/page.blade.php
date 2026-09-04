<!DOCTYPE html>
{{-- YAYINLANMIŞ kurumsal sayfa — FF-117.

     Bugün içerik blokları henüz yok; bu şablon yalnız kapının "yayınlandı"
     dalının gerçekten çalıştığını gösterir. Boş bir vaat vermemek için sayfa
     yalnız kendi başlığını ve kütükteki kimliğini yazar; içerik modeli
     geldiğinde blokları burası çizecek. --}}
<html lang="{{ \App\Support\Localization\DocumentLocale::tag($page->locale) }}" dir="{{ \App\Support\Localization\DocumentLocale::direction($page->locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    <link rel="canonical" href="{{ url($page->canonical_path) }}">
</head>
<body>
<main role="main">
    <h1>{{ $page->title }}</h1>
</main>
</body>
</html>
