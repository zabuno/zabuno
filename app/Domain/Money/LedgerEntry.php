<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * Değişmez çift-kayıtlı defter girdisi — CORE-12.
 *
 * Çift kayıt bir muhasebe süsü değil, bir **doğruluk kontrolüdür**: her
 * hareket bir yerden çıkıp bir yere girer ve ikisi eşit olmak zorundadır.
 * Eşit değilse para kaybolmuş ya da yoktan var olmuştur; ikisi de bir hatadır
 * ve kaydedilmeden önce yakalanmalıdır.
 *
 * Girdi kurulduktan sonra DEĞİŞTİRİLEMEZ. Bir defterin tek değeri geçmişin
 * sonradan düzeltilememesidir; düzeltilebilen defter, defter değil not
 * tutmaktır. Hatalı bir kayıt silinmez, karşı kayıtla dengelenir.
 */
final class LedgerEntry
{
    private function __construct(
        public readonly int $workspaceId,
        public readonly string $reference,
        public readonly string $debitAccount,
        public readonly string $creditAccount,
        public readonly Money $amount,
        public readonly string $occurredAt,
        public readonly ?string $description,
    ) {}

    public static function record(
        int $workspaceId,
        string $reference,
        string $debitAccount,
        string $creditAccount,
        Money $amount,
        string $occurredAt,
        ?string $description = null,
    ): self {
        if ($workspaceId <= 0) {
            throw new InvalidArgumentException('Ledger entry requires a workspace.');
        }

        if (trim($reference) === '') {
            throw new InvalidArgumentException('Ledger entry requires a reference.');
        }

        $debit = trim($debitAccount);
        $credit = trim($creditAccount);

        if ($debit === '' || $credit === '') {
            throw new InvalidArgumentException('Ledger entry requires both a debit and a credit account.');
        }

        // Aynı hesaba borç ve alacak yazmak hiçbir şey ifade etmez: hareket
        // yokken hareket kaydedilmiş olur ve defter sessizce şişer.
        if ($debit === $credit) {
            throw new InvalidArgumentException('Ledger entry cannot debit and credit the same account.');
        }

        if ($amount->minorAmount() <= 0) {
            throw new InvalidArgumentException('Ledger entry amount must be positive.');
        }

        return new self($workspaceId, trim($reference), $debit, $credit, $amount, $occurredAt, $description);
    }
}
