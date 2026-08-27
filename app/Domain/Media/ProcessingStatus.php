<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Dosyanın BORU HATTINDA nerede olduğu.
 *
 * Üç durum ekseninden biri. Tek bir `status` sütununa doldurulmuş hâlde
 * cevaplanamayan sorular vardı: "işlenmesi bitti mi" ile "çöp kutusunda mı"
 * ile "herkese açık mı" birbirinden bağımsızdır. Bir varlık aynı anda
 * `READY`, `ACTIVE` ve `TENANT` olabilir.
 */
enum ProcessingStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Quarantined = 'quarantined';
    case Scanning = 'scanning';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Rejected = 'rejected';

    /**
     * Yalnız `READY` bir varlık yayınlanabilir.
     *
     * Bu kural burada durur ve teslim katmanının iyi niyetine bırakılmaz:
     * taranmamış bir dosyaya public adres üretmek, güvenlik boru hattını
     * atlamak demektir (`docs/49` Faz 2).
     */
    public function isPublishable(): bool
    {
        return $this === self::Ready;
    }
}
