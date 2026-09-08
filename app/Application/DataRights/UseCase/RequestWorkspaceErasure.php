<?php

declare(strict_types=1);

namespace App\Application\DataRights\UseCase;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Exception\ErasureBlockedException;
use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\WorkspaceDataEraserPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\ErasureWindow;
use App\Domain\DataRights\TenantDataScope;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * "Verimizi silin" — FF-226 (`docs/138` §4).
 *
 * BU EYLEM HESABI KAPATMAZ. İki şey karıştırılmamalı: hesabı kapatmak
 * hizmetin durmasıdır, veriyi silmek verinin yok olmasıdır. Talep açıldığı
 * gün çalışma alanı ÇALIŞMAYA DEVAM EDER — menü yayında kalır, panel
 * açılır — ve bu bilinçlidir: pencere boyunca "vazgeç" düğmesine
 * ulaşılabilmeli. Paneli aynı anda kilitleseydik, fikrini değiştiren bir
 * sahip kendi vazgeçme düğmesine erişemezdi.
 *
 * YASAL SAKLAMAYA ALINMIŞ DOSYA VARSA HİÇ BAŞLAMAZ. Uyuşmazlık kaydına
 * bağlı bir dosyayı silmek, kilidin var olma sebebini ortadan kaldırırdı
 * (`UpdateMediaLegalHoldController`). Kilidi açmak ayrı ve bilinçli bir
 * karardır; silme onu sessizce ezmez.
 */
final readonly class RequestWorkspaceErasure
{
    public function __construct(
        private DataRequestRepositoryPort $requests,
        private WorkspaceDataEraserPort $eraser,
    ) {}

    public function handle(int $workspaceId, int $userId): DataRequestRow
    {
        $existing = $this->requests->openRequest($workspaceId, DataRequestKind::Erasure);

        if ($existing !== null) {
            return $existing;
        }

        $held = $this->eraser->assetsUnderLegalHold($workspaceId);

        if ($held > 0) {
            throw new ErasureBlockedException('legal_hold', $held);
        }

        $window = ErasureWindow::fromConfig(config('data-rights.erasure.grace_days'));
        $endsAt = $window->endsAt(new DateTimeImmutable(Carbon::now()->toDateTimeString()));

        return $this->requests->open(
            $workspaceId,
            DataRequestKind::Erasure,
            $userId,
            array_map(static fn ($table): string => $table->name, array_filter(
                TenantDataScope::tables(),
                static fn ($table): bool => $table->erased,
            )),
            $endsAt->format('Y-m-d H:i:s'),
        );
    }
}
