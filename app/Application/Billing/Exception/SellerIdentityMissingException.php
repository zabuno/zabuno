<?php

declare(strict_types=1);

namespace App\Application\Billing\Exception;

use RuntimeException;

/**
 * Satıcının yasal kimliği yayınlanmadan CANLI tahsilat başlatılamaz (FF-216).
 *
 * Mesafeli satış sözleşmesinin tarafı "not yet provided" iken kurulan bir
 * sözleşmenin satıcısı yoktur. Sandbox kipinde bu bir prova olduğu için yol
 * açıktır; canlı kipte kapalıdır.
 */
final class SellerIdentityMissingException extends RuntimeException {}
