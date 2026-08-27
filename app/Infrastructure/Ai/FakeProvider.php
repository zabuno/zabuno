<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\OcrPort;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Domain\Ai\AiArtifact;
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
final readonly class FakeProvider implements OcrPort, StructuredGenerationPort
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

    public function generate(AiRequest $request): AiArtifact
    {
        /*
         * Sahte sağlayıcı BİLEREK `uncertain` bir alan döndürür.
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
}
