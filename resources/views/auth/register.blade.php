<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $st['titleRegister'] }}</title>
    @include('partials.theme-bootstrap')
    @include('partials.build-identity')
    @viteReactRefresh
    @include('partials.font-preload')
    @vite(['resources/css/app.css', 'resources/js/auth.tsx'])
</head>
<body>
    <div id="auth-app" data-auth-view="register"></div>
    {{-- YASAL METİN SAYFAYLA BİRLİKTE GELİR (REG-LEGAL-01).

         `@json` `<`, `>`, `&`, `'` ve `"` karakterlerini kaçırır, yani metin
         içindeki bir `</script>` dizisi bu bloğu kapatamaz. Ayrı bir istek
         yok: imzalanan metin, imza anında ekranda olmalı.

         Metin verilmeden çizilirse (kabuk seviyesindeki şablon denetimleri)
         blok BOŞ çıkar ve form yine çalışır: kartlar kalıcı sayfaya düşer.
         Gerçek rotanın metni taşıdığı ayrıca sınanıyor — bu yedek, bir
         gerileme gizlesin diye değil, kabuk denetimi çökmesin diye var. --}}
    <script type="application/json" id="register-legal">@json($legal ?? ['reviewPending' => false, 'documents' => []])</script>
</body>
</html>
