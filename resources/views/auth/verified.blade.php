<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Email verified</title>
    @include('partials.theme-bootstrap')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div id="auth-app" data-auth-view="verified"></div>
</body>
</html>
