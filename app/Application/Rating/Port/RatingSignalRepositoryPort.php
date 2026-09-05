<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

use App\Application\Rating\Dto\RatingSignalDraft;
use DateTimeImmutable;

/**
 * DEĞİŞMEZ PUAN DEFTERİNE YAZAN TEK KAPI — `docs/116` §1 Ö2.
 *
 * ═══ BU ARAYÜZDE `delete` YOKTUR VE OLMAYACAKTIR ═══
 *
 * Eksiklik değil, sözleşmenin kendisi. Bir sinyal kötüye kullanım
 * sayıldığında SİLİNMEZ, işaretlenir (`docs/116` §4) — ve işaretleme bile
 * ayrı bir kapı olarak durmuyor: yalnız YENİ BİR OY YAZARKEN, aynı işlemin
 * içinde, aynı ziyaretçinin eski oyunu geçersizleştirmek için yapılıyor.
 * Serbest bir "işaretle" ucu, "sil"in kibar hâli olurdu.
 */
interface RatingSignalRepositoryPort
{
    /**
     * Bir masadan, verilen pencere içinde gelen SAYILAN sinyal sayısı.
     *
     * İşaretli satırlar sayılmaz ve bu kasıtlı: fikrini değiştiren bir
     * misafir kendi masasını "kampanya" gibi göstermemeli.
     */
    public function countedSignalsFromTableSince(
        int $workspaceId,
        int $diningTableId,
        DateTimeImmutable $since,
    ): int;

    /**
     * Bu ziyaretçinin bu ürüne verdiği SAYILAN oy var mı?
     *
     * Cevap `true` ise misafir fikrini değiştiriyor demektir; yeni satır
     * yazılırken eskisi aynı işlemde `superseded` olarak işaretlenir.
     */
    public function hasCountedSignal(
        int $workspaceId,
        string $subjectType,
        int $subjectId,
        string $visitorKey,
    ): bool;

    /**
     * Sinyali yazar; gerekiyorsa aynı işlemde eskisini işaretler.
     *
     * TEK İŞLEM olmak zorunda: arada bir an bile iki sayılan satır
     * bulunsaydı kısmî benzersizlik indeksi isteği reddederdi.
     */
    public function record(RatingSignalDraft $draft): void;
}
