<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['titleEmailVerified'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @viteReactRefresh
    @include('partials.font-preload')
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div id="auth-app" data-auth-view="verified"></div>
</body>
</html>
