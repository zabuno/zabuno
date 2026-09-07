<?php

declare(strict_types=1);

namespace App\Application\Billing\Exception;

use RuntimeException;

/** e-arşiv/e-fatura yolu yapılandırılmadı — sessiz başarı yerine açık duruş. */
final class EArchiveGatewayNotConfiguredException extends RuntimeException {}
