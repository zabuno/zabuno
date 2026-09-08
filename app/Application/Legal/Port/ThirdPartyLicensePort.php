<?php

declare(strict_types=1);

namespace App\Application\Legal\Port;

use App\Domain\Legal\DependencyInventory;

/**
 * Üçüncü taraf lisans listesini TÜRETEN taraf — FF-228.
 *
 * Liste elle yazılmaz: `composer.json`/`composer.lock` ve
 * `package.json`/`package-lock.json` zaten bu deponun tek gerçeğidir ve bir
 * bağımlılık eklendiğinde ikinci bir listeyi güncellemeyi hatırlamak
 * zorunda kalmak, o listenin eskiyeceği anlamına gelir (`docs/140` §5).
 */
interface ThirdPartyLicensePort
{
    /** @return list<DependencyInventory> Her ekosistem için bir envanter. */
    public function inventories(): array;
}
