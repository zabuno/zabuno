@php
    $zabunoAnalytics = \App\Support\Analytics\AnalyticsConfiguration::fromConfig();
@endphp
@if ($zabunoAnalytics->isEnabled())
{{--
    Ölçüm dikişi. Sıra ÖNEMLİDİR ve tesadüf değildir:

    1. `dataLayer` yaratılır ve tenant bağlamı İÇİNE yazılır,
    2. sonra Google Tag Manager yüklenir.

    Ters sırada, GTM'in gördüğü ilk olayın tenant alanı BOŞ olurdu. Tek bir
    boş alan raporu bozmaz gibi görünür; ama boş kalan olay her zaman aynı
    olaydır — ziyaretçinin gördüğü İLK sayfa. Yani tam da en çok ölçmek
    istediğin an, hiçbir restorana ait olmayan bir satır olurdu.

    Buraya YALNIZ tenant ve yüzey kimliği yazılır. Kişisel veri (e-posta, ad,
    telefon) hiçbir koşulda `dataLayer`'a girmez: dataLayer'ın içeriği GTM
    üzerinden üçüncü taraflara akar ve oraya giren veri geri alınamaz
    (docs/46 §4).
--}}
<script nonce="{{ $cspNonce ?? '' }}">
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(@json($zabunoAnalytics->dataLayerPayload(
    $analyticsContext ?? [],
    \App\Support\Localization\DocumentLocale::tag(),
)));
</script>
<script nonce="{{ $cspNonce ?? '' }}">
(function (w, d, s, l, i, n) {
    w[l] = w[l] || [];
    w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
    var f = d.getElementsByTagName(s)[0];
    var j = d.createElement(s);
    j.async = true;
    j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i;
    // `strict-dynamic` altında GTM'in enjekte ettiği etiketler güvenilir
    // sayılır; nonce'u ayrıca taşımak şart değildir. Yine de veriyoruz:
    // `strict-dynamic` desteklemeyen eski bir tarayıcı host listesine düşer
    // ve orada nonce tek geçerli kanıttır.
    j.setAttribute('nonce', n);
    f.parentNode.insertBefore(j, f);
})(window, document, 'script', 'dataLayer', @json($zabunoAnalytics->containerId()), @json($cspNonce ?? ''));
</script>
@endif
