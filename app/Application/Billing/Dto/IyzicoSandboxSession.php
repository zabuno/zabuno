<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

final class IyzicoSandboxSession
{
    public function __construct(
        public readonly string $state,
        public readonly ?string $conversationId = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?string $redirectUrl = null,
    ) {}

    public static function none(): self
    {
        return new self('none');
    }

    /**
     * @return array{state: string}|array{state: string, conversation_id: ?string, amount_minor: ?int, currency: ?string, redirect_url: ?string}
     */
    public function toArray(): array
    {
        if ($this->state === 'none') {
            return ['state' => 'none'];
        }

        return [
            'state' => $this->state,
            'conversation_id' => $this->conversationId,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'redirect_url' => $this->redirectUrl,
        ];
    }
}
