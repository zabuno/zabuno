<?php

declare(strict_types=1);

namespace App\Application\Workspace\Port;

use App\Application\Workspace\Dto\SetupProgress;

/**
 * "Kurulumun neresindeyim ve ilk yayına kaç dakikada ulaştım?" — `docs/107`
 * 1.7, `docs/101` §4, `docs/110` §7.
 *
 * Port, çünkü cevap BEŞ AYRI MODÜLÜN tablosundan toplanır (marka, şube,
 * menü, yayın, karekod) ve hiçbiri "kurulum" diye bir kayıt tutmaz. Bu
 * port yeni kayıt da tutmaz: var olan zaman damgalarını okur, uydurmaz.
 */
interface SetupProgressPort
{
    public function forWorkspace(int $workspaceId): SetupProgress;
}
