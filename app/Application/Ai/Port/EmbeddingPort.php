<?php

declare(strict_types=1);

namespace App\Application\Ai\Port;

/**
 * Metin → vektör.
 *
 * Vektör kaydı MODEL KİMLİĞİNİ taşır (`docs/16` AI-12): gömme modeli
 * değişince eski vektörler yeni sorgularla karşılaştırılamaz ve bunu
 * fark etmenin tek yolu kimliği saklamaktır.
 */
interface EmbeddingPort
{
    /**
     * @param  list<string>  $texts
     * @return list<array{vector: list<float>, model: string}>
     */
    public function embed(int $workspaceId, array $texts): array;
}
