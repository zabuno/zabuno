<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zabuno — Platform Admin</title>
    @include('partials.theme-bootstrap')
    @viteReactRefresh
    <!-- vite-entry: resources/js/platform.tsx -->
    @vite(['resources/css/app.css', 'resources/js/platform.tsx'])
</head>
<body>
    <div id="platform-admin-app"></div>
</body>
</html>
