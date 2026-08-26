<?php

declare(strict_types=1);

namespace Tests\Unit\Url;

use App\Domain\Url\UrlNormalizer;
use App\Domain\Url\UrlPolicy;
use PHPUnit\Framework\TestCase;

/**
 * URL motorunun çekirdek kuralları.
 *
 * En kritik test `test_an_opaque_token_path_is_never_case_folded`'dır: yaygın
 * tavsiye "tüm path'i küçük harfe indir" der, ama bu üründe o kural basılmış
 * her QR kodunu geçersiz kılar. Kural burada dondurulmuştur.
 *
 * Requirement ID'leri: URL-NORM-SLASH-01, URL-NORM-CASE-02,
 * URL-NORM-OPAQUE-03, URL-NORM-QUERY-04, URL-NORM-DUPLICATE-05,
 * URL-NORM-IDEMPOTENT-06.
 */
final class UrlNormalizerTest extends TestCase
{
    private function normalizer(array $overrides = []): UrlNormalizer
    {
        return new UrlNormalizer(new UrlPolicy(array_merge([
            'canonical_scheme' => 'https',
            'collapse_duplicate_slashes' => true,
            'trailing_slash' => 'never_except_root',
            'lowercase_prefixes' => ['terms', 'app', 'platform'],
            'opaque_prefixes' => ['q', 'menu', 'invitations'],
            'duplicate_query_keys' => 'reject',
            'tracking_parameters' => ['utm_source'],
            'normalization_redirect_status' => 301,
        ], $overrides)));
    }

    // --- URL-NORM-SLASH-01 -------------------------------------------------

    public function test_a_trailing_slash_is_removed_everywhere_except_the_root(): void
    {
        self::assertSame('/terms', $this->normalizer()->normalize('/terms/')->path);
        self::assertSame('/', $this->normalizer()->normalize('/')->path, 'Kök adres kendisidir; ondan slash silmek adresi yok eder.');
    }

    public function test_duplicate_slashes_collapse_because_they_are_the_same_page_at_two_addresses(): void
    {
        self::assertSame('/app/menu', $this->normalizer()->normalize('//app//menu')->path);
    }

    // --- URL-NORM-CASE-02 --------------------------------------------------

    public function test_a_static_path_is_case_folded(): void
    {
        self::assertSame('/terms', $this->normalizer()->normalize('/Terms')->path);
        self::assertTrue($this->normalizer()->normalize('/TERMS')->changed);
    }

    // --- URL-NORM-OPAQUE-03 ------------------------------------------------

    public function test_an_opaque_token_path_is_never_case_folded(): void
    {
        // QR token'ı `[A-Za-z0-9_-]{43}` biçimindedir ve büyük/küçük harfe
        // DUYARLIDIR. Bu yolu katlamak, masadaki basılı kodu sessizce
        // çalışmaz hâle getirir — kod değişmez, ama artık menüye gitmez.
        $token = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdE';
        $result = $this->normalizer()->normalize('/q/'.$token);

        self::assertSame('/q/'.$token, $result->path, 'URL-NORM-OPAQUE-03: QR token harf katlamaya uğradı — her basılı kod ölür.');
        self::assertFalse($result->changed);
    }

    public function test_an_unknown_path_is_not_case_folded_by_default(): void
    {
        // Bilinmeyen bir yolu katlamak, opak kimlik taşıyıp taşımadığını
        // bilmeden onu bozma riskidir. Varsayılan güvenli taraftır.
        self::assertSame('/Something/New', $this->normalizer()->normalize('/Something/New')->path);
    }

    // --- URL-NORM-QUERY-04 -------------------------------------------------

    public function test_query_order_is_deterministic_so_one_page_has_one_cache_key(): void
    {
        $first = $this->normalizer()->normalize('/app', ['b' => '2', 'a' => '1'])->target();
        $second = $this->normalizer()->normalize('/app', ['a' => '1', 'b' => '2'])->target();

        self::assertSame($first, $second);
        self::assertSame('/app?a=1&b=2', $first);
    }

    public function test_tracking_parameters_do_not_trigger_a_redirect(): void
    {
        // Yönlendirmek, ölçüm yapılmadan izleme parametresini silmek olurdu.
        // Onlar canonical adresin dışında bırakılır, istekten atılmaz.
        $result = $this->normalizer()->normalize('/terms', ['utm_source' => 'instagram']);

        self::assertFalse($result->changed);
        self::assertStringContainsString('utm_source=instagram', $result->target());
    }

    // --- URL-NORM-DUPLICATE-05 ---------------------------------------------

    public function test_a_repeated_query_key_is_detected(): void
    {
        self::assertTrue($this->normalizer()->hasDuplicateQueryKeys('sort=a&sort=b'));
        self::assertFalse($this->normalizer()->hasDuplicateQueryKeys('sort=a&order=b'));
    }

    public function test_php_array_syntax_is_not_a_duplicate(): void
    {
        // `items[]=a&items[]=b` meşru bir çokluktur; onu reddetmek çalışan
        // formları kırardı.
        self::assertFalse($this->normalizer()->hasDuplicateQueryKeys('items[]=a&items[]=b'));
    }

    // --- URL-NORM-IDEMPOTENT-06 --------------------------------------------

    public function test_normalizing_twice_changes_nothing_the_second_time(): void
    {
        // Aksi hâlde yönlendirme kendine yönlenir ve tarayıcı döngüye girer.
        foreach (['/Terms/', '//app//menu/', '/q/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdE'] as $path) {
            $once = $this->normalizer()->normalize($path);
            $twice = $this->normalizer()->normalize($once->path);

            self::assertSame($once->path, $twice->path);
            self::assertFalse($twice->changed, "URL-NORM-IDEMPOTENT-06: {$path} ikinci geçişte hâlâ değişiyor — yönlendirme döngüsü.");
        }
    }
}
