<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Credential;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformConnectionAdminPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\ConnectionHealth;
use App\Domain\Platform\Credential\CredentialConnection;
use App\Domain\Platform\Credential\CredentialFieldStatus;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use App\Domain\Platform\Credential\CredentialStatus;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

/**
 * Kasanın tek gerçek uygulaması — üç portu da karşılar.
 *
 * Sırlar `secret_ciphertext`'te uygulama anahtarıyla şifreli durur. Düz
 * alanlar ayrı sütunda. `secret_hints` yalnız son-4 maskesini taşır.
 * Öncelik resolve'de KASA > env: kasa doldurulunca sunucu `.env`'inin önüne
 * geçer, boşken env yedeği çalışmaya devam eder (`docs/93` FF-36 aktarımı).
 *
 * Faz 3'ten beri veri `Provider → Connection (N adet)` hiyerarşisindedir
 * (`docs/95`). Eski sağlayıcı-düzeyi yüzey (`PlatformCredentialAdminPort`)
 * KIRILMADI: o yüzeyin yazdığı yer, sağlayıcının VARSAYILAN bağlantısıdır
 * — çalışan bir paneli hiçbir kazanç karşılığı bozmamak için.
 */
final readonly class EloquentPlatformCredentialStore implements CredentialResolverPort, PlatformConnectionAdminPort, PlatformCredentialAdminPort
{
    private const TABLE = 'platform_credential_connections';

    private const AUDITS = 'platform_credential_audits';

    private const DEFAULT_LABEL = 'Varsayılan';

    public function __construct(private Encrypter $encrypter) {}

    // === Bağlantı portu (Faz 3) =========================================

    public function connections(?CredentialProvider $provider = null): array
    {
        $query = DB::table(self::TABLE)->orderBy('id');

        if ($provider !== null) {
            $query->where('provider', $provider->value);
        }

        $out = [];

        foreach ($query->get() as $row) {
            $connection = $this->hydrate($row);
            if ($connection !== null) {
                $out[] = $connection;
            }
        }

        return $out;
    }

    public function connection(int $id): ?CredentialConnection
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function createConnection(
        CredentialProvider $provider,
        string $label,
        CredentialScope $scope,
        ?int $workspaceId,
        array $values,
        ?int $byUserId,
    ): int {
        $label = trim($label);

        if ($label === '') {
            throw new \InvalidArgumentException(
                'Bağlantı etiketi zorunlu — sır görünmediği için iki kartı ayırt eden tek şey odur.',
            );
        }

        if ($scope === CredentialScope::TenantByok && $workspaceId === null) {
            throw new \InvalidArgumentException('BYOK bağlantısı bir workspace adı taşımak zorunda.');
        }

        if ($scope === CredentialScope::PlatformOwned && $workspaceId !== null) {
            throw new \InvalidArgumentException('Platform bağlantısı bir workspace sahiplenemez.');
        }

        $this->assertKnownFields($provider, $values);

        [$plain, $secrets, $hints] = $this->merge($provider, $values, [], [], []);

        $id = (int) DB::table(self::TABLE)->insertGetId([
            'provider' => $provider->value,
            'label' => $label,
            'scope' => $scope->value,
            'workspace_id' => $workspaceId,
            'plain_fields' => $this->encodeJson($plain),
            'secret_ciphertext' => $this->encodeSecrets($secrets),
            'secret_hints' => $this->encodeJson($hints),
            'state' => 'active',
            'health_status' => ConnectionHealth::Unknown->value,
            'last_rotated_at' => now(),
            'set_by_user_id' => $byUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit($provider, 'created', $byUserId, $id);

        return $id;
    }

    public function updateConnection(int $id, array $values, ?int $byUserId): void
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if ($row === null) {
            throw new \InvalidArgumentException("Bağlantı bulunamadı: {$id}");
        }

        $provider = CredentialProvider::from((string) $row->provider);
        $this->assertKnownFields($provider, $values);

        [$plain, $secrets, $hints] = $this->merge(
            $provider,
            $values,
            $this->plainFields($row),
            $this->decryptSecrets($row),
            $this->secretHints($row),
        );

        DB::table(self::TABLE)->where('id', $id)->update([
            'plain_fields' => $this->encodeJson($plain),
            'secret_ciphertext' => $this->encodeSecrets($secrets),
            'secret_hints' => $this->encodeJson($hints),
            'last_rotated_at' => now(),
            'set_by_user_id' => $byUserId,
            'updated_at' => now(),
        ]);

        $this->audit($provider, 'set', $byUserId, $id);
    }

    public function renameConnection(int $id, string $label, ?int $byUserId): void
    {
        $label = trim($label);

        if ($label === '') {
            throw new \InvalidArgumentException('Bağlantı etiketi boş olamaz.');
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if ($row === null) {
            throw new \InvalidArgumentException("Bağlantı bulunamadı: {$id}");
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'label' => $label,
            'updated_at' => now(),
        ]);

        $this->audit(CredentialProvider::from((string) $row->provider), 'renamed', $byUserId, $id);
    }

    public function disableConnection(int $id, ?int $byUserId): void
    {
        $this->setState($id, 'disabled', 'disabled', $byUserId);
    }

    public function enableConnection(int $id, ?int $byUserId): void
    {
        $this->setState($id, 'active', 'enabled', $byUserId);
    }

    public function markHealth(int $id, ConnectionHealth $health, ?int $byUserId): void
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if ($row === null) {
            return;
        }

        $previous = (string) ($row->health_status ?? ConnectionHealth::Unknown->value);

        DB::table(self::TABLE)->where('id', $id)->update([
            'health_status' => $health->value,
            'last_health_check_at' => now(),
            'updated_at' => now(),
        ]);

        // Yalnız DEĞİŞİM denetime yazılır: her sağlık yoklaması bir satır
        // yazsaydı, denetim izi gürültüye boğulur ve gerçek olay (bir
        // hesabın düşmesi) görünmez olurdu (`docs/14` §2a).
        if ($previous !== $health->value) {
            $this->audit(
                CredentialProvider::from((string) $row->provider),
                'health:'.$health->value,
                $byUserId,
                $id,
            );
        }
    }

    // === Admin portu, sağlayıcı düzeyi (sır GERİ OKUNMAZ) ================

    public function all(): array
    {
        return array_map(
            fn (CredentialProvider $provider): CredentialStatus => $this->status($provider),
            CredentialProvider::cases(),
        );
    }

    public function status(CredentialProvider $provider): CredentialStatus
    {
        $row = $this->defaultRow($provider);
        $plain = $this->plainFields($row);
        $hints = $this->secretHints($row);
        $state = $row->state ?? 'unset';

        [$fields, $requiredSatisfied] = $this->fieldStatuses($provider, $plain, $hints);

        return new CredentialStatus(
            provider: $provider,
            configured: $row !== null && $state === 'active' && $requiredSatisfied,
            state: $state,
            fields: $fields,
            lastRotatedAt: isset($row->last_rotated_at) && $row->last_rotated_at !== null
                ? (string) $row->last_rotated_at
                : null,
        );
    }

    public function put(CredentialProvider $provider, array $values, ?int $byUserId): void
    {
        $this->assertKnownFields($provider, $values);

        $row = $this->defaultRow($provider);

        if ($row === null) {
            $this->createConnection(
                $provider,
                self::DEFAULT_LABEL,
                CredentialScope::PlatformOwned,
                null,
                $values,
                $byUserId,
            );

            return;
        }

        // Kapalı bir varsayılanı yeniden yazmak onu AÇAR: eski yüzeyin
        // sözleşmesi buydu (`put` her zaman state=active yazıyordu) ve
        // panelin "kaydet" düğmesi hâlâ aynı şeyi vaat ediyor.
        $this->updateConnection((int) $row->id, $values, $byUserId);
        DB::table(self::TABLE)->where('id', $row->id)->update([
            'state' => 'active',
            'updated_at' => now(),
        ]);
    }

    public function disable(CredentialProvider $provider, ?int $byUserId = null): void
    {
        // Sağlayıcı düzeyinde "kapat" demek, o sağlayıcının PLATFORM
        // bağlantılarının tamamını kapatmaktır — eski yüzeyde sağlayıcı ile
        // bağlantı aynı şeydi ve "kapat" düğmesi o sağlayıcıyı tümden
        // durdurmayı vaat ediyordu. Tenant BYOK bağlantıları bundan
        // ETKİLENMEZ: onlar platformun kapatma kararının konusu değil.
        $ids = DB::table(self::TABLE)
            ->where('provider', $provider->value)
            ->where('scope', CredentialScope::PlatformOwned->value)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->disableConnection((int) $id, $byUserId);
        }
    }

    // === Resolver portu (yalnız tüketici) ===============================

    public function resolve(CredentialProvider $provider): array
    {
        $row = $this->routableRow($provider);

        return $this->resolveRow($provider, $row);
    }

    public function isConfigured(CredentialProvider $provider): bool
    {
        return $this->resolve($provider) !== [];
    }

    // === İç yardımcılar =================================================

    private function setState(int $id, string $state, string $action, ?int $byUserId): void
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if ($row === null) {
            return;
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'state' => $state,
            'updated_at' => now(),
        ]);

        $this->audit(CredentialProvider::from((string) $row->provider), $action, $byUserId, $id);
    }

    private function hydrate(object $row): ?CredentialConnection
    {
        $provider = CredentialProvider::tryFrom((string) $row->provider);

        if ($provider === null) {
            // Kaldırılmış bir sağlayıcının satırı: kasada durur (silmiyoruz,
            // veri kaybı olmasın) ama panele bir "bilinmeyen" kart çıkarmanın
            // anlamı yok — o kartın alanları da bilinemezdi.
            return null;
        }

        [$fields, $requiredSatisfied] = $this->fieldStatuses(
            $provider,
            $this->plainFields($row),
            $this->secretHints($row),
        );

        $state = (string) ($row->state ?? 'unset');

        return new CredentialConnection(
            id: (int) $row->id,
            provider: $provider,
            label: (string) $row->label,
            scope: CredentialScope::from((string) $row->scope),
            workspaceId: $row->workspace_id === null ? null : (int) $row->workspace_id,
            configured: $state === 'active' && $requiredSatisfied,
            state: $state,
            health: ConnectionHealth::from((string) ($row->health_status ?? 'unknown')),
            fields: $fields,
            lastRotatedAt: $row->last_rotated_at === null ? null : (string) $row->last_rotated_at,
            lastHealthCheckAt: $row->last_health_check_at === null
                ? null
                : (string) $row->last_health_check_at,
        );
    }

    /**
     * @param  array<string, mixed>  $plain
     * @param  array<string, string>  $hints
     * @return array{list<CredentialFieldStatus>, bool}
     */
    private function fieldStatuses(CredentialProvider $provider, array $plain, array $hints): array
    {
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

        return [$fields, $requiredSatisfied];
    }

    /**
     * Panelin/eski yüzeyin gördüğü satır: sağlayıcının EN ESKİ platform
     * bağlantısı. "İlk yazılan" belirlenebilir bir kuraldır; "en yenisi"
     * olsaydı, ikinci bir bağlantı eklemek panelin gösterdiği kartı sessizce
     * değiştirirdi.
     */
    private function defaultRow(CredentialProvider $provider): ?object
    {
        return DB::table(self::TABLE)
            ->where('provider', $provider->value)
            ->where('scope', CredentialScope::PlatformOwned->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * Çözüme aday satır: platform kapsamlı, AÇIK, sağlığı elverişli ve
     * zorunlu alanları tam olan ilk bağlantı.
     *
     * BYOK satırları sorgunun kendisinde elenir — bir `if` ile değil.
     * Filtre unutulabilir; `where('scope', ...)` unutulursa test kırılır.
     */
    private function routableRow(CredentialProvider $provider): ?object
    {
        $rows = DB::table(self::TABLE)
            ->where('provider', $provider->value)
            ->where('scope', CredentialScope::PlatformOwned->value)
            ->where('state', 'active')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $health = ConnectionHealth::from((string) ($row->health_status ?? 'unknown'));

            if (! $health->isRoutable()) {
                continue;
            }

            if ($this->resolveRow($provider, $row) !== []) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Bir satırı (ya da satırsız hâlde yalnız env'i) tam bir alan kümesine
     * çevirir; zorunlu alanı eksikse BOŞ döner — yarım bir yapılandırma
     * sessizce "çalışıyor" görünmemeli.
     *
     * @return array<string, string>
     */
    private function resolveRow(CredentialProvider $provider, ?object $row): array
    {
        $merged = $this->envValues($provider);

        if ($row !== null && ($row->state ?? null) === 'active') {
            foreach ($this->plainFields($row) as $name => $value) {
                $merged[$name] = (string) $value;
            }
            foreach ($this->decryptSecrets($row) as $name => $value) {
                $merged[$name] = (string) $value;
            }
        }

        foreach ($provider->fields() as $field) {
            if ($field->required && ($merged[$field->name] ?? '') === '') {
                return [];
            }
        }

        foreach ($provider->fields() as $field) {
            if (! $field->secret && $field->default !== null && ($merged[$field->name] ?? '') === '') {
                $merged[$field->name] = $field->default;
            }
        }

        $out = [];
        foreach ($provider->fieldNames() as $name) {
            if (($merged[$name] ?? '') !== '') {
                $out[$name] = (string) $merged[$name];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $plain
     * @param  array<string, string>  $secrets
     * @param  array<string, string>  $hints
     * @return array{array<string, mixed>, array<string, string>, array<string, string>}
     */
    private function merge(
        CredentialProvider $provider,
        array $values,
        array $plain,
        array $secrets,
        array $hints,
    ): array {
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

        return [$plain, $secrets, $hints];
    }

    /** @param array<string, string> $values */
    private function assertKnownFields(CredentialProvider $provider, array $values): void
    {
        foreach (array_keys($values) as $name) {
            if ($provider->field((string) $name) === null) {
                throw new \InvalidArgumentException(
                    "Bilinmeyen alan '{$name}' — {$provider->value} şemasında yok.",
                );
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function encodeJson(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, string> $secrets */
    private function encodeSecrets(array $secrets): ?string
    {
        return $secrets === [] ? null : $this->encrypter->encryptString($this->encodeJson($secrets));
    }

    /** Append-only denetim satırı — SIRSIZ. Yalnız kim/ne/ne zaman/hangi bağlantı. */
    private function audit(CredentialProvider $provider, string $action, ?int $actor, ?int $connectionId): void
    {
        DB::table(self::AUDITS)->insert([
            'provider' => $provider->value,
            'connection_id' => $connectionId,
            'action' => $action,
            'actor_user_id' => $actor,
            'created_at' => now(),
        ]);
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
            // AI sağlayıcıları için env yedeği yok — kasa doldurulunca çalışır.
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
