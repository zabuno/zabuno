<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

/**
 * Yayınlanan menülerin herkese açık adres çözümü — `docs/38` §21.
 */
interface PublicMenuAddressPort
{
    /**
     * Bir QR token'ının işaret ettiği menünün adresi.
     *
     * @return array{key: string, slug: string, menu_id: int, workspace_id: int}|null
     */
    public function findByQrToken(string $token): ?array;

    /**
     * @return array{key: string, slug: string, menu_id: int, workspace_id: int}|null
     */
    public function findByPublicKey(string $key): ?array;

    /**
     * Sitemap'e girecek yayınlanmış, indekslenebilir menüler.
     *
     * @return list<array{key: string, slug: string, published_at: string}>
     */
    public function indexableMenus(): array;
}
