<?php

declare(strict_types=1);

namespace App\Domain\Security;

final class TenantIsolationSuiteManifest
{
    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return [
            'tests/Feature/Media/MediaTenantIsolationTest.php',
            'tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php',
            'tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php',
            'tests/Feature/Publication/PublicationTenantIsolationTest.php',
            'tests/Feature/QrDestination/BulkQrTenantIsolationTest.php',
            'tests/Feature/QrDestination/QrDestinationTenantIsolationTest.php',
        ];
    }
}
