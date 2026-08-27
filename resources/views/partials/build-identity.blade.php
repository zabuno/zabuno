@php
    $zabunoBuild = \App\Support\Build\BuildIdentity::resolve();
@endphp
{{--
    Sunucunun çalıştırdığı kaynağın kimliği.

    Bu etiketler bir "sürüm rozeti" değil, bir KARŞILAŞTIRMANIN yarısıdır.
    Diğer yarısı tarayıcıya inen paketin içine gömülüdür. İkisi ayrıştığı an
    sayfayı üreten kaynak ile ekranda çalışan JavaScript farklı commit'lerden
    geliyor demektir — ve bu, hiçbir belirti vermeden olur: arayüz gayet
    normal görünür, yalnızca YANLIŞ sürümü gösterir.

    Sürümün HTML'de açıkça durması bir sızıntı değildir: depo açık kaynaktır,
    commit kimlikleri zaten herkese açıktır.
--}}
<meta name="zabuno-build-revision" content="{{ $zabunoBuild->revision() ?? '' }}">
<meta name="zabuno-build-stale" content="{{ $zabunoBuild->isBuildStale() ? 'true' : 'false' }}">
<meta name="zabuno-build-banner" content="{{ config('build.banner') ? 'true' : 'false' }}">
{{--
    Vite geliştirme sunucusu çalışıyor mu?

    Bu bilgi istemcide TAHMİN EDİLMEZ, sunucudan bildirilir. Tarayıcı
    tarafında `import.meta.hot` bakmak yanlış bir vekildir: test
    koşucusunda da tanımlıdır, yani kontrolü sınanamaz hâle getirir —
    ve sınanamayan bir kontrol güvenilemeyen bir kontroldür. Sunucu ise
    sıcak dosyanın varlığından bunu KESİN bilir.
--}}
<meta name="zabuno-build-hot" content="{{ \Illuminate\Support\Facades\Vite::isRunningHot() ? 'true' : 'false' }}">
