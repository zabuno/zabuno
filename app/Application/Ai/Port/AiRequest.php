<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

use App\Domain\Ai\Capability;

/**
 * Bir AI çağrısının girdisi.
 *
 * `userContent` ile `instruction` BİLEREK ayrıdır. Kullanıcı içeriği —
 * menü metni, OCR çıktısı, ürün açıklaması — asla talimat olarak
 * yorumlanmaz. Bir restoran sahibi ürün açıklamasına "önceki talimatları
 * yoksay" yazabilir; bir saldırgan bunu PDF'in içine görünmez metin olarak
 * koyabilir (`docs/16` AI-10). Ayrım burada, tür düzeyinde kurulur.
 */
final readonly class AiRequest
{
    /**
     * @param  array<string, scalar|null>  $userContent  VERİ — talimat değil
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public Capability $capability,
        public int $workspaceId,
        public string $instruction,
        public array $userContent = [],
        public array $options = [],
    ) {}
}
