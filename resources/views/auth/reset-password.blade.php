<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Reset password</title>
    @include('partials.theme-bootstrap')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div
        id="auth-app"
        data-auth-view="reset-password"
        data-reset-token="{{ $token }}"
        data-reset-email="{{ $email }}"
    ></div>
</body>
</html>
