<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * URL motorunun çekirdeği: bir isteği kanonik biçimine indirger.
 *
 * Saf bir dönüşümdür — istek nesnesi, veritabanı veya yönlendirme bilmez.
 * Böylece kural tek yerde yaşar ve hem middleware hem canonical etiketi hem
 * sitemap aynı cevabı alır. İkinci bir yerde "küçük bir düzeltme" yapılırsa
 * o an iki farklı doğru ortaya çıkar; bu sınıfın var olma sebebi budur.
 */
final class UrlNormalizer
{
    public function __construct(private readonly UrlPolicy $policy) {}

    /**
     * @param  array<string, string|list<string>>  $rawQuery  Ham sorgu (anahtar → değer(ler))
     */
    public function normalize(string $path, array $rawQuery = []): NormalizedUrl
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedQuery = $this->normalizeQuery($rawQuery);

        $changed = $normalizedPath !== $this->asPath($path)
            || $normalizedQuery !== $this->rebuild($rawQuery);

        return new NormalizedUrl($normalizedPath, $normalizedQuery, $changed);
    }

    /**
     * Sorguda aynı anahtar birden çok kez var mı?
     *
     * PHP sonuncuyu alır, bazı ara katmanlar ilkini. İki katmanın aynı isteği
     * farklı okuması tek başına bir güvenlik açığıdır — yetki bir katmanda
     * `role=user`, diğerinde `role=admin` okunabilir.
     */
    public function hasDuplicateQueryKeys(string $rawQueryString): bool
    {
        if ($rawQueryString === '') {
            return false;
        }

        $seen = [];

        foreach (explode('&', $rawQueryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $key = urldecode(explode('=', $pair, 2)[0]);

            // PHP'nin dizi sözdizimi (`items[]=a&items[]=b`) meşru bir
            // çokluktur ve yinelenen anahtar sayılmaz.
            if (str_ends_with($key, '[]') || preg_match('/\[[^\]]*\]$/', $key) === 1) {
                continue;
            }

            if (isset($seen[$key])) {
                return true;
            }

            $seen[$key] = true;
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = $this->asPath($path);

        if ($this->policy->collapsesDuplicateSlashes()) {
            $path = (string) preg_replace('#/{2,}#', '/', $path);
        }

        if ($this->policy->removesTrailingSlash() && $path !== '/') {
            $path = rtrim($path, '/');

            if ($path === '') {
                $path = '/';
            }
        }

        // Harf katlama YALNIZ politikada açıkça izin verilen statik
        // öneklerde yapılır. Opak kimlik taşıyan bir yolu katlamak, basılmış
        // bir QR kodunu geçersiz kılmaktır (bkz. `config/url-policy.php`).
        if ($this->policy->allowsCaseFolding($path)) {
            $path = strtolower($path);
        }

        return $path;
    }

    /** @param array<string, string|list<string>> $rawQuery */
    private function normalizeQuery(array $rawQuery): string
    {
        // İzleme parametreleri BURADA silinmez: yönlendirme onları ölçüm
        // yapılmadan yok ederdi. Yalnız canonical adresin dışında bırakılırlar
        // (bkz. `CanonicalUrl`).
        return $this->rebuild($rawQuery);
    }

    /** @param array<string, string|list<string>> $rawQuery */
    private function rebuild(array $rawQuery): string
    {
        if ($rawQuery === []) {
            return '';
        }

        // Sıralama deterministiktir: aynı sorgu her zaman aynı dizeyi üretir,
        // yani aynı önbellek anahtarını ve aynı canonical adresi.
        ksort($rawQuery);

        return http_build_query($rawQuery, '', '&', PHP_QUERY_RFC3986);
    }

    private function asPath(string $path): string
    {
        return $path === '' ? '/' : '/'.ltrim($path, '/');
    }
}
