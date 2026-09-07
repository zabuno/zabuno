<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['titlePlatform'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @include('partials.analytics', ['analyticsContext' => ['zabuno_surface' => 'platform']])
    @viteReactRefresh
    <!-- vite-entry: resources/js/platform.tsx -->
    @include('partials.font-preload')
    @vite(['resources/css/app.css', 'resources/js/platform.tsx'])
</head>
{{-- PERSONA: superadmin yüzeyi lacivert zeminde çalışır (`docs/102` §5h).
     Öznitelik `<body>` üzerindedir: `<html>` etiketi RTL kapısında birebir
     donmuş (`RTL-LOGIN-DERIVED-02`) ve portalla açılan katmanlar zaten
     `body` altına çizilir. İlk boyamada doğru olsun diye SUNUCUDAN gelir. --}}
<body data-persona="platform" class="app-shell-body">
    <div id="platform-admin-app"></div>
</body>
</html>
