<?php

declare(strict_types=1);

namespace App\Application\Content\UseCase;

use App\Application\Content\PageDelivery;
use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PageEnvironment;
use App\Domain\Content\PageGate;
use App\Domain\Content\PagePublicationStatus;
use App\Domain\Content\PageRenderDecision;
use App\Models\ContentPage;

/**
 * Kütük satırından TEK karara — FF-214.
 *
 * ── Neden var ────────────────────────────────────────────────────────────
 *
 * `PageGate` zaten tek karar noktasıydı, ama ona giden yol üç kere ayrı ayrı
 * yürünüyordu. Kapının kararını uygulayabilmek için üç şeyin daha bilinmesi
 * gerekiyor ve bu üçü kapının içinde yaşamıyor:
 *
 *  1. Satır bir SAYFA mı? Şablon (`/tr/blog/{slug}/`) bir desendir, dış
 *     bağlantı ise başka birinin sitesidir; ikisi de bu sitede açılmaz.
 *  2. O dilde gerçekten YAZILMIŞ bir metin var mı? Kütükteki durum elle ileri
 *     sürülebilir; kalite kapısı bir süreçtir, bir kilit değil.
 *  3. Metin yoksa gerçek aşama nedir? "Yayında" deyip 404 dönmek, fişle
 *     cevabın çeliştiği tek yerdi.
 *
 * Bu üç adım denetleyicide vardı, `ResolveLocaleAlternates`'te bir kez daha
 * yazılmıştı, `ShowSitemapController`'da ise HİÇ yoktu — sitemap kütüğü
 * okumuyordu. Üç yerde üç kural demek, bir gün sitemap'in açılmayan bir
 * sayfayı ilan etmesi demektir; ve o gün kimse fark etmez, çünkü sitemap yine
 * geçerli bir XML döndürür.
 *
 * ── Sözleşme ─────────────────────────────────────────────────────────────
 *
 * Bir adresin sitemap'e girip girmemesi ile o adresin 200 mü 404 mü döndüğü
 * AYNI `PageRenderDecision` nesnesinden okunur. İkisi ayrışamaz, çünkü ayrı
 * hesaplanmıyorlar.
 *
 * Bu sınıf hiçbir sayfayı YAYINA ALMAZ. Yayın kararı insanlarındır ve kütükteki
 * durum alanında yaşar; burası o kararı yalnız yansıtır.
 */
final class ResolvePageDelivery
{
    public function __construct(private readonly ContentLibraryPort $library) {}

    public function for(ContentPage $page, PageEnvironment $environment): PageDelivery
    {
        /*
            Şablon bir DESENDİR, bir sayfa değil; dış bağlantı da bu sitede bir
            sayfa değildir. İkisi de ziyaretçiye 404 döner — dolayısıyla ikisi
            de sitemap'e giremez. Aynı cümlenin iki yarısı.
        */
        if ($page->is_template || $page->is_external) {
            return new PageDelivery(
                new PageRenderDecision(
                    mode: 'not-found',
                    statusCode: 404,
                    robots: 'noindex,nofollow',
                    includeInSitemap: false,
                    includeInNavigation: false,
                ),
                $page->status(),
                null,
            );
        }

        $stage = $page->status();

        $decision = PageGate::decide(
            $stage,
            $environment,
            /*
                Önizleme yetkisi HENÜZ YOK: imzalı önizleme token'ı ayrı bir
                paketin işi. Varsayılanı `false` bırakmak, yanlış tarafta hata
                yapmamak demek — `true` bırakmak taslakları herkese açardı.
            */
            false,
            $page->was_ever_published,
        );

        if ($decision->mode === 'not-found') {
            return new PageDelivery($decision, $stage, null);
        }

        $content = $this->library->find($page->page_key, $page->locale);

        /*
            YAYINDA AMA İÇERİĞİ YOK — son emniyet kemeri.

            Böyle bir sayfayı 200 ile sunmak, kapının en baştan engellemek için
            var olduğu şeyi üretirdi: hiçbir soruya cevap vermeyen ince bir
            sayfa. Sitemap tarafındaki karşılığı daha da pahalıdır — arama
            motoruna açılmayan bir adres ilan etmek.

            Bugünkü hâliyle bu dal ÖLÇÜLEN bir dal: Türkçe içerik yuvası
            bilerek boş (`docs/118` E4), yani Türkçe bir adres yayına alınsa
            bile 404 kalır ve sitemap'e giremez.
        */
        if ($decision->mode === 'content' && $content === null) {
            $stage = PagePublicationStatus::Scaffolded;
            $decision = PageGate::decide($stage, $environment, false, false);
        }

        return new PageDelivery($decision, $stage, $content);
    }
}
