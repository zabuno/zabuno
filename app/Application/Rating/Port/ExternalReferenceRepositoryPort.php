<?php

declare(strict_types=1);

namespace App\Application\Rating\Port;

use App\Application\Rating\Dto\ExternalReferenceDraft;
use DateTimeImmutable;

/**
 * EŞLEME TABLOSUNA YAZAN TEK KAPI — `docs/116` §1 Ö4 / §5 D4 (P7).
 *
 * ═══ İKİ AYRI YAZMA YOLU, ÇÜNKÜ İKİ AYRI OLAY ═══
 *
 * `suggest()` bir MAKİNE önerisidir ve kararsız doğar. `mapByOwner()`
 * sahibin kendi elle kurduğu eşlemedir ve onaylı doğar — çünkü sahibin
 * kimliği kendi eliyle yazması ZATEN onaydır; ondan ayrıca "onaylıyor
 * musunuz?" diye sormak, verdiği cevabı tekrar sormaktır.
 *
 * Tek bir `save()` metodu olsaydı, "onaylı mı?" bir parametreye dönerdi ve
 * o parametreyi bir eşleştirici `true` geçebilirdi.
 *
 * ═══ HER METOT KİRACIYI İSTER ═══
 *
 * `workspaceId` hiçbir metotta isteğe bağlı değildir ve sorgunun İÇİNE
 * girer. Kiracı sızıntısı bu depoda PostgreSQL'de yakalanmış bir kusur
 * ailesidir; sınırı çağıranın hatırlamasına bırakmak, bir gün
 * hatırlanmaması demektir.
 */
interface ExternalReferenceRepositoryPort
{
    /**
     * Otomatik bir aday — kararsız doğar, misafire hiçbir şey taşımaz.
     *
     * @return int Yazılan satırın kimliği.
     */
    public function suggest(ExternalReferenceDraft $draft): int;

    /**
     * Sahibin kendi kurduğu eşleme — onaylı doğar.
     *
     * @return int Yazılan satırın kimliği.
     */
    public function mapByOwner(
        ExternalReferenceDraft $draft,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): int;

    /**
     * "Evet, burası benim restoranım."
     *
     * @return bool Bu kiracıda karar bekleyen böyle bir satır var mıydı?
     */
    public function confirm(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): bool;

    /**
     * "Hayır, burası benim restoranım değil."
     *
     * @return bool Bu kiracıda karar bekleyen böyle bir satır var mıydı?
     */
    public function reject(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $decidedAt,
    ): bool;
}
