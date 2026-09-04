<?php

declare(strict_types=1);

namespace App\Support\QrDestination;

use App\Application\Publication\Port\MenuIdentityPort;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;

/**
 * İstenen temayı GERÇEK bir yerleşime çevirir — FF-112, `docs/104` Döngü 10.
 *
 * Dört dışa aktarım ucu (png, svg, pdf, baskı sayfası) aynı soruyu soruyor:
 * "markalı" seçildiyse markanın rengi nedir ve basılabilir mi? Bunu dört
 * yerde ayrı ayrı çözmek, dördünün bir gün farklı cevap vermesi demekti —
 * ekrandaki önizleme bir renk, yazıcıdan çıkan kart başka bir renk.
 */
final class QrLayoutResolver
{
    public function __construct(private readonly MenuIdentityPort $identities) {}

    public function resolve(?string $theme, int $workspaceId, int $menuId): QrLayout
    {
        $requested = QrTheme::from($theme ?? QrTheme::Classic->value);

        if ($requested !== QrTheme::Branded) {
            return new QrLayout($requested);
        }

        $identity = $this->identities->forMenu($workspaceId, $menuId);

        return QrLayout::branded($identity?->primaryColor);
    }
}
