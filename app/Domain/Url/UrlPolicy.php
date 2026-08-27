<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * URL politikasının tiplenmiş okuyucusu.
 *
 * Politikanın DEĞERİ `config/url-policy.php`'de, NEDENİ `docs/38`'de yaşar.
 * Bu sınıf ikisini de tekrar etmez; yalnız değerleri güvenli tiplerle sunar
 * ve motorun her yerde aynı cevabı almasını sağlar.
 */
final class UrlPolicy
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function canonicalScheme(): string
    {
        return $this->string('canonical_scheme', 'https');
    }

    public function canonicalHost(): ?string
    {
        $host = $this->config['canonical_host'] ?? null;

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    /**
     * Kabul edilen Host başlıkları. Boşsa çağıran `APP_URL` host'una düşer.
     *
     * @return list<string>
     */
    public function trustedHosts(): array
    {
        return $this->list('trusted_hosts');
    }

    /**
     * Uygulamanın cevap vereceği Host'lar.
     *
     * Yapılandırma boşsa `APP_URL`'in host'una düşülür: host'u koda gömmek,
     * aynı yapının beş barındırıcıda çalışmasını imkânsız kılardı
     * (`docs/38` §8). Saf bir işlevdir — çerçevenin global durumuna
     * dokunmaz, bu yüzden test edilmesi bir yan etki bırakmaz.
     *
     * @return list<string>
     */
    public function resolvedTrustedHosts(?string $applicationUrl): array
    {
        $configured = $this->trustedHosts();

        if ($configured !== []) {
            return $configured;
        }

        $host = is_string($applicationUrl) ? parse_url($applicationUrl, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? [strtolower($host)] : [];
    }

    public function enforcesScheme(): bool
    {
        return (bool) ($this->config['enforce_scheme'] ?? false);
    }

    public function enforcesHost(): bool
    {
        return (bool) ($this->config['enforce_host'] ?? false) && $this->canonicalHost() !== null;
    }

    public function collapsesDuplicateSlashes(): bool
    {
        return (bool) ($this->config['collapse_duplicate_slashes'] ?? true);
    }

    public function removesTrailingSlash(): bool
    {
        return ($this->config['trailing_slash'] ?? 'never_except_root') === 'never_except_root';
    }

    public function rejectsDuplicateQueryKeys(): bool
    {
        return ($this->config['duplicate_query_keys'] ?? 'reject') === 'reject';
    }

    public function normalizationRedirectStatus(): int
    {
        return (int) ($this->config['normalization_redirect_status'] ?? 301);
    }

    /**
     * Bu yolun ilk segmenti harf katlamaya AÇIK mı?
     *
     * Varsayılan HAYIR'dır. Bilinmeyen bir yolu katlamak, o yolun opak bir
     * kimlik taşıyıp taşımadığını bilmeden onu bozma riskidir; ve bu üründe
     * o risk basılmış bir QR kodudur.
     */
    public function allowsCaseFolding(string $path): bool
    {
        $first = $this->firstSegment($path);

        if ($first === null) {
            return false; // kök: katlanacak bir şey yok
        }

        if (in_array(strtolower($first), $this->list('opaque_prefixes'), true)) {
            return false;
        }

        return in_array(strtolower($first), $this->list('lowercase_prefixes'), true);
    }

    /**
     * Bu yol arama motoruna kapalı mı? Karar ilk segmentten verilir, yani
     * `/app/herhangi-bir-sey` de kapalıdır.
     */
    public function isNoIndexPath(string $path): bool
    {
        $first = $this->firstSegment($path);

        return $first !== null && in_array(strtolower($first), $this->list('noindex_prefixes'), true);
    }

    /** @return list<string> */
    public function noIndexPrefixes(): array
    {
        return $this->list('noindex_prefixes');
    }

    /** @return list<string> */
    public function disallowPrefixes(): array
    {
        return $this->list('disallow_prefixes');
    }

    /** @return list<string> */
    public function trackingParameters(): array
    {
        return $this->list('tracking_parameters');
    }

    /** @return list<string> */
    public function reservedSlugs(): array
    {
        return $this->list('reserved_slugs');
    }

    public function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), $this->reservedSlugs(), true);
    }

    private function firstSegment(string $path): ?string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return null;
        }

        return explode('/', $trimmed)[0];
    }

    /** @return list<string> */
    private function list(string $key): array
    {
        $value = $this->config[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => strtolower((string) $item),
            $value,
        ));
    }

    private function string(string $key, string $default): string
    {
        $value = $this->config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
