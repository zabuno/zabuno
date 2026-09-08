<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Authorization;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;

/**
 * Destek oturumunun GÖRME izni — `docs/122` Y7, `docs/133` §4.
 *
 * Bu sınıf üyelik kararının üstüne biner, onun yerine geçmez: önce gerçek
 * karar sorulur, hayır çıkarsa açık bir destek oturumu VARSA ve o oturum
 * TAM OLARAK bu çalışma alanına aitse, yalnız `READ_ONLY` kümesindeki
 * izinler verilir.
 *
 * KÜME SAYILIDIR VE YÖNETME İZNİ İÇERMEZ. `menu.manage`, `qr.create`,
 * `billing.manage`, `order.confirm`, `rating.reply` ve kardeşleri burada
 * YOKTUR — ve bu, ara katmandaki istek düzeyi yasağının ikinci kilididir.
 * Yazma yasağı zaten istekte kapalı; buradaki daralma, ekranın yazma
 * düğmesini HİÇ ÇİZMEMESİNİ sağlar (`docs/98` FF-74 ilkesi: yetkisiz eylem
 * çizilmez, 403 gösterilmez). Tek kilit bırakılsaydı destek görevlisi
 * tıklayabildiği ama işlemeyen düğmeler görürdü.
 *
 * OTURUM BAŞKA BİR KİRACIYA GENİŞLEMEZ: `workspaceId` eşitliği kontrol
 * edilir. Bir destek oturumu tek bir restorana bakmaktır; "hepsine bakmak"
 * diye bir oturum yoktur.
 */
final class SupportAccessAuthorization implements AuthorizationPort
{
    /**
     * Destek oturumunun görebildiği izinler — hepsi OKUMA.
     *
     * @var list<Permission>
     */
    private const READ_ONLY = [
        Permission::WorkspaceView,
        Permission::MenuView,
        Permission::QrView,
        Permission::AnalyticsView,
        Permission::BillingView,
        Permission::OrderView,
        Permission::RatingView,
    ];

    public function __construct(
        private readonly AuthorizationPort $inner,
        private readonly SupportAccessScope $scope,
    ) {}

    public function can(int $userId, Permission $permission, int $workspaceId): bool
    {
        if ($this->inner->can($userId, $permission, $workspaceId)) {
            return true;
        }

        return $this->grantsAccessTo($userId, $workspaceId)
            && in_array($permission, self::READ_ONLY, true);
    }

    public function permissionsFor(int $userId, int $workspaceId): array
    {
        $own = $this->inner->permissionsFor($userId, $workspaceId);

        /*
            ÜYELİK VARSA DESTEK OTURUMU BİR ŞEY EKLEMEZ. Platform ekibinden
            biri bir restoranın gerçek üyesi de olabilir (kendi deneme
            hesabı); o zaman gördüğü şey üyeliğinin gösterdiğidir ve destek
            oturumu onu ne genişletir ne daraltır. İki kaynağı birleştirmek,
            "hangi sıfatla baktı?" sorusunu cevapsız bırakırdı.
        */
        if ($own !== []) {
            return $own;
        }

        return $this->grantsAccessTo($userId, $workspaceId) ? self::READ_ONLY : [];
    }

    private function grantsAccessTo(int $userId, int $workspaceId): bool
    {
        return $this->scope->workspaceIdFor($userId) === $workspaceId;
    }
}
