<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Ödemesiz süre hatırlatmasının okuduğu ve yazdığı yer — `docs/134`.
 *
 * BU PORT EVRE KARARI VERMEZ. `candidates()` yalnız bir PENCERE okur:
 * dönemi bitmiş ve ödemesiz süre kadar geriye giden aralıkta duran
 * abonelikler. Hangi aboneliğin gerçekten `Grace` evresinde olduğu
 * `SubscriptionLifecycle`'ın tek hesabında kalır — pencere bilerek geniş
 * tutulur, çünkü fazla okunan bir satırı evre eler, hiç okunmayan bir
 * satır ise sessizce haber alamaz.
 */
interface SubscriptionGraceReminderRepositoryPort
{
    /**
     * Dönemi `$endedAfter` ile `$endedBefore` arasında bitmiş abonelikler.
     *
     * `cancelled_at` SORGUDA ELENMEZ ve çağırana olduğu gibi verilir:
     * iptalin ödemesiz süreyi kaldırdığı kararı `SubscriptionLifecycle`'a
     * aittir ve burada tekrarlanırsa iki gün sonra ayrışır.
     *
     * @return list<array{workspaceId:int, endsAt:DateTimeImmutable, cancelledAt:?DateTimeImmutable}>
     */
    public function candidates(DateTimeInterface $endedAfter, DateTimeInterface $endedBefore): array;

    /**
     * Çalışma alanının BUGÜNKÜ sahipleri.
     *
     * Fatura profilindeki adres DEĞİL: o adres mali müşavirin olabilir ve
     * hatırlatma bir belge değil bir davranış çağrısıdır — yalnız paneli
     * açıp ödemeyi yapabilen role gider.
     *
     * @return list<string>
     */
    public function currentOwnerEmails(int $workspaceId): array;

    /** Bu dönem, bu alıcı için gerçek bir gönderim damgası var mı? */
    public function alreadyNotified(int $workspaceId, DateTimeInterface $periodEndsAt, string $recipientEmail): bool;

    /**
     * Damgayı basar. YALNIZ gerçek bir dışarı gönderim başarısında çağrılır.
     *
     * Aynı anahtar ikinci kez gelirse satır sessizce yazılmaz: benzersiz
     * indeks son sözü söyler ve çakışma bir arıza değil, aynı işin iki kez
     * istenmesidir.
     */
    public function markNotified(int $workspaceId, DateTimeInterface $periodEndsAt, string $recipientEmail): void;
}
