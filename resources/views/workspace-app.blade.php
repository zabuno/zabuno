<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Workspace</title>
    @include('partials.theme-bootstrap')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/workspace.tsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
