<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

/**
 * "Bu plana geçersem NE OLUR?" — ödemeden ve karar vermeden ÖNCE.
 *
 * Düşürmenin sessiz yapılması bu üründe özellikle ağırdır: `menu.rich-media`
 * hakkı düşen bir restoranın MİSAFİRİ, bir sonraki yayında tabak
 * fotoğraflarını göremez; `ordering.basic` düşerse masadaki sepet gönderilemez
 * hâle gelir. Sahip bunu faturasından değil, EKRANDAN önceden öğrenmelidir.
 *
 * `losing` / `gaining`, `Entitlement` enum'unun anahtar + etiket çiftleridir;
 * etiket sunucudan gelir çünkü etiketin tek sahibi o enum'dur.
 */
final class PlanChangePreview
{
    /**
     * @param  'upgrade'|'downgrade'|'same'|'unpriced'  $direction
     * @param  list<array{key: string, label: string}>  $losing
     * @param  list<array{key: string, label: string}>  $gaining
     */
    public function __construct(
        public readonly int $currentPlanId,
        public readonly string $currentPlanName,
        public readonly int $targetPlanId,
        public readonly string $targetPlanName,
        public readonly string $direction,
        public readonly ?int $currentAmountMinor,
        public readonly ?int $targetAmountMinor,
        public readonly ?string $currency,
        /** Kararın yürürlüğe gireceği an — düşürmede ödenmiş dönemin sonu. */
        public readonly string $effectiveAt,
        public readonly array $losing,
        public readonly array $gaining,
        /** Düşürme için: bu karar bugün zaten kayıtlı mı? */
        public readonly bool $alreadyScheduled,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'current_plan_id' => $this->currentPlanId,
            'current_plan_name' => $this->currentPlanName,
            'target_plan_id' => $this->targetPlanId,
            'target_plan_name' => $this->targetPlanName,
            'direction' => $this->direction,
            'current_amount_minor' => $this->currentAmountMinor,
            'target_amount_minor' => $this->targetAmountMinor,
            'currency' => $this->currency,
            'effective_at' => $this->effectiveAt,
            'losing' => $this->losing,
            'gaining' => $this->gaining,
            'already_scheduled' => $this->alreadyScheduled,
        ];
    }
}
