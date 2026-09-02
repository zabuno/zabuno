<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Exception\QrCodePersistenceFailedException;

interface QrCodeRepositoryPort
{
    /**
     * @throws QrCodePersistenceFailedException
     */
    public function create(int $workspaceId, int $locationId, int $menuId, string $token): QrCodeRecord;

    /**
     * @return list<QrCodeRecord>
     */
    public function listForLocation(int $workspaceId, int $locationId): array;

    public function findById(int $qrCodeId): ?QrCodeRecord;

    /**
     * @throws QrCodePersistenceFailedException
     */
    public function disable(int $qrCodeId): QrCodeRecord;

    /**
     * Devre dışı bırakılan bir kodu geri açar — `docs/81` (P1-03).
     *
     * Yanlışlıkla kapatılan bir kod, masadaki basılı kâğıdı kalıcı olarak
     * ölü bırakırdı. Yeniden bastırmak, bu ürünün temel vaadinin ihlalidir.
     *
     * @throws QrCodePersistenceFailedException
     */
    public function enable(int $qrCodeId): QrCodeRecord;

    /**
     * Kodun hedefini BAŞKA bir menüye taşır.
     *
     * `qr_destinations` YENİ bir satır alır; eski satır durur. Token
     * DEĞİŞMEZ — masadaki kâğıt aynı kâğıttır, yalnız açtığı menü değişir.
     *
     * Kodun şubesi de menünün şubesine taşınır: aksi hâlde ölçüm, kodun
     * artık göstermediği şubeye yazılırdı.
     *
     * @throws QrCodePersistenceFailedException
     */
    public function retarget(int $qrCodeId, int $menuId, int $locationId): QrCodeRecord;

    public function tokenExists(string $token): bool;

    public function findActiveByToken(string $token): ?QrCodeRecord;
}
