<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['titleWorkspace'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @include('partials.analytics', ['analyticsContext' => [
        'zabuno_surface' => 'workspace',
        'zabuno_tenant_slug' => (string) (request()->route('workspace') ?? ''),
    ]])
    @viteReactRefresh
@php
        // Hangi paketin yükleneceğine SUNUCU karar verir. Tarayıcıya inen
        // JavaScript, o cihaz için yazılmış olandır; diğerinin kodu hiç
        // indirilmez (docs/54).
        $zabunoDevice = request()->attributes->get(
            \App\Http\Middleware\NegotiateDeviceClass::ATTRIBUTE,
        ) ?? \App\Support\Device\DeviceClass::detect(request());
    @endphp
    @include('partials.font-preload')
    @vite(['resources/css/app.css', $zabunoDevice->entryFor('workspace')])
</head>
<body class="app-shell-body">
    <div id="app"></div>
</body>
</html>
