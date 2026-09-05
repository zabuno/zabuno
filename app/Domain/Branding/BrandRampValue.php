<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * Rampanın tek bir basamağı: türetilmiş renk VE onun ölçülmüş kanıtı.
 *
 * Renk ile kanıtı ayrı yerlerde durursa, biri güncellenip diğeri
 * güncellenmediğinde kimse fark etmez. Burada ikisi aynı nesnede taşınır ve
 * birlikte yayına donar: `hex` neyin çizileceğini, `ratio` onun neye karşı
 * kaç ölçtüğünü, `floor` ise neyi geçmesi gerektiğini söyler.
 *
 * `adjusted`, ürünün kiracıya borçlu olduğu dürüstlüktür: "senin tonunu
 * aldım ama okunması için koyulaştırdım" cümlesinin makine karşılığıdır.
 */
final readonly class BrandRampValue
{
    public function __construct(
        public BrandRampRole $role,
        /** Türetilen renk, `#rrggbb`. */
        public string $hex,
        /** Oranın ÖLÇÜLDÜĞÜ karşı renk (zemin ya da metin). */
        public string $againstHex,
        /** Ölçülen WCAG 2.x kontrast oranı. */
        public float $ratio,
        /** Bu rolün geçmek zorunda olduğu oran. */
        public float $floor,
        /** Kiracının verdiği ton, bu rol için oynatıldı mı? */
        public bool $adjusted,
    ) {}

    /** @return array{hex: string, against: string, ratio: float, floor: float, adjusted: bool} */
    public function toSnapshot(): array
    {
        return [
            'hex' => $this->hex,
            'against' => $this->againstHex,
            // İki basamak yeter ve KASITLIDIR: kanıt insanın da okuyacağı
            // bir sayıdır, kayan nokta kuyruğu değil.
            'ratio' => round($this->ratio, 2),
            'floor' => $this->floor,
            'adjusted' => $this->adjusted,
        ];
    }

    /** @param  array<string, mixed>  $entry */
    public static function fromSnapshot(BrandRampRole $role, array $entry): ?self
    {
        $hex = SrgbColor::tryFromHex(is_string($entry['hex'] ?? null) ? $entry['hex'] : null);
        $against = SrgbColor::tryFromHex(is_string($entry['against'] ?? null) ? $entry['against'] : null);

        if ($hex === null || $against === null) {
            return null;
        }

        return new self(
            role: $role,
            hex: $hex->toHex(),
            againstHex: $against->toHex(),
            /*
                DONMUŞ ORAN OKUNUR, YENİDEN HESAPLANMAZ.

                Yayın, sahibin "bunu onayladım" dediği hâldir (`docs/75`).
                Eşik yarın yükselse ya da türetme kuralı değişse bile dünkü
                yayın kendi kanıtını taşımaya devam eder; aksi hâlde misafir
                bir gün, sahibin hiç görmediği bir rengi görürdü.
            */
            ratio: (float) ($entry['ratio'] ?? 0.0),
            floor: (float) ($entry['floor'] ?? $role->floor()),
            adjusted: (bool) ($entry['adjusted'] ?? false),
        );
    }
}
