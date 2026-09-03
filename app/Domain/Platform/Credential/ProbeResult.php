<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Bir uyumluluk yoklamasının sonucu — `docs/95` Faz 3.
 *
 * `outcome` üç değerden biridir ve üçü de FARKLI şeyler söyler:
 *   • `reachable`  — adres cevap verdi, anahtar kabul edildi.
 *   • `rejected`   — adres cevap verdi ama anahtarı/isteği reddetti.
 *   • `unsupported`— bu sağlayıcı için yoklanacak bir uç nokta yok
 *                    (posta/ödeme sağlayıcıları); bu bir HATA değildir.
 *
 * `rejected` ile `unsupported` ayrımı önemli: ilki bir sorunu bildirir,
 * ikincisi yalnız "burada yoklanacak bir şey yok" der ve sağlığı
 * DEĞİŞTİRMEZ — yoklanamayan bir bağlantıyı sağlıksız işaretlemek, çalışan
 * bir Mailgun hesabını havuzdan düşürmek olurdu.
 */
final readonly class ProbeResult
{
    public function __construct(
        public string $outcome,
        public ?int $httpStatus = null,
        public ?string $detail = null,
    ) {}

    public static function reachable(int $status): self
    {
        return new self('reachable', $status);
    }

    public static function rejected(?int $status, string $detail): self
    {
        return new self('rejected', $status, $detail);
    }

    public static function unsupported(string $detail): self
    {
        return new self('unsupported', null, $detail);
    }

    public function isReachable(): bool
    {
        return $this->outcome === 'reachable';
    }

    public function changesHealth(): bool
    {
        return $this->outcome !== 'unsupported';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'httpStatus' => $this->httpStatus,
            'detail' => $this->detail,
        ];
    }
}
