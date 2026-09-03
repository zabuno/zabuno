<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\EmbeddingPort;
use App\Application\Ai\Port\OcrPort;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Domain\Ai\SourceRef;

/**
 * Sağlayıcısız geliştirme ve CI için deterministik sahte sağlayıcı.
 *
 * Neden gerçek bir bileşen: Faz 1'in kabul ölçütü, bütün zincirin bir
 * sağlayıcı anahtarı OLMADAN uçtan uca koşmasıdır (`docs/51` §3.6/2). Bu
 * olmadan AI kodu ancak birinin API anahtarı varken sınanabilirdi — yani
 * CI'da hiç sınanamazdı.
 *
 * DETERMİNİSTİKTİR: aynı girdi aynı çıktıyı verir. Rastgele davranan bir
 * sahte sağlayıcı, testleri kırılgan yapardı.
 */
final readonly class FakeProvider implements EmbeddingPort, OcrPort, StructuredGenerationPort, VisionExtractionPort
{
    private function deployment(): ModelDeployment
    {
        return new ModelDeployment('local', 'fake', 'deterministic-fake', 'none');
    }

    public function read(AiRequest $request, string $filePath): AiArtifact
    {
        $hash = hash('sha256', $filePath);

        return new AiArtifact(
            capability: $request->capability,
            model: $this->deployment(),
            promptVersion: 'fake.v1',
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue(
                    name: 'text',
                    value: '',
                    confidence: 1.0,
                    uncertain: false,
                    source: new SourceRef($hash, page: 1, boundingBox: null),
                ),
            ],
        );
    }

    /**
     * Görselden menü çıkarımı — sağlayıcı gelene kadarki dayanak.
     *
     * Şekil GERÇEKÇİ tutulur: her alan bir MENÜ SATIRIDIR ve satır başına
     * güven taşır. Düz bir metin döndürseydi, onay hattının geri kalanı
     * gerçek sağlayıcıya kadar hiç çalıştırılamazdı.
     */
    public function extract(AiRequest $request, array $filePaths): AiArtifact
    {
        $source = new SourceRef(hash('sha256', implode('|', $filePaths)), page: 1, boundingBox: null);

        return new AiArtifact(
            capability: $request->capability,
            model: $this->deployment(),
            promptVersion: 'fake.v1',
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue('row.1', [
                    'category' => 'Çorbalar',
                    'product' => 'Mercimek Çorbası',
                    'priceMinorAmount' => 5250,
                    'currencyCode' => 'TRY',
                ], 0.97, false, $source),
                new FieldValue('row.2', [
                    'category' => 'Çorbalar',
                    'product' => 'Ezogelin Çorbası',
                    'priceMinorAmount' => 5250,
                    'currencyCode' => 'TRY',
                ], 0.94, false, $source),
                /*
                 * Fiyatı OKUNAMAYAN bir satır her koşuda üretilir.
                 *
                 * Sebebi `generate()` ile aynı: her zaman kesin cevap veren
                 * bir sahte sağlayıcı, ürünün belirsizlik yolunu hiç
                 * çalıştırmaz ve o yol ilk kez üretimde denenirdi.
                 */
                new FieldValue('row.3', [
                    'category' => 'Kebaplar',
                    'product' => 'Adana Kebap',
                    'priceMinorAmount' => null,
                    'currencyCode' => 'TRY',
                ], 0.41, true, $source),
            ],
        );
    }

    public function generate(AiRequest $request): AiArtifact
    {
        /*
         * `product.description` gerçek bir alan şekli döndürür — CI'da bu
         * yeteneğin onay/uygulama yolunun (`ApplyProductDescriptionDraft`)
         * gerçekten çalıştığı, sağlayıcı anahtarı OLMADAN sınanabilsin diye
         * (`docs/51` §3.6/2 ile aynı kabul ölçütü).
         */
        if ($request->capability === Capability::ProductDescription) {
            return new AiArtifact(
                capability: $request->capability,
                model: $this->deployment(),
                promptVersion: 'fake.v1',
                schemaVersion: $request->capability->schemaVersion(),
                fields: [
                    new FieldValue('description', 'Taze malzemelerle hazırlanır.', 0.85, false),
                ],
            );
        }

        /*
         * Diğer yetenekler için sahte sağlayıcı BİLEREK `uncertain` bir alan
         * döndürür.
         *
         * Sebebi: belirsizliğin taşındığı yolun her koşuda sınanması. Her
         * zaman kesin cevap veren bir sahte sağlayıcı, ürünün belirsizlik
         * işleme yolunu hiç çalıştırmazdı ve o yol ilk kez üretimde
         * denenirdi.
         */
        return new AiArtifact(
            capability: $request->capability,
            model: $this->deployment(),
            promptVersion: 'fake.v1',
            schemaVersion: $request->capability->schemaVersion(),
            fields: [
                new FieldValue('name', '', 1.0, false),
                new FieldValue('price', null, 0.0, true),
            ],
        );
    }

    /**
     * Metin → sahte vektör — harf-frekans histogramı.
     *
     * Rastgele DEĞİL: benzer metinler (büyük/küçük harf farkı gibi) benzer
     * vektör üretmeli ki yinelenen-terim tespitinin benzerlik matematiği
     * (kosinüs benzerliği) sağlayıcı anahtarı OLMADAN CI'da gerçekten
     * sınanabilsin — aynı kabul ölçütü: `docs/51` §3.6/2.
     *
     * @param  list<string>  $texts
     * @return list<array{vector: list<float>, model: string}>
     */
    public function embed(int $workspaceId, array $texts): array
    {
        return array_map(
            fn (string $text): array => [
                'vector' => $this->letterHistogram($text),
                'model' => $this->deployment()->identity(),
            ],
            $texts,
        );
    }

    /** @return list<float> */
    private function letterHistogram(string $text): array
    {
        $buckets = array_fill(0, 32, 0.0);
        $lower = mb_strtolower($text);

        foreach (mb_str_split($lower) as $char) {
            $bucket = ord($char[0] ?? "\0") % 32;
            $buckets[$bucket]++;
        }

        $magnitude = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $buckets)));

        if ($magnitude === 0.0) {
            return $buckets;
        }

        return array_map(static fn (float $v): float => $v / $magnitude, $buckets);
    }
}
