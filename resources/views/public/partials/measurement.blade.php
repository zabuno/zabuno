@php($zabunoMeasurementEnabled = \App\Support\Analytics\AnalyticsConfiguration::fromConfig()->isEnabled())
@if ($zabunoMeasurementEnabled)
{{--
    KAMU SAYFALARININ DÖNÜŞÜM OLAYLARI — `docs/100` Faz 3 (L3).

    Üç olay, üç gerçek soru:

      • `pricing_viewed`   — fiyatı kaç kişi gerçekten okudu?
      • `register_started` — kaydolmaya kaç kişi başladı?
      • `contact_sent`     — kaç kişi bize yazdı?

    Bugüne kadar bunların hiçbiri ölçülmüyordu: bütün kamu trafiği tek bir
    "marketing" yüzeyi olarak akıyor, "fiyatı okuyanların kaçı iletişime
    geçti" sorusu raporda cevapsız kalıyordu.

    KİŞİSEL VERİ YOK. Olaylara yalnız sayfa kimliği ve olayın adı girer;
    e-posta, ad ve mesaj hiçbir koşulda `dataLayer`'a yazılmaz — dataLayer'ın
    içeriği GTM üzerinden üçüncü taraflara akar ve oraya giren veri geri
    alınamaz (`docs/46` §4).

    Olay TIKLAMADA yazılır, sayfa yüklenmesinde değil: "kaydol düğmesine
    bastı" ile "kayıt sayfasını gördü" aynı şey değildir ve ikincisi
    ilkinden çok daha kalabalıktır.
--}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var layer = (window.dataLayer = window.dataLayer || []);

    function send(name) {
        layer.push({ event: 'zabuno_' + name, zabuno_page: @json($pageKey ?? 'unknown') });
    }

    @if (($pageKey ?? '') === 'pricing')
        send('pricing_viewed');
    @endif

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (target === null) {
            return;
        }

        // Yalnız KAYIT bağlantısı: hangi düğmenin sayıldığı adresten okunur,
        // ayrı bir işaretleyici özniteliğe gerek yok — işaretleyici bir gün
        // düşer ve kimse fark etmez, adres düşerse bağlantı zaten bozulur.
        if (new URL(target.href, window.location.origin).pathname === '/register') {
            send('register_started');
        }
    });

    var contactForm = document.querySelector('form[action="/contact"]');

    if (contactForm !== null) {
        contactForm.addEventListener('submit', function () {
            send('contact_sent');
        });
    }
})();
</script>
@endif
