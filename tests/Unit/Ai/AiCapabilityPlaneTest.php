<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Application\Ai\Exception\SchemaViolationException;
use App\Application\Ai\Port\AiRequest;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Domain\Ai\SourceRef;
use App\Infrastructure\Ai\ArtifactSchemaValidator;
use App\Infrastructure\Ai\FakeProvider;
use PHPUnit\Framework\TestCase;

/**
 * AI-S1-01 — Capability Plane çekirdeği.
 *
 * Bu testler `docs/51` §3.6'daki kabul ölçütlerini dondurur. Sırayla
 * okunduklarında Faz 1'in ne vaat ettiğini anlatırlar.
 */
final class AiCapabilityPlaneTest extends TestCase
{
    // --- Kaynak modeli ---------------------------------------------------

    public function test_every_generated_field_can_carry_where_it_came_from(): void
    {
        $source = new SourceRef('abc123', page: 2, boundingBox: ['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.05]);
        $field = new FieldValue('price', '42.50', 0.94, false, $source);

        // "Bu fiyat nereden geldi" sorusu menü YAYINLANDIKTAN sonra sorulur;
        // cevabı o an üretilemez, üretim anında saklanmış olması gerekir.
        self::assertSame('abc123', $field->source?->fileHash);
        self::assertSame(2, $field->source?->page);
        self::assertSame(0.1, $field->source?->boundingBox['x']);
    }

    public function test_the_model_identity_includes_its_quantization(): void
    {
        $full = new ModelDeployment('local', 'sidecar-1', 'some-model', 'q4');
        $none = new ModelDeployment('google', 'project-a', 'some-model');

        // Nicemleme model kimliğinin PARÇASIDIR: aynı model farklı düzeyde
        // farklı kalite verir ve sessizce değişirse kimse fark etmez.
        self::assertSame('local:sidecar-1:some-model@q4', $full->identity());
        self::assertSame('google:project-a:some-model', $none->identity());
    }

    // --- Belirsizlik gizlenmez -------------------------------------------

    public function test_an_uncertain_field_is_unusable_however_confident_it_claims_to_be(): void
    {
        $flagged = new FieldValue('price', '9.99', 0.99, true);
        $clean = new FieldValue('price', '9.99', 0.95, false);

        // İşareti MODEL koymuştur ve o, güven puanından daha güçlü bir
        // sinyaldir. Tersi olsaydı yüksek güvenli bir uydurma geçerdi.
        self::assertFalse($flagged->isUsableWithoutReview(0.90));
        self::assertTrue($clean->isUsableWithoutReview(0.90));
    }

    public function test_no_artifact_is_ever_publishable_without_review(): void
    {
        $artifact = new AiArtifact(
            Capability::MenuExtract,
            new ModelDeployment('local', 'fake', 'm'),
            'p.v1',
            Capability::MenuExtract->schemaVersion(),
            [new FieldValue('name', 'Adana Kebap', 1.0, false)],
        );

        self::assertFalse($artifact->isPublishableWithoutReview());
        self::assertTrue(Capability::MenuExtract->requiresHumanApproval());
    }

    // --- Şema doğrulaması -------------------------------------------------

    public function test_a_response_that_misses_a_required_field_is_a_failure_not_a_partial_result(): void
    {
        $validator = (new ArtifactSchemaValidator)
            ->withRequired(Capability::MenuExtract, ['name', 'price']);

        $artifact = new AiArtifact(
            Capability::MenuExtract,
            new ModelDeployment('local', 'fake', 'm'),
            'p.v1',
            Capability::MenuExtract->schemaVersion(),
            [new FieldValue('name', 'Adana', 1.0, false)],
        );

        $this->expectException(SchemaViolationException::class);
        $validator->validate($artifact);
    }

    public function test_a_wrong_schema_version_is_refused(): void
    {
        $artifact = new AiArtifact(
            Capability::MenuExtract,
            new ModelDeployment('local', 'fake', 'm'),
            'p.v1',
            'menu-extract.v0',
            [],
        );

        $this->expectException(SchemaViolationException::class);
        (new ArtifactSchemaValidator)->validate($artifact);
    }

    // --- Alerjen sınırı (docs/16 AI-14) -----------------------------------

    public function test_the_model_may_never_claim_something_is_allergen_free(): void
    {
        $validator = new ArtifactSchemaValidator;

        foreach ($validator->forbiddenFields() as $forbidden) {
            $artifact = new AiArtifact(
                Capability::MenuExtract,
                new ModelDeployment('local', 'fake', 'm'),
                'p.v1',
                Capability::MenuExtract->schemaVersion(),
                [new FieldValue($forbidden, true, 1.0, false)],
            );

            try {
                $validator->validate($artifact);
                self::fail("'{$forbidden}' alanı reddedilmeliydi.");
            } catch (SchemaViolationException $e) {
                self::assertStringContainsString('candidate_allergens', $e->getMessage());
            }
        }
    }

    public function test_a_candidate_allergen_is_allowed(): void
    {
        $artifact = new AiArtifact(
            Capability::MenuExtract,
            new ModelDeployment('local', 'fake', 'm'),
            'p.v1',
            Capability::MenuExtract->schemaVersion(),
            [new FieldValue('candidate_allergens', ['süt'], 0.7, true)],
        );

        (new ArtifactSchemaValidator)->validate($artifact);
        self::assertTrue(true);
    }

    // --- Sahte sağlayıcı ---------------------------------------------------

    public function test_the_fake_provider_always_exercises_the_uncertain_path(): void
    {
        $artifact = (new FakeProvider)->generate(
            new AiRequest(Capability::MenuExtract, 1, 'çıkar'),
        );

        // Her zaman kesin cevap veren bir sahte sağlayıcı, ürünün belirsizlik
        // yolunu hiç çalıştırmazdı — o yol ilk kez üretimde denenirdi.
        self::assertNotEmpty($artifact->uncertainFields());
    }

    public function test_the_fake_provider_is_deterministic(): void
    {
        $provider = new FakeProvider;
        $request = new AiRequest(Capability::MenuExtract, 1, 'çıkar');

        self::assertEquals(
            $provider->generate($request)->toArray(),
            $provider->generate($request)->toArray(),
        );
    }
}
