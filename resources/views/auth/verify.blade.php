<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Verify your email</title>
    @include('partials.theme-bootstrap')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div id="auth-app" data-auth-view="verification-pending" data-auth-email="{{ $email ?? '' }}"></div>
</body>
</html>
