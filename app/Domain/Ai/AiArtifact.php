<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * AI'nın ürettiği HER ŞEY — kaynağıyla birlikte.
 *
 * Bir artifact asla doğrudan uygulanmaz. Taslakta durur, insan inceler,
 * onaylanınca typed bir sunucu komutuna dönüşür ve yetki YENİDEN doğrulanır
 * (`docs/14` §9a adım 13).
 *
 * Taşıdığı üstveri, ürünün "bu nereden geldi" sorusuna cevap verebilmesinin
 * tek yoludur: model kimliği, prompt sürümü, şema sürümü ve alan bazında
 * kaynak.
 */
final readonly class AiArtifact
{
    /** @param list<FieldValue> $fields */
    public function __construct(
        public Capability $capability,
        public ModelDeployment $model,
        public string $promptVersion,
        public string $schemaVersion,
        public array $fields,
        /*
            Birincil aday çalışma zamanında başarısız olup istek ikinci bir
            sağlayıcıya düştüğünde `true` olur (`docs/97` R12). Sessiz geçiş
            yasak (`docs/51` UNK-03) — inceleme ekranı bunu kullanıcıya
            gösterir. Varsayılan `false`: yedeğe düşmeyen her çağrı için
            mevcut çağıranlar değişmeden kalır.
        */
        public bool $usedFallback = false,
    ) {}

    /** Belirsiz işaretli alanlar — inceleme ekranı bunları öne çıkarır. */
    public function uncertainFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldValue $field): bool => $field->uncertain,
        ));
    }

    /**
     * Bu artifact insan incelemesi olmadan yayınlanabilir mi?
     *
     * Cevap HER ZAMAN hayırdır. Metot yine de var, çünkü çağıran tarafın
     * "acaba" diye düşünmesi gereken tek yer burasıdır ve cevabı burada
     * görür — kendi dalını yazmaz.
     */
    public function isPublishableWithoutReview(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability->value,
            'model' => $this->model->identity(),
            'promptVersion' => $this->promptVersion,
            'schemaVersion' => $this->schemaVersion,
            'fields' => array_map(
                static fn (FieldValue $field): array => $field->toArray(),
                $this->fields,
            ),
            'usedFallback' => $this->usedFallback,
        ];
    }
}
