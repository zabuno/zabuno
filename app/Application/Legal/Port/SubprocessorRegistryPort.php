<?php

declare(strict_types=1);

namespace App\Application\Legal\Port;

use App\Domain\Legal\SubprocessorInventory;

/**
 * Alt işleyen listesini ÖLÇEN taraf — FF-228.
 *
 * Liste elle yazılmaz. Bir sağlayıcı eklendiğinde ya da kasadan çıkarıldığında
 * belgenin sessizce eskimemesi için, DPA metnindeki ek bu porttan gelir
 * (`docs/140` §3).
 */
interface SubprocessorRegistryPort
{
    public function inventory(string $locale = 'en'): SubprocessorInventory;
}
