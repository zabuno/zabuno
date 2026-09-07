<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\PageContent;
use App\Domain\Content\PagePublicationStatus;
use App\Domain\Content\PageRenderDecision;

/**
 * Kütükteki BİR SATIR hakkında verilmiş TEK karar — FF-214.
 *
 * `PageRenderDecision` zaten "bütün yayın soruları tek bir nesnede yanıtlanır"
 * diyordu; eksik olan, o nesneyi ÜRETEN yolun tek olmasıydı. Kapı kararı
 * denetleyicide, hreflang süzgeci kendi sınıfında, sitemap ise hiçbir yerde
 * hesaplanıyordu — üç yer, üç kural, ve üçünün ayrışması an meselesiydi.
 *
 * Bu nesne o üç yeri aynı hesaba bağlar: HTTP kodu, robots, sitemap üyeliği ve
 * ziyaretçiye gösterilen aşama hep AYNI `decide` çağrısından çıkar.
 */
final class PageDelivery
{
    public function __construct(
        public readonly PageRenderDecision $decision,
        /**
         * Ziyaretçiye gösterilecek AŞAMA — kütüktekinin aynısı olmak zorunda
         * değil. Kütük "yayında" deyip gösterilecek metin yoksa gerçek durum
         * "iskeleti var, içeriği yok"tur ve fişte o yazar.
         */
        public readonly PagePublicationStatus $stage,
        /** O dilde gerçekten yazılmış metin; yoksa `null` ve bu bir DURUMDUR. */
        public readonly ?PageContent $content,
    ) {}
}
