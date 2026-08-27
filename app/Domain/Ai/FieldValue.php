<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * AI'nın ürettiği TEK bir alan — değeri, güveni ve kaynağıyla.
 *
 * Neden ham bir değer değil: modelin okuyamadığı bir fiyat, sıfır ya da
 * tahmin olarak gelirse restoran sahibi bunu ancak müşteri şikâyet edince
 * öğrenir. Belirsizlik GİZLENMEZ, taşınır (`docs/16` AI-15).
 */
final readonly class FieldValue
{
    public function __construct(
        public string $name,
        public mixed $value,
        public float $confidence,
        public bool $uncertain,
        public ?SourceRef $source = null,
    ) {}

    /**
     * Bu alan insan doğrulaması olmadan kullanılabilir mi?
     *
     * `uncertain` işaretli bir alan, güveni yüksek olsa bile kullanılamaz:
     * işareti model koymuştur ve o, güven puanından daha güçlü bir sinyaldir.
     */
    public function isUsableWithoutReview(float $threshold): bool
    {
        return ! $this->uncertain && $this->confidence >= $threshold;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'confidence' => $this->confidence,
            'uncertain' => $this->uncertain,
            'source' => $this->source?->toArray(),
        ];
    }
}
