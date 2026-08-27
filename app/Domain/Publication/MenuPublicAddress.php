<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use InvalidArgumentException;

/**
 * Yayınlanan bir menünün herkese açık adresi.
 *
 * İki parçadan oluşur ve ikisinin rolü farklıdır:
 *
 * - `key` **kimliktir**. Değişmez. İşletme adını değiştirse, şubesini
 *   taşısa, menüsünü baştan yazsa bile aynı kalır.
 * - `slug` **okunabilirliktir**. Değişebilir. Yanlış veya eski bir slug ile
 *   gelen istek, doğru adrese kalıcı olarak yönlendirilir — yani bağlantı
 *   ölmez, kendini onarır.
 *
 * Bu ayrım olmasaydı, bir restoranın adını değiştirmesi paylaşılmış her
 * bağlantıyı ve her dış linki kırardı.
 */
final class MenuPublicAddress
{
    private const KEY_PATTERN = '/^[a-z0-9]{10}$/';

    private function __construct(
        public readonly string $key,
        public readonly string $slug,
    ) {}

    /** Kimlik ve hazır slug'dan kurar (depodan okunan hâl). */
    public static function fromKeyAndSlug(string $key, string $slug): self
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Menu public key must be 10 lowercase alphanumeric characters.');
        }

        return new self($key, $slug);
    }

    public static function create(string $key, string $displayName): self
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Menu public key must be 10 lowercase alphanumeric characters.');
        }

        return new self($key, self::slugFor($displayName));
    }

    /** Yeni bir kimlik üretir. Sıralı değildir: sıralı kimlik işletme sayısını ilan eder. */
    public static function generateKey(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $key = '';

        for ($index = 0; $index < 10; $index++) {
            $key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $key;
    }

    public static function isKey(string $value): bool
    {
        return preg_match(self::KEY_PATTERN, $value) === 1;
    }

    /**
     * Görünen addan okunabilir bir slug üretir.
     *
     * Türkçe harfler çevrilir: "Çiğköfteci Ömer" → "cigkofteci-omer".
     * Ad boşsa slug da boş kalır ve adres yalnız kimlikten oluşur —
     * uydurulmuş bir slug, yanlış bir slug'dan iyi değildir.
     *
     * Çeviri burada elle yapılır çünkü ALAN KATMANI çerçeveye bağlanamaz
     * (`OnionBoundaryTest`). Kural aynıdır ve tek yerde durur.
     */
    public static function slugFor(string $displayName): string
    {
        $transliterated = strtr(mb_strtolower(trim($displayName), 'UTF-8'), [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);

        $slug = preg_replace('/[^a-z0-9]+/u', '-', $transliterated) ?? '';
        $slug = trim($slug, '-');

        return substr($slug, 0, 60);
    }

    public function path(): string
    {
        return $this->slug === ''
            ? '/menu/'.$this->key
            : '/menu/'.$this->key.'/'.$this->slug;
    }
}
