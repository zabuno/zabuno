<?php

declare(strict_types=1);

namespace App\Application\Ordering\Exception;

use RuntimeException;
use Throwable;

/**
 * Sipariş yazılamadı.
 *
 * Altyapı istisnası burada SARILIR: `QueryException` uygulama katmanına
 * sızsaydı, denetleyici bir veritabanı sürücüsünün mesajını misafirin
 * ekranına taşıyabilirdi.
 */
final class OrderPersistenceFailedException extends RuntimeException
{
    public static function fromPrevious(Throwable $previous): self
    {
        return new self('Sipariş yazılamadı.', 0, $previous);
    }
}
