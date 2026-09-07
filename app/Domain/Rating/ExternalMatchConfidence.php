<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * EŞLEŞTİRİCİNİN KENDİ İDDİASI — `docs/116` §1 Ö4.
 *
 * ═══ NEDEN ÜÇ BANT, NEDEN BİR YÜZDE DEĞİL ═══
 *
 * "%87 güven" yazan bir sütun, olmayan bir hassasiyeti gösterir: o sayıyı
 * üretecek ölçülmüş bir eşleştirici bu depoda YOKTUR ve uydurulmuş bir
 * ondalık, kararı verecek insanı yanıltır — sahip "%87 ise doğrudur" diye
 * düşünür. Üç bant iddianın ne kadar kaba olduğunu dürüstçe söyler.
 *
 * ═══ BU DEĞER TEK BAŞINA HİÇBİR KAPIYI AÇMAZ ═══
 *
 * Güven düzeyi sahibin onayının yerine GEÇMEZ (§5 D4). En yüksek bant bile
 * bir öneridir; kapıyı açan şey `ExternalReference::mayCarryExternalDataToGuest()`
 * içindeki tek kuraldır ve o kural yalnız sahibin kararına bakar.
 */
enum ExternalMatchConfidence: string
{
    /**
     * `external_references.confidence` sütununun genişliği (bkz.
     * `ExternalSystem::MAX_VALUE_LENGTH` gerekçesi).
     */
    public const MAX_VALUE_LENGTH = 16;

    /** Yalnız isim benziyor — "Lezzet Sarayı" adında üç restoran vardır. */
    case Low = 'low';

    /** İsim ve şehir/ilçe tutuyor. */
    case Medium = 'medium';

    /** İsim, adres ve konum birlikte tutuyor. */
    case High = 'high';
}
