<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * KUYRUĞUN OKUMA YÜZEYİ — `docs/108` §3 madde 5.
 *
 * `MediaRepositoryPort` işleri AÇAR ve KAPATIR (yazma). Bu port yalnız
 * OKUR. Ayrı, çünkü kuyruk ekranının okuduğu şey ile işleme hattının
 * yazdığı şey aynı hızda değişmez: ekran bir gün sayfalama isteyecek,
 * hattın buna hiç ihtiyacı yok. İkisini tek arayüzde tutmak, salt okunur
 * bir ekranın hattın imzasını değiştirmesine yol açardı.
 *
 * Bu portta iş BAŞLATAN bir metot YOKTUR ve olmayacaktır: "yeniden dene",
 * var olan tek-varlık yeniden üretim ucuna gider.
 */
interface MediaProcessingJobPort
{
    /**
     * Bir çalışma alanının son işleri — en yeni önce.
     *
     * `progress` bilerek `null` olabilir: tabloda yüzde sütunu yok ve
     * çalışan bir işin ne kadarının bittiği BİLİNMİYOR. Uydurulmuş bir
     * "%40" sahibi bekletir, sonra yanıltır.
     *
     * @return array<int, array{
     *     id:int,
     *     mediaAssetId:int,
     *     assetName:?string,
     *     kind:string,
     *     state:string,
     *     attempts:int,
     *     failureReason:?string,
     *     finished:bool,
     *     progress:?float,
     *     startedAt:?string,
     *     finishedAt:?string
     * }>
     */
    public function recent(int $workspaceId, int $limit = 30): array;

    /**
     * Durum başına sayaç.
     *
     * `held` ve `failed` AYRI sayılır: birinde dosyada bir sorun var,
     * diğerinde tarayıcı konuşamadı. İkisini toplamak sahibi "dosyalarım
     * bozuk" sanmaya iter.
     *
     * @return array{pending:int, running:int, succeeded:int, failed:int, held:int, total:int}
     */
    public function counts(int $workspaceId): array;
}
