<?php

declare(strict_types=1);

namespace App\Infrastructure\Content;

use App\Application\Content\UseCase\ResolvePageDelivery;
use App\Domain\Content\PageEnvironment;
use App\Domain\Url\UrlNormalizer;
use App\Models\ContentPage;
use Throwable;

/**
 * Bir kurumsal adresin BAŞKA BİR DİLDEKİ karşılığı — `docs/120` §5 madde 7.
 *
 * ── Neden var ────────────────────────────────────────────────────────────
 *
 * Dil değiştirici bir çerez yazar ve ziyaretçiyi GELDİĞİ ADRESE geri
 * gönderir. Yaşayan rotalar için (`/pricing`, `/help`) bu doğru davranıştır:
 * o sayfalar tek bir adreste yaşar ve dili çerezden okur.
 *
 * Kurumsal kütük sayfaları için ise YANLIŞTI ve ölçülebilir biçimde yanlıştı:
 * `/en/product/qr-menu` üzerinde Türkçeyi seçen bir ziyaretçi aynı İngilizce
 * adrese geri dönüyordu. Sayfanın dili adresin kendisinde yaşıyor
 * (`ShowCorporatePageController` metni KAYDIN `locale` alanından okur), yani
 * ziyaretçi Türkçeyi seçmiş olmasına rağmen İngilizce metni okumaya devam
 * ediyordu — üstelik kabuk Türkçeye dönüyordu, dolayısıyla sayfa iki dilli
 * görünüyordu.
 *
 * ── Karşılık ANAHTARDAN bulunur, adresten değil ──────────────────────────
 *
 * `/tr/urun/qr-menu/` ile `/en/product/qr-menu/` arasında mekanik bir bağ
 * YOKTUR ve olmamalıdır: slug çevrilebilir bir alandır (`docs/119` §10.4).
 * Adresten türeten bir eşleme, slug çevrildiği gün sessizce kopardı. Bu
 * yüzden yol önce bir kütük satırına, satır bir `page_key`e ve anahtar hedef
 * dilin satırına çözülür — `ContentPage::alternates()` ile aynı ilke.
 *
 * ── Taslak sızmaz ────────────────────────────────────────────────────────
 *
 * Karşılık, ziyaretçinin alacağı HTTP kodunu üreten kararın ta kendisinden
 * geçer (`ResolvePageDelivery`, `docs/129` §3). Hedef dilde yayınlanmamış ya
 * da metni yazılmamış bir sayfa bir karşılık DEĞİLDİR; böyle bir durumda
 * ziyaretçi bulunduğu adreste kalır. Onu 404'e göndermek, dil değiştirmeyi
 * cezaya çevirirdi.
 */
final class LocalisedContentPath
{
    public function __construct(
        private readonly ResolvePageDelivery $delivery,
        private readonly UrlNormalizer $normalizer,
    ) {}

    /**
     * `$path` hedef dilde açılan bir karşılığa sahipse onun adresi, değilse `null`.
     *
     * `$path` yalnız YOL olmalıdır: sorgu ve çapa çağıran tarafta ayrılır.
     * Burada ikisini de taşımamanın sebebi, karşılığın BAŞKA BİR BELGE
     * olmasıdır — bir dildeki başlığı adlandıran çapa, öteki belgede hiçbir
     * yere götürmez.
     */
    public function counterpart(string $path, string $targetLocale): ?string
    {
        /*
            Kütük yolları sondaki eğik çizgiyle saklanır (`/en/product/`) ama
            sunucu o çizgiyi 301 ile atar; dolayısıyla ziyaretçinin adres
            çubuğundaki hâli çizgisizdir. İkisini de aramak, kaydın hangi
            biçimde yazıldığına bakmadan aynı satırı bulmayı sağlar.
        */
        $trimmed = rtrim($path, '/');

        if ($trimmed === '') {
            return null;
        }

        try {
            $current = ContentPage::query()
                ->whereIn('canonical_path', [$trimmed, $trimmed.'/'])
                ->first();

            if ($current === null || $current->locale === $targetLocale) {
                return null;
            }

            $counterpart = ContentPage::query()
                ->where('page_key', $current->page_key)
                ->where('locale', $targetLocale)
                ->first();
        } catch (Throwable) {
            /*
                KÜTÜK OKUNAMAZSA DİL DEĞİŞTİRMEK ÖLMEZ.

                Veritabanı bir an tökezlediğinde ziyaretçinin dil tercihi yine
                yazılır ve kendi sayfasında kalır. Aynı karar gezinti için de
                verilmişti (`SiteNavigation`).
            */
            return null;
        }

        if ($counterpart === null) {
            return null;
        }

        $environment = PageEnvironment::tryFrom((string) config('content.page_environment'))
            ?? PageEnvironment::Production;

        if (! $this->delivery->for($counterpart, $environment)->decision->isLinkable()) {
            return null;
        }

        // Yönlendirmeye yönlendirmek, her dil değişimine bir tur eklemek olurdu.
        return $this->normalizer->normalize($counterpart->canonical_path)->target();
    }
}
