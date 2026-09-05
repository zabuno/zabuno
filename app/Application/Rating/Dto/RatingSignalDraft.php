<?php

declare(strict_types=1);

namespace App\Application\Rating\Dto;

use App\Domain\Rating\RatingSource;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;

/**
 * Deftere yazılmak üzere hazırlanmış TEK bir sinyal.
 *
 * ═══ KARAR BURADA VERİLMİŞ HÂLDE GELİR ═══
 *
 * `excludedReason` bir "belki" değil, verilmiş bir karardır: yığılma
 * tespiti uygulama katmanında (`RecordGuestRating`) yapılır ve sonucu bu
 * alanda taşınır. Depoya "sen karar ver" deseydik, kötüye kullanım kuralı
 * algoritma dosyasının değil SQL'in içinde yaşardı.
 */
final class RatingSignalDraft
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly RatingSubject $subjectType,
        public readonly int $subjectId,
        public readonly RatingSource $source,
        public readonly int $scoreValue,
        /** Sinyalin KENDİ ölçeği; puanla birlikte yaşar (`docs/116` §1 Ö2). */
        public readonly int $scoreScaleMax,
        public readonly ?string $visitorKey,
        /** Masadan geldiğinin kanıtı — dış kaynakta ikisi de boştur. */
        public readonly ?int $qrCodeId,
        public readonly ?int $diningTableId,
        /** Oyun VERİLDİĞİ an; sönüm bunu okur. */
        public readonly DateTimeImmutable $observedAt,
        /** Bizim onu GÖRDÜĞÜMÜZ an; dış kaynakta ikisi aylarca ayrışır. */
        public readonly DateTimeImmutable $recordedAt,
        /** Doluysa satır yazılır AMA ağırlıklandırmaya girmez. */
        public readonly ?string $excludedReason = null,
        /**
         * Doluysa: aynı ziyaretçinin aynı ürüne verdiği önceki SAYILAN oy
         * bu sebeple işaretlenir (misafir fikrini değiştirdi).
         *
         * Bayrak değil SEBEP taşınıyor: sebep dizesi algoritma dosyasındaki
         * kapalı listeye karşı ÇAĞIRAN tarafından doğrulanır ve buraya
         * doğrulanmış hâlde gelir. Bayrak olsaydı, sütuna yazılacak değeri
         * depo uydururdu ve kapalı liste bir yorumdan ibaret kalırdı.
         *
         * Yazma ile işaretleme AYNI işlemde olmak zorunda: arada bir an
         * bile iki sayılan satır bulunsaydı, kısmî benzersizlik indeksi
         * isteği reddederdi — ve haklı olarak.
         */
        public readonly ?string $supersedePreviousWithReason = null,
    ) {}
}
