<?php

declare(strict_types=1);

namespace App\Application\DataRights\Port;

use App\Application\DataRights\Dto\ExportArtifact;

/**
 * Çalışma alanının verisini tek bir arşive yazar — FF-226.
 *
 * Port, çünkü biçim bir altyapı kararıdır: bugün ZIP içinde JSON ve CSV,
 * yarın başka bir şey. Uygulamanın bilmesi gereken tek şey, geriye bir
 * dosyanın yolu, boyu ve sağlaması geldiği.
 */
interface WorkspaceDataExporterPort
{
    public function export(int $workspaceId, int $requestId): ExportArtifact;

    /** Arşivi diskten kaldırır; süresi dolan çıktı sunucuda durmaz. */
    public function forget(string $path): void;
}
