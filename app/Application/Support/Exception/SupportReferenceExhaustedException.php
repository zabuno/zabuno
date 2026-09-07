<?php

declare(strict_types=1);

namespace App\Application\Support\Exception;

use RuntimeException;

/**
 * Art arda çarpışan referanslar — ya üreteç bozuk ya da alan tükendi.
 * İkisi de "tekrar dene" ile geçmez; gürültüyle durmak doğrudur.
 */
final class SupportReferenceExhaustedException extends RuntimeException {}
