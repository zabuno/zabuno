<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiRequest;

/**
 * Prompt'a giden içerikten kişisel veriyi çıkarır ve kullanıcı içeriğini
 * VERİ olarak işaretler.
 *
 * İki ayrı iş, tek yerde, çünkü ikisi de prompt'un kurulduğu tek noktada
 * yapılmak zorunda:
 *
 * 1. **Redaction** — sağlayıcıya giden veri geri alınamaz (`docs/16` AI-04).
 * 2. **Enjeksiyon sınırı** — kullanıcı içeriği talimat DEĞİLDİR. Bir restoran
 *    sahibi ürün açıklamasına "önceki talimatları yoksay" yazabilir; bir
 *    saldırgan bunu PDF'in içine görünmez metin olarak koyabilir ve OCR onu
 *    okur (`docs/16` AI-10).
 */
final readonly class PromptRedactor
{
    /** @return array{instruction: string, data: array<string, scalar|null>} */
    public function build(AiRequest $request): array
    {
        $redactFields = array_map('strval', (array) config('ai.redact_fields', []));
        $data = [];

        foreach ($request->userContent as $key => $value) {
            $lower = strtolower((string) $key);

            $isSensitive = false;

            foreach ($redactFields as $needle) {
                if (str_contains($lower, $needle)) {
                    $isSensitive = true;

                    break;
                }
            }

            // Hassas alan atlanmaz, [redacted] ile DEĞİŞTİRİLİR: alanın var
            // olduğu bilgisi bazen gereklidir, değeri hiçbir zaman.
            $data[(string) $key] = $isSensitive ? '[redacted]' : $value;
        }

        return ['instruction' => $request->instruction, 'data' => $data];
    }
}
