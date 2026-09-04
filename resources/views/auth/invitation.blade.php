<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['titleInvitation'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div
        id="auth-app"
        data-auth-view="invitation-accept"
        data-invitation-status="{{ $state }}"
        data-authenticated="{{ $authenticated ? 'true' : 'false' }}"
        data-login-url="{{ $loginUrl }}"
        data-workspace-name="{{ $workspaceName }}"
        data-invited-email="{{ $invitedEmail }}"
        data-role="{{ $role }}"
        data-accept-url="{{ $acceptUrl }}"
    ></div>
</body>
</html>
