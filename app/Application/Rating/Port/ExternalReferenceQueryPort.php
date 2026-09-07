<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

use App\Domain\Rating\ExternalReference;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSubject;

/**
 * EŞLEME TABLOSUNU OKUYAN KAPI — `docs/116` §1 Ö4 (P7).
 *
 * ═══ İKİ AYRI OKUMA, ÇÜNKÜ İKİ AYRI SORU ═══
 *
 * `confirmedForSubject()` DIŞ VERİNİN GEÇECEĞİ kapıdır ve yalnız onaylı
 * satırları verir. `pendingDecisionsForWorkspace()` SAHİBİN KARAR
 * KUTUSUDUR ve yalnız kararsız satırları verir. Tek bir `all()` metodu
 * olsaydı, dış veriyi çeken kod da onu çağırır ve "hangileri onaylıydı?"
 * filtresini kendi yazardı — bir gün yazmayı unutana kadar.
 *
 * ═══ KİRACI SINIRI SORGUNUN İÇİNDE ═══
 *
 * `identityIsConfirmedElsewhere()` bu kuralın TEK istisnasıdır ve
 * gerekçesi kendi doküman bloğunda yazılıdır.
 */
interface ExternalReferenceQueryPort
{
    /**
     * Bu varlığın dış veriyi taşımaya YETKİLİ eşlemeleri.
     *
     * Onaylanmamış satır buradan çıkmaz; çıksaydı, bir eşleştiricinin
     * tahmini misafire "bu restoranın puanı" diye gösterilirdi.
     *
     * @return list<ExternalReference>
     */
    public function confirmedForSubject(
        int $workspaceId,
        RatingSubject $subjectType,
        int $subjectId,
    ): array;

    /**
     * Sahibin cevabını bekleyen öneriler.
     *
     * @return list<ExternalReference>
     */
    public function pendingDecisionsForWorkspace(int $workspaceId): array;

    /** Bu kiracıya ait tek bir eşleme; başkasınınki `null` döner. */
    public function find(int $workspaceId, int $referenceId): ?ExternalReference;

    /**
     * Bu dış kimliği BAŞKA bir varlık onaylı taşıyor mu?
     *
     * ═══ KİRACI FİLTRESİ OLMAYAN TEK METOT — GEREKÇESİ ═══
     *
     * Bir Google yer kimliği tek bir fiziksel yeri gösterir; iki farklı
     * işletmenin ikisinin birden "burası benim" demesi mümkün DEĞİLDİR.
     * Bu yüzden kısıt platform geneldir ve kontrol de öyle olmak zorundadır
     * — kiracıyla sınırlasaydık, kontrol her zaman "boş" derdi ve gerçek
     * çatışma yalnız veritabanı indeksi tarafından, ham bir hata olarak
     * görünürdü.
     *
     * Metot BOOLEAN döner ve başka hiçbir şey: kimin taşıdığı, hangi
     * kiracıda olduğu, ne zaman onaylandığı DIŞARI ÇIKMAZ. Çıksaydı,
     * rastgele kimlikler deneyen bir sahip başka işletmelerin hangi
     * platformlara bağlı olduğunu haritalayabilirdi.
     */
    public function identityIsConfirmedElsewhere(
        ExternalSystem $system,
        string $externalId,
        int $exceptReferenceId,
    ): bool;

    /**
     * Bu varlığın bu dış sistemde ZATEN onaylı bir kimliği var mı?
     *
     * Kiracı sınırı içinde kalır: soru bizim kendi varlığımız hakkındadır.
     */
    public function subjectIsConfirmedIn(
        int $workspaceId,
        RatingSubject $subjectType,
        int $subjectId,
        ExternalSystem $system,
        int $exceptReferenceId,
    ): bool;
}
