<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use InvalidArgumentException;

/**
 * Yayınlanan bir menünün herkese açık adresi.
 *
 *     /restoran/pasa-doner/menu/ab12cd34ef
 *     /restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap
 *
 * Parçaların rolleri farklıdır:
 *
 * - `type` segmenti **ne olduğunu söyler** ve kiracıya kendi kökünü verir
 *   (`docs/105` §4.2). Dile göre yazılır: `restoran` / `restaurant`.
 * - `slug` **okunabilirliktir**. Değişebilir. Yanlış veya eski bir slug ile
 *   gelen istek doğru adrese kalıcı olarak yönlendirilir — bağlantı ölmez,
 *   kendini onarır.
 * - `key` **kimliktir**. Değişmez. İşletme adını değiştirse, şubesini taşısa,
 *   menüsünü baştan yazsa bile aynı kalır.
 *
 * Bu ayrım olmasaydı, bir restoranın adını değiştirmesi paylaşılmış her
 * bağlantıyı ve her dış linki kırardı.
 *
 * SIRA 2026-09-04'te DEĞİŞTİ (FF-116, sahibin talebi). Önceki hâl
 * `/menu/ab12cd34ef/pasa-doner` idi: en anlamlı parça (işletme adı) en sonda,
 * en anlamsız parça (10 karakterlik anahtar) ortadaydı. Kartvizite yazıldığında
 * ya da telefonda söylendiğinde önce anlamsız kısım geliyordu.
 */
final class MenuPublicAddress
{
    private const KEY_PATTERN = '/^[a-z0-9]{10}$/';

    private function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly BusinessType $type,
        /** Segmentlerin yazıldığı dil: işletmenin KENDİ dili. */
        public readonly string $locale,
    ) {}

    /** Kimlik ve hazır slug'dan kurar (depodan okunan hâl). */
    public static function fromKeyAndSlug(
        string $key,
        string $slug,
        string $locale = 'tr',
        BusinessType $type = BusinessType::Restaurant,
    ): self {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Menu public key must be 10 lowercase alphanumeric characters.');
        }

        return new self($key, $slug, $type, $locale);
    }

    public static function create(
        string $key,
        string $displayName,
        string $locale = 'tr',
        BusinessType $type = BusinessType::Restaurant,
    ): self {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Menu public key must be 10 lowercase alphanumeric characters.');
        }

        return new self($key, self::slugFor($displayName), $type, $locale);
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
        $prefix = '/'.$this->type->segment($this->locale);

        return $this->slug === ''
            ? $prefix.'/menu/'.$this->key
            : $prefix.'/'.$this->slug.'/menu/'.$this->key;
    }

    /**
     * Tek bir ürünün adresi.
     *
     * Sahibin ilk örneği `#item=101` idi. Fragment sunucuya HİÇ ulaşmaz:
     * indekslenmez, ayrı bir görüntüleme olarak ölçülemez ve paylaşılan
     * bağlantıda hangi ürün olduğu sunucu tarafından bilinemez. Yol segmenti
     * üçünü de yapar (`docs/105` §4.3).
     *
     * Kimlik BAŞTADIR: adı okunamayan bir ürün (yalnız emoji, yalnız Çince)
     * bile çalışan bir adrese sahip olur ve slug boşsa adres kısalır.
     */
    public function itemPath(int $menuItemId, string $productName): string
    {
        $slug = self::slugFor($productName);
        $segment = $slug === '' ? (string) $menuItemId : $menuItemId.'-'.$slug;

        return $this->path().'/'.$this->type->itemSegment($this->locale).'/'.$segment;
    }
}
