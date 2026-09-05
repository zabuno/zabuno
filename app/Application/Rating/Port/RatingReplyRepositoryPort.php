<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

/**
 * SAHİBİN KENDİ SÖZÜ — `docs/116` §4 (P6).
 *
 * Bu arayüzde `delete` VARDIR ve `RatingSignalRepositoryPort`'ta YOKTUR.
 * Fark bu paketin tamamıdır: misafirin ölçümü sahibin malı değildir,
 * sahibin cümlesi sahibinindir. İkisini aynı kurala bağlasaydık ya sahip
 * yanlış yazdığı bir cümleye sonsuza kadar mahkûm olurdu, ya da "puan
 * silinemez" kuralı ilk istisnasında delinirdi.
 */
interface RatingReplyRepositoryPort
{
    /**
     * Yanıtı yazar ya da var olanın ÜZERİNE yazar.
     *
     * Yeni satır eklemez: bir ürün için restoranın tek bir sesi vardır ve
     * iki yanıt, misafire hangisinin bugünkü söz olduğunu sorardı.
     */
    public function put(
        int $workspaceId,
        string $subjectType,
        int $subjectId,
        string $body,
        ?int $authorUserId,
    ): void;

    /** Sahip kendi sözünü geri alır. Ölçüme dokunmaz. */
    public function remove(int $workspaceId, string $subjectType, int $subjectId): void;
}
