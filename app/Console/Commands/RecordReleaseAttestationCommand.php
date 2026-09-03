<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Security\UseCase\RecordReleaseAttestation;
use App\Domain\Security\ReleaseAttestationKey;
use Illuminate\Console\Command;

/**
 * Sunucudan tanıklık kaydı — `docs/98` FF-63.
 *
 *   php artisan platform:evidence:attest qr-physical-scan --status=passed \
 *     --summary="iPhone 15 ile basılı QR taranıp menü açıldı" \
 *     --payload=device=iPhone-15 --payload=menu=ana-menu
 *
 *   php artisan platform:evidence:attest rpo-rto-decision --status=decided \
 *     --summary="Günlük yedek; 24 saat veri kaybı, 4 saat kesinti kabul" \
 *     --payload=rpo_hours=24 --payload=rto_hours=4
 *
 * Aynı iş panelden de yapılabilir; komut, paneli olmayan bir sunucuda
 * (deploy sonrası) kaydı düşürmek içindir.
 */
final class RecordReleaseAttestationCommand extends Command
{
    protected $signature = 'platform:evidence:attest
        {key : qr-physical-scan | rpo-rto-decision | owasp-asvs-audit}
        {--status= : passed|failed|decided|recorded (maddeye göre)}
        {--summary= : tanığın kendi cümlesi}
        {--reference= : rapor/belge adresi ya da depo yolu (opsiyonel)}
        {--payload=* : anahtar=değer (ör. device=iPhone-15, rpo_hours=24)}';

    protected $description = 'Record a human attestation for a release-readiness item (QR field scan, RPO/RTO decision, ASVS audit reference).';

    public function handle(RecordReleaseAttestation $useCase): int
    {
        $key = ReleaseAttestationKey::tryFrom((string) $this->argument('key'));

        if ($key === null) {
            $this->error('Bilinmeyen madde. Geçerli: '.implode(', ', array_map(static fn ($k) => $k->value, ReleaseAttestationKey::cases())));

            return self::FAILURE;
        }

        $payload = [];
        foreach ((array) $this->option('payload') as $pair) {
            [$name, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $payload[trim($name)] = trim($value);
        }

        try {
            $id = $useCase->execute(
                $key,
                (string) $this->option('status'),
                (string) $this->option('summary'),
                $this->option('reference') === null ? null : (string) $this->option('reference'),
                $payload,
                null,
            );
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Tanıklık kaydedildi (#{$id}): {$key->value} → ".$this->option('status'));

        return self::SUCCESS;
    }
}
