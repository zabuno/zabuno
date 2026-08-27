<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Exception\SchemaViolationException;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Domain\Ai\FieldValue;

/**
 * Modelin cevabı sözleşmeye uyuyor mu?
 *
 * Aynı prompt farklı modelde farklı yapı döndürür. Doğrulama olmadan bu,
 * arayüzde SESSİZ bozulmadır: alan eksik gelir, ekran boş görünür ve kimse
 * sebebini bilmez (`docs/51` UNK-02).
 *
 * Ayrıca burada ürünün en sert sınırı zorlanır: **alerjen iddiası**
 * (`docs/16` AI-14).
 */
final readonly class ArtifactSchemaValidator
{
    /** Alerjen için YALNIZ aday bildirilebilir. */
    private const ALLERGEN_CANDIDATE_FIELD = 'candidate_allergens';

    /**
     * Şema düzeyinde YASAK alan adları.
     *
     * "Alerjensizdir" bir çıkarım değil, bir iddiadır; çapraz bulaşma menü
     * metninden çıkarılamaz — mutfak pratiği bilgisi gerektirir. Yanlış bir
     * "alerjensiz" iddiası bir sağlık olayıdır. Bu yüzden alan adı düzeyinde
     * yasaklanır: modelin iyi niyetine bırakılmaz.
     */
    private const FORBIDDEN_FIELDS = [
        'allergen_free',
        'is_allergen_free',
        'allergens_confirmed',
        'no_allergens',
        'cross_contamination',
        'is_vegan_certified',
    ];

    /** @param array<string, list<string>> $requiredFieldsBySchema */
    public function __construct(private array $requiredFieldsBySchema = []) {}

    public function validate(AiArtifact $artifact): void
    {
        $problems = [];

        if ($artifact->schemaVersion !== $artifact->capability->schemaVersion()) {
            $problems[] = "şema sürümü beklenen {$artifact->capability->schemaVersion()} değil, {$artifact->schemaVersion}";
        }

        $names = array_map(
            static fn (FieldValue $field): string => $field->name,
            $artifact->fields,
        );

        foreach ($names as $name) {
            if (in_array($name, self::FORBIDDEN_FIELDS, true)) {
                $problems[] = "yasak alan: {$name} — AI yalnız '".self::ALLERGEN_CANDIDATE_FIELD."' bildirebilir";
            }
        }

        foreach ($this->requiredFieldsBySchema[$artifact->schemaVersion] ?? [] as $required) {
            if (! in_array($required, $names, true)) {
                $problems[] = "eksik alan: {$required}";
            }
        }

        foreach ($artifact->fields as $field) {
            if ($field->confidence < 0.0 || $field->confidence > 1.0) {
                $problems[] = "{$field->name}: güven 0..1 aralığında olmalı";
            }
        }

        if ($problems !== []) {
            throw new SchemaViolationException($artifact->schemaVersion, $problems);
        }
    }

    public function forbiddenFields(): array
    {
        return self::FORBIDDEN_FIELDS;
    }

    /** @param list<string> $required */
    public function withRequired(Capability $capability, array $required): self
    {
        $map = $this->requiredFieldsBySchema;
        $map[$capability->schemaVersion()] = $required;

        return new self($map);
    }
}
