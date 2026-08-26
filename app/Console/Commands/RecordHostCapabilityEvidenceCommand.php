<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Platform\UseCase\RecordHostCapabilityEvidence;
use Illuminate\Console\Command;

final class RecordHostCapabilityEvidenceCommand extends Command
{
    protected $signature = 'platform:evidence:host-capability';

    protected $description = 'Probe this host for the capabilities the product depends on, record one evidence row, and print the degradation plan.';

    public function handle(RecordHostCapabilityEvidence $useCase): int
    {
        $result = $useCase->execute();

        $this->info('Host capability evidence recorded (#'.$result['id'].').');

        $this->table(
            ['Capability', 'Value'],
            array_map(
                static fn (string $key, bool|string $value): array => [
                    $key,
                    is_bool($value) ? ($value ? 'yes' : 'no') : $value,
                ],
                array_keys($result['capabilities']),
                array_values($result['capabilities']),
            ),
        );

        if ($result['degradations'] === []) {
            $this->info('No degradation needed on this host.');

            return self::SUCCESS;
        }

        // Eksik yetenek hata değildir; planlı bir düşüştür. Komut bu yüzden
        // başarılı biter — aksi hâlde her paylaşımlı host'ta CI kırılırdı.
        $this->warn('Planned degradations on this host:');

        foreach ($result['degradations'] as $degradation) {
            $this->line('  - '.$degradation);
        }

        return self::SUCCESS;
    }
}
