<!DOCTYPE html>
{{-- PERSONA: superadmin yüzeyi lacivert zeminde çalışır (`docs/102` §5h).
     Öznitelik BURADA da yazılır ki ilk boyama doğru olsun: React yüklenene
     kadar sayfa kiracı tonunda görünüp sonra renk değiştirirdi. --}}
<html data-persona="platform" lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['engineeringTitle'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @include('partials.analytics', ['analyticsContext' => ['zabuno_surface' => 'engineering']])
    @viteReactRefresh
    <!-- vite-entry: resources/js/engineering.tsx -->
    @vite(['resources/css/app.css', 'resources/js/engineering.tsx'])
</head>
<body>
    <div id="engineering-app"></div>
</body>
</html>
