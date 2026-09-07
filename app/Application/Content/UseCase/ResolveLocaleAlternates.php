<?php

declare(strict_types=1);

namespace App\Application\Content\UseCase;

use App\Domain\Content\PageEnvironment;
use App\Domain\Url\CanonicalUrl;
use App\Models\ContentPage;

/**
 * Bir sayfanın hreflang alternatifleri ve `x-default`i — `docs/119` §10.4.
 *
 * hreflang bir nezaket etiketi değil, bir İDDİADIR: "bu sayfanın şu dildeki
 * karşılığı şuradadır." Yanlış ilan edilen bir alternatif, arama motorunu
 * çalışmayan bir adrese gönderir ve iki sayfayı birbirinin kopyası sayar.
 *
 * Bu yüzden liste ayrı bir "çevrildi mi" bayrağından TÜRETİLMEZ. Bayrak elle
 * ileri sürülebilir; kütükteki durum bir süreçtir, bir kilit değil. Ölçü şu:
 * o dilde GERÇEKTEN AÇILAN bir sayfa var mı? Cevabı zaten tek bir yerde
 * yaşıyor — `PageGate` — ve orada yaşamaya devam ediyor. Yarım çevrilmiş bir
 * sayfa yayına giremez, dolayısıyla alternatif de olamaz (`docs/119` §10.4).
 *
 * İkinci süzgeç içerik katmanıdır: kütük "yayında" dese bile o dilde
 * gösterilecek metin yoksa sayfa 404 kalır (`ShowCorporatePageController`).
 * Var olmayan bir sayfayı alternatif ilan etmek, ilanın kendisini yalan yapar.
 *
 * FF-214: bu iki süzgeç burada İKİNCİ KEZ yazılmıştı. Artık ziyaretçiye
 * verilen cevabı üreten hesabın kendisinden okunuyor (`ResolvePageDelivery`) —
 * aynı karar sitemap'i de besliyor.
 */
final class ResolveLocaleAlternates
{
    public function __construct(
        private readonly ResolvePageDelivery $delivery,
        private readonly CanonicalUrl $canonical,
    ) {}

    /**
     * `alternates`: dil kodu → tam adres. `xDefault`: dili bilinmeyen
     * ziyaretçinin düşeceği adres, ya da karşılık yoksa `null`.
     *
     * @return array{alternates: array<string, string>, xDefault: ?string}
     */
    public function handle(ContentPage $page, PageEnvironment $environment, string $baseUrl): array
    {
        /*
            Kendisi de listededir ve bu bir fazlalık değil: hreflang kümesi
            KARŞILIKLI olmak zorundadır (`docs/119` §10.4 "karşılıklı hreflang
            ekle"). Kendine işaret etmeyen bir sayfa, öteki dilin ilanını da
            geçersiz kılar.
        */
        $rows = ContentPage::query()
            ->where('page_key', $page->page_key)
            ->orderBy('locale')
            ->get();

        $alternates = [];

        foreach ($rows as $row) {
            /*
                ŞABLON, DIŞ BAĞLANTI, YAYINLANMAMIŞ VE METİNSİZ — dördü de tek
                bir sorunun cevabıdır: bu adres gerçekten açılıyor mu? Cevabı
                `ResolvePageDelivery` veriyor ve o cevap, ziyaretçinin aldığı
                HTTP kodunun ta kendisi.
            */
            if (! $this->delivery->for($row, $environment)->decision->isLinkable()) {
                continue;
            }

            /*
                Adres URL MOTORUNDAN geçer. Kütük yolları belgenin yazımıyla,
                sondaki eğik çizgiyle duruyor (`docs/105` §4.1 gösterimi);
                gerçek sunucu ise `config/url-policy.php` gereği o çizgiyi 301
                ile atıyor. Ham yolu ilan etmek, her hreflang iddiasını bir
                YÖNLENDİRMEYE bağlamak olurdu — kanonik etiketin aynı sebeple
                motordan geçirilmesinin ölçülmüş gerekçesi bu.
            */
            $alternates[$row->locale] = $this->canonical->for($baseUrl, $row->canonical_path);
        }

        /*
            TEK DİL KALDIYSA İDDİA DA YOKTUR. Yalnız kendine hreflang veren bir
            sayfa hiçbir soruyu yanıtlamaz; `x-default` de aynı adresi ikinci
            kez göstermiş olur. Gürültü, eksiklikten daha pahalıdır: bir gün
            gerçek bir alternatif eklendiğinde onu fark eden olmaz.
        */
        if (count($alternates) < 2) {
            return ['alternates' => [], 'xDefault' => null];
        }

        /*
            `x-default` KAYNAK DİLİ gösterir. `docs/105` §4.1 onu Türkçe
            canonical'a bağlamıştı ve o madde `docs/118` E4 ile ezildi: kaynak
            dil artık İngilizce. `x-default`, dili bu kümeden hiçbiriyle
            eşleşmeyen ziyaretçinin düşeceği yerdir ve o yer, ürünün asıl
            yazıldığı dil olmalıdır.
        */
        $sourceLocale = (string) config('i18n.source_locale');

        return [
            'alternates' => $alternates,
            'xDefault' => $alternates[$sourceLocale] ?? null,
        ];
    }
}
