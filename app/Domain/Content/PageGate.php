<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * TEK KARAR NOKTASI — FF-117, yönerge §7.
 *
 * 414 sayfanın hepsi buradan geçer. Sayfayı açmak için koddan bir bileşen
 * silinmez; yalnız kontrollü yayın durumu değişir.
 *
 * En kritik karar: **yayınlanmamış yüzlerce URL'ye `200` ile aynı
 * "hazırlanıyor" metnini vermek yasaktır.** Bu soft-404'tür ve 414 sayfalık
 * bir sitede alan adının kalitesini topluca düşürür: arama motoru yüzlerce
 * neredeyse aynı, hiçbir soruya cevap vermeyen sayfa görür. Doğru cevap
 * "burada henüz bir şey yok"tur — yani 404 — ve hazırlanıyor ekranı o 404'ün
 * GÖVDESİDİR, kendisi değil.
 */
final class PageGate
{
    public static function decide(
        PagePublicationStatus $status,
        PageEnvironment $environment,
        bool $hasPreviewAccess,
        bool $wasEverPublished,
    ): PageRenderDecision {
        if ($environment === PageEnvironment::Staging) {
            // Staging'de her şey görünür ve hiçbir şey indekslenmez. Menü de
            // çalışır: ekip sayfaları gezerek kontrol eder.
            return new PageRenderDecision(
                mode: $status->isPublished() ? 'content' : 'preview',
                statusCode: 200,
                robots: 'noindex,nofollow',
                includeInSitemap: false,
                includeInNavigation: true,
            );
        }

        if ($status->isPublished()) {
            return new PageRenderDecision(
                mode: 'content',
                statusCode: 200,
                robots: 'index,follow',
                includeInSitemap: true,
                includeInNavigation: true,
            );
        }

        if ($status === PagePublicationStatus::Retired) {
            // Emekli sayfa saklanmaz, YOK. Eşdeğeri varsa yönlendirme ayrı bir
            // karardır ve redirect tablosunda yaşar.
            return new PageRenderDecision(
                mode: 'not-found',
                statusCode: 404,
                robots: 'noindex,nofollow',
                includeInSitemap: false,
                includeInNavigation: false,
            );
        }

        if ($hasPreviewAccess) {
            return new PageRenderDecision(
                mode: 'preview',
                statusCode: 200,
                robots: 'noindex,nofollow',
                includeInSitemap: false,
                includeInNavigation: false,
            );
        }

        if ($status === PagePublicationStatus::Maintenance) {
            /*
                503 "bu sayfa VARDI, kısa süreliğine yok" demektir ve arama
                motoruna "indeksteki hâlini koru" der. Hiç yayınlanmamış bir
                sayfada kullanmak, var olmayan bir şeyin geri geleceğini
                söylemektir; o yüzden geçmişi olmayan sayfa 404'e düşer.
            */
            if ($wasEverPublished) {
                return new PageRenderDecision(
                    mode: 'maintenance',
                    statusCode: 503,
                    robots: 'noindex,follow',
                    includeInSitemap: false,
                    includeInNavigation: false,
                );
            }
        }

        return new PageRenderDecision(
            mode: 'construction',
            statusCode: 404,
            robots: 'noindex,follow',
            includeInSitemap: false,
            includeInNavigation: false,
        );
    }
}
