<?php

declare(strict_types=1);

namespace App\Application\DataRights\UseCase;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\TenantDataScope;
use App\Jobs\BuildWorkspaceDataExportJob;

/**
 * "Verimin bir kopyasını istiyorum" — FF-226.
 *
 * İSTEK EKRANDA BEKLETMEZ. Arşiv üretmek bir çalışma alanının bütün
 * tablolarını okumak demektir ve bu saniyeler sürer; kullanıcı o süreyi
 * dönen bir çarka bakarak geçirmemeli. İstek deftere yazılır, iş kuyruğa
 * girer, ekranda durum görünür ve hazır olduğunda haber verilir
 * (`docs/93`: taşıyıcı yoksa haber gitmez ama kayıt ekranda DURUR).
 *
 * AYNI ANDA TEK İŞ. Açık bir dışa aktarma varken ikincisi açılmaz: aynı
 * veriyi iki kez üretmek diski ve kuyruğu boşuna meşgul eder, kullanıcıya
 * da hangisinin "gerçek" olduğunu sordururdu.
 */
final readonly class RequestWorkspaceExport
{
    public function __construct(private DataRequestRepositoryPort $requests) {}

    public function handle(int $workspaceId, int $userId): DataRequestRow
    {
        $existing = $this->requests->openRequest($workspaceId, DataRequestKind::Export);

        if ($existing !== null) {
            return $existing;
        }

        $sections = array_map(
            static fn ($table): string => $table->name,
            TenantDataScope::exportedTables(),
        );

        $request = $this->requests->open(
            $workspaceId,
            DataRequestKind::Export,
            $userId,
            ['workspaces', ...$sections],
        );

        BuildWorkspaceDataExportJob::dispatch($workspaceId, $request->id);

        return $request;
    }
}
