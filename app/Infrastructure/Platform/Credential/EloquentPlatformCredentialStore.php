<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Credential;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialFieldStatus;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

/**
 * Kasanın tek gerçek uygulaması — hem admin (yalnız-yazılır) hem resolver
 * (yalnız-tüketici) portunu karşılar.
 *
 * Sırlar `secret_ciphertext`'te uygulama anahtarıyla şifreli durur. Düz
 * alanlar ayrı sütunda. `secret_hints` yalnız son-4 maskesini taşır. Öncelik
 * resolve'de KASA > env: kasa doldurulunca sunucu `.env`'inin önüne geçer,
 * boşken env yedeği çalışmaya devam eder (`docs/93` FF-36 aktarımı).
 */
final readonly class EloquentPlatformCredentialStore implements CredentialResolverPort, PlatformCredentialAdminPort
{
    private const TABLE = 'platform_credentials';

    private const AUDITS = 'platform_credential_audits';

    public function __construct(private Encrypter $encrypter) {}

    // === Admin portu (sır GERİ OKUNMAZ) ==================================

    public function all(): array
    {
        return array_map(
            fn (CredentialProvider $provider): CredentialStatus => $this->status($provider),
            CredentialProvider::cases(),
        );
    }

    public function status(CredentialProvider $provider): CredentialStatus
    {
        $row = $this->row($provider);
        $plain = $this->plainFields($row);
        $hints = $this->secretHints($row);
        $state = $row->state ?? 'unset';

        $fields = [];
        $requiredSatisfied = true;

        foreach ($provider->fields() as $field) {
            if ($field->secret) {
                $isSet = array_key_exists($field->name, $hints);
                $preview = $isSet ? (string) $hints[$field->name] : null;
            } else {
                $value = $plain[$field->name] ?? null;
                $isSet = is_string($value) && $value !== '';
                $preview = $isSet ? (string) $value : null;
            }

            if ($field->required && ! $isSet) {
                $requiredSatisfied = false;
            }

            $fields[] = new CredentialFieldStatus($field->name, $field->secret, $isSet, $preview);
        }

        $configured = $row !== null && $state === 'active' && $requiredSatisfied;

        return new CredentialStatus(
            provider: $provider,
            configured: $configured,
            state: $state,
            fields: $fields,
            lastRotatedAt: isset($row->last_rotated_at) && $row->last_rotated_at !== null
                ? (string) $row->last_rotated_at
                : null,
        );
    }

    public function put(CredentialProvider $provider, array $values, ?int $byUserId): void
    {
        foreach (array_keys($values) as $name) {
            if ($provider->field((string) $name) === null) {
                throw new \InvalidArgumentException(
                    "Bilinmeyen alan '{$name}' — {$provider->value} şemasında yok.",
                );
            }
        }

        $row = $this->row($provider);
        $plain = $this->plainFields($row);
        $secrets = $this->decryptSecrets($row);
        $hints = $this->secretHints($row);

        foreach ($provider->fields() as $field) {
            if (! array_key_exists($field->name, $values)) {
                continue; // Verilmeyen alan öncekini KORUR.
            }

            $value = (string) $values[$field->name];

            if ($field->secret) {
                // Boş bir sır "değiştirme" demektir, "sil" değil: panel sırrı
                // geri okuyamadığı için her kayıtta yeniden girmek zorunda
                // kalmamalı.
                if ($value !== '') {
                    $secrets[$field->name] = $value;
                    $hints[$field->name] = $this->mask($value);
                }
            } else {
                $plain[$field->name] = $value;
            }
        }

        DB::table(self::TABLE)->updateOrInsert(
            ['provider' => $provider->value],
            [
                'plain_fields' => json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'secret_ciphertext' => $secrets === [] ? null : $this->encrypter->encryptString(
                    (string) json_encode($secrets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ),
                'secret_hints' => json_encode($hints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'state' => 'active',
                'last_rotated_at' => now(),
                'set_by_user_id' => $byUserId,
                'updated_at' => now(),
                'created_at' => $row->created_at ?? now(),
            ],
        );

        $this->audit($provider, 'set', $byUserId);
    }

    public function disable(CredentialProvider $provider, ?int $byUserId = null): void
    {
        DB::table(self::TABLE)
            ->where('provider', $provider->value)
            ->update(['state' => 'disabled', 'updated_at' => now()]);

        $this->audit($provider, 'disabled', $byUserId);
    }

    // === Resolver portu (yalnız tüketici) ===============================

    public function resolve(CredentialProvider $provider): array
    {
        $merged = $this->envValues($provider);

        $row = $this->row($provider);
        if ($row !== null && ($row->state ?? null) === 'active') {
            foreach ($this->plainFields($row) as $name => $value) {
                $merged[$name] = (string) $value;
            }
            foreach ($this->decryptSecrets($row) as $name => $value) {
                $merged[$name] = (string) $value;
            }
        }

        // Zorunlu bir alan hiçbir kaynaktan gelmiyorsa, yetenek KULLANILAMAZ:
        // yarım bir yapılandırma sessizce "çalışıyor" görünmemeli.
        foreach ($provider->fields() as $field) {
            if ($field->required && ($merged[$field->name] ?? '') === '') {
                return [];
            }
        }

        // Zorunlu alanlar tamam: opsiyonel varsayılanları doldur.
        foreach ($provider->fields() as $field) {
            if (! $field->secret && $field->default !== null && ($merged[$field->name] ?? '') === '') {
                $merged[$field->name] = $field->default;
            }
        }

        // Yalnız şema alanları, yalnız dolu olanlar.
        $out = [];
        foreach ($provider->fieldNames() as $name) {
            if (($merged[$name] ?? '') !== '') {
                $out[$name] = (string) $merged[$name];
            }
        }

        return $out;
    }

    public function isConfigured(CredentialProvider $provider): bool
    {
        return $this->resolve($provider) !== [];
    }

    // === İç yardımcılar =================================================

    /**
     * Append-only denetim satırı — SIRSIZ. Yalnız kim/ne/ne zaman.
     */
    private function audit(CredentialProvider $provider, string $action, ?int $actor): void
    {
        DB::table(self::AUDITS)->insert([
            'provider' => $provider->value,
            'action' => $action,
            'actor_user_id' => $actor,
            'created_at' => now(),
        ]);
    }

    private function row(CredentialProvider $provider): ?object
    {
        return DB::table(self::TABLE)->where('provider', $provider->value)->first();
    }

    /** @return array<string, mixed> */
    private function plainFields(?object $row): array
    {
        if ($row === null || ! isset($row->plain_fields) || $row->plain_fields === null) {
            return [];
        }

        return (array) json_decode((string) $row->plain_fields, true);
    }

    /** @return array<string, string> */
    private function secretHints(?object $row): array
    {
        if ($row === null || ! isset($row->secret_hints) || $row->secret_hints === null) {
            return [];
        }

        return (array) json_decode((string) $row->secret_hints, true);
    }

    /** @return array<string, string> */
    private function decryptSecrets(?object $row): array
    {
        if ($row === null || ! isset($row->secret_ciphertext) || $row->secret_ciphertext === null) {
            return [];
        }

        return (array) json_decode(
            $this->encrypter->decryptString((string) $row->secret_ciphertext),
            true,
        );
    }

    /** @return array<string, string> */
    private function envValues(CredentialProvider $provider): array
    {
        $map = match ($provider) {
            CredentialProvider::Mailgun => [
                'domain' => 'services.mailgun.domain',
                'secret' => 'services.mailgun.secret',
                'endpoint' => 'services.mailgun.endpoint',
            ],
            CredentialProvider::Iyzico => [
                'api_key' => 'services.iyzico.sandbox.api_key',
                'secret_key' => 'services.iyzico.sandbox.secret_key',
                'base_url' => 'services.iyzico.sandbox.base_url',
            ],
            // openai/gemini için env yedeği yok — kasa doldurulunca çalışır.
            default => [],
        };

        $out = [];
        foreach ($map as $field => $configKey) {
            $value = config($configKey);
            if (is_string($value) && $value !== '') {
                $out[$field] = $value;
            }
        }

        return $out;
    }

    private function mask(string $value): string
    {
        $last4 = mb_substr($value, -4);

        return '••••'.$last4;
    }
}
