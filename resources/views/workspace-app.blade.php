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
    {{--
        CİHAZIN KENDİ STİLİ, CİHAZIN KENDİ PAKETİYLE BİRLİKTE (`docs/151`).

        `docs/54` JavaScript'i cihaza göre ayırmıştı; stil hâlâ tekti ve
        masaüstünün kendine ait bir kuralı yoktu. Artık masaüstü belgesi
        ikinci bir stil dosyası ister, telefon belgesi istemez — yani
        telefon o dosyanın tek baytını indirmez.

        Liste `entryFor` gibi türetilmez, açıkça yazılır: Vite giriş adları
        derleme zamanında bilinmek zorunda ve üretilmiş bir ad, olmayan bir
        girişi sessizce isteyebilirdi.
    --}}
    @vite(array_filter([
        'resources/css/app.css',
        $zabunoDevice === \App\Support\Device\DeviceClass::Desktop
            ? 'resources/css/app-desktop.css'
            : null,
        $zabunoDevice->entryFor('workspace'),
    ]))
</head>
{{--
    CİHAZ KARARI BELGEDE GÖRÜNÜR.

    Masaüstü stil katmanı bütün kurallarını `[data-device='desktop']`
    kapsamında yazar. Nitelik olmasaydı katman yalnız dosyanın varlığına
    güvenirdi; Storybook, statik dışa aktarım ya da bir önizleme yüzeyi
    bütün CSS'i tek belgeye topladığında 320 tabanı sessizce masaüstü
    yoğunluğuna dönerdi.

    Nitelik bir tarayıcı ölçümü DEĞİL: değeri sunucudan gelir
    (`App\Support\Device\DeviceClass`).
--}}
<body class="app-shell-body" data-device="{{ $zabunoDevice->value }}">
    <div id="app"></div>
</body>
</html>
