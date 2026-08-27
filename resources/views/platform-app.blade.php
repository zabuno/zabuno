<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Platform Admin</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @include('partials.analytics', ['analyticsContext' => ['zabuno_surface' => 'platform']])
    @viteReactRefresh
    <!-- vite-entry: resources/js/platform.tsx -->
    @vite(['resources/css/app.css', 'resources/js/platform.tsx'])
</head>
<body>
    <div id="platform-admin-app"></div>
</body>
</html>
