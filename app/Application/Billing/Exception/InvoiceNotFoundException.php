<?php

declare(strict_types=1);

namespace App\Application\Billing\Exception;

use RuntimeException;

/** Belge bu çalışma alanında yok — varlığı bile sızdırılmaz (404). */
final class InvoiceNotFoundException extends RuntimeException {}
