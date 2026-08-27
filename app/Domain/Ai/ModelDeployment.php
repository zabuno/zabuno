<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * Bir sağlayıcı bağlantısındaki TAM model revizyonu.
 *
 * Hiyerarşi (`docs/51` §3.2, kanonik: `modules/ai-provider-account-vault.md`):
 *
 *   Provider → Connection → ModelDeployment → CapabilityRoute
 *
 * `ANTHROPIC_KEY_1/2/3` gibi numaralı anahtar dizisi DEĞİL: bir sağlayıcının
 * birden çok resmi bağlantısı (proje/workspace/service account) olabilir ve
 * her bağlantı farklı model revizyonları sunabilir.
 *
 * `quantization` model kimliğinin PARÇASIDIR (`docs/16` AI-08): aynı model
 * farklı nicemleme düzeyinde farklı kalite verir; sessizce değişirse çıktı
 * bozulur ve kimse fark etmez.
 */
final readonly class ModelDeployment
{
    public function __construct(
        public string $provider,
        public string $connection,
        public string $model,
        public ?string $quantization = null,
    ) {}

    /** Denetim kaydına ve artifact'e yazılan tam kimlik. */
    public function identity(): string
    {
        $identity = "{$this->provider}:{$this->connection}:{$this->model}";

        return $this->quantization === null ? $identity : "{$identity}@{$this->quantization}";
    }

    public function isLocal(): bool
    {
        return $this->provider === 'local';
    }
}
