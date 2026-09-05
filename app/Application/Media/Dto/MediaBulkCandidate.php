<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

/**
 * KURU ÇALIŞMANIN tek bir dosya hakkında bilmesi gereken her şey.
 *
 * Bu nesne var, çünkü kuru çalışma N dosya için N ayrı sorgu atmamalı:
 * 1.800 dosyalık bir kapsamda "yayında mı", "yasal saklamada mı", "hazır
 * mı" sorularını dosya başına sormak, sahibin ekranını dakikalarca boş
 * bırakırdı. Depo bu alanları TEK seferde doldurur; karar veren kod
 * yalnız bu nesneye bakar ve hiç sorgu atmaz.
 *
 * `MediaAssetSummary`den ayrıdır ve onu genişletmez: özet KÜTÜPHANENİN
 * gösterdiği şeydir (önizleme adresi, sürüm sayısı, alt metin), bu nesne
 * ise TOPLU İŞLEMİN karar verdiği şeydir. İkisini birleştirmek, her
 * kütüphane listesine yayın ve yasal saklama sorgusu bindirirdi.
 */
final class MediaBulkCandidate
{
    public function __construct(
        public readonly int $id,
        /** Sahibin ekranda okuduğu ad — `display_name` yoksa `original_name`. */
        public readonly string $name,
        public readonly int $sizeBytes,
        public readonly string $mimeType,
        /** `quarantined | scanning | accepted | processing | ready | rejected | failed` */
        public readonly string $status,
        public readonly ?int $folderId,
        public readonly bool $trashed,
        public readonly bool $legalHold,
        public readonly bool $usedByPublication,
    ) {}
}
