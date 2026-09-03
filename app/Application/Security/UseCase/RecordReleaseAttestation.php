<?php

declare(strict_types=1);

namespace App\Application\Security\UseCase;

use App\Domain\Security\ReleaseAttestationKey;
use Illuminate\Support\Facades\DB;

/**
 * Bir insan tanıklığını kaydeder — `docs/98` FF-63.
 *
 * Doğrulama burada, HTTP'de değil: komut satırı ve API aynı kuralları
 * geçmeli. Kural üç: durum o maddenin izin verdiği kümeden; özet boş değil;
 * maddenin zorunlu yapılandırılmış alanları dolu.
 */
final class RecordReleaseAttestation
{
    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function execute(
        ReleaseAttestationKey $key,
        string $status,
        string $summary,
        ?string $reference,
        array $payload,
        ?int $byUserId,
    ): int {
        if (! in_array($status, $key->allowedStatuses(), true)) {
            throw new \InvalidArgumentException(
                "'{$key->value}' için durum '{$status}' geçersiz; izin verilenler: ".implode(', ', $key->allowedStatuses()),
            );
        }

        $summary = trim($summary);

        if ($summary === '') {
            throw new \InvalidArgumentException('Özet boş olamaz — tanıklık bir cümle ister.');
        }

        foreach ($key->requiredPayloadKeys() as $required) {
            if (! isset($payload[$required]) || $payload[$required] === '') {
                throw new \InvalidArgumentException("'{$key->value}' için '{$required}' alanı zorunlu.");
            }
        }

        $attestedAt = now();
        $encodedPayload = $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return (int) DB::table('release_attestations')->insertGetId([
            'key' => $key->value,
            'status' => $status,
            'summary' => $summary,
            'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
            'payload' => $encodedPayload,
            'attested_by_user_id' => $byUserId,
            'attested_at' => $attestedAt,
            'integrity_sha256' => self::digest($key->value, $status, $summary, $reference, $encodedPayload, $attestedAt->toIso8601String()),
            'created_at' => $attestedAt,
            'updated_at' => $attestedAt,
        ]);
    }

    public static function digest(
        string $key,
        string $status,
        string $summary,
        ?string $reference,
        ?string $payload,
        string $attestedAt,
    ): string {
        return hash('sha256', implode("\n", [$key, $status, $summary, (string) $reference, (string) $payload, $attestedAt]));
    }
}
