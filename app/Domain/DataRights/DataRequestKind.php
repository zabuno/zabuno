<?php

declare(strict_types=1);

namespace App\Domain\DataRights;

/**
 * İki ayrı hak, iki ayrı tür — FF-226 (`docs/138`).
 *
 * Dışa aktarma ile silme aynı kelimeyle anılmaz ve tek bir düğmeye
 * bağlanmaz: biri geri alınabilir ve zararsızdır, diğeri geri alınamaz.
 * Aynı türün iki hâli olsalardı, bir gün biri diğerinin yolundan geçer
 * ve "indir" düğmesi veriyi silerdi.
 */
enum DataRequestKind: string
{
    case Export = 'export';
    case Erasure = 'erasure';
}
