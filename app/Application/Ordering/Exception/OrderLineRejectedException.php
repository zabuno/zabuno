<?php

declare(strict_types=1);

namespace App\Application\Ordering\Exception;

use RuntimeException;

/**
 * Sipariş satırı kabul edilmedi — VE SEBEBİ TAŞINIR.
 *
 * Sebep bir SERBEST DİZE DEĞİL, sabit bir anahtardır: misafirin ekranında
 * bir cümleye, ölçümde bir sayıya dönüşecek. Serbest bırakılsaydı, aynı ret
 * iki farklı yüzeyde iki farklı cümle olurdu ve hiçbiri çevrilemezdi.
 *
 * İstisna, yazma İŞLEMİ AÇILMADAN önce fırlar. PostgreSQL'de başarısız bir
 * ifade tüm işlemi zehirler; doğrulamayı işlemin içine bırakmak, SQLite'ta
 * geçen ve dağıtım motorunda çöken bir yol açardı.
 */
final class OrderLineRejectedException extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly ?int $menuItemId,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** Menüde yok, gizli, ya da başka bir menünün satırı. */
    public static function itemUnavailable(?int $menuItemId): self
    {
        return new self('item_unavailable', $menuItemId, 'Bu ürün şu anda menüde değil.');
    }

    /** "Bugün bitti" — `docs/115` M7. */
    public static function outOfStock(int $menuItemId): self
    {
        return new self('out_of_stock', $menuItemId, 'Bu ürün bugün tükendi.');
    }

    public static function invalidQuantity(?int $menuItemId): self
    {
        return new self('invalid_quantity', $menuItemId, 'Adet geçersiz.');
    }

    public static function emptyOrder(): self
    {
        return new self('empty_order', null, 'Sipariş boş.');
    }

    public static function tooManyLines(): self
    {
        return new self('too_many_lines', null, 'Tek siparişte bu kadar farklı ürün olamaz.');
    }

    /**
     * Tek siparişte iki para birimi.
     *
     * Bugün bir menü tek para birimi kullanıyor, yani bu ret pratikte hiç
     * görünmez. Yine de sessiz kalmıyoruz: toplamı iki para biriminden
     * toplamak, matematiksel olarak anlamsız bir sayı üretirdi ve o sayı
     * masada ödenirdi.
     */
    public static function currencyMismatch(int $menuItemId): self
    {
        return new self('currency_mismatch', $menuItemId, 'Tek siparişte iki farklı para birimi olamaz.');
    }
}
