<?php

declare(strict_types=1);

namespace App\Domain\Branding;

/**
 * SKIN'İN BİÇİM EKSENİ — kiracı burada bir DEĞER değil, bir SEÇENEK seçer.
 *
 * Karşılığı uydurulmadı: `resources/css/aep/tokens/variants.css` altı biçim
 * varyantını (yarıçap, kenarlık, gölge, şerit, başlık ağırlığı) zaten
 * tanımlıyor ve kendi ilk satırında *"the ONLY place the 12 micro-axes
 * resolve"* diyor. Bugüne kadar hiçbir üretim kodu bu özniteliği
 * tüketmiyordu (`docs/113` §5.3).
 *
 * Neden seçenek, neden değer değil: altı varyantın altısı da platform
 * tarafından bir kez ölçülür. Kiracıya serbest bir yarıçap/gölge alanı
 * açmak, ölçülmemiş bir kombinasyonu misafirin ekranına koymak olurdu ve o
 * ekran restoranın değil, ürünün itibarıdır.
 *
 * Liste CSS'ten AYRILAMAZ: `SkinVariantMatchesTokenLayerTest` iki tarafı
 * karşılaştırır. Ayrılırsa kiracı, tarayıcıda hiçbir şey yapmayan bir
 * varyant seçer.
 */
enum SkinVariant: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';
    case E = 'e';
    case F = 'f';

    /**
     * Seçenek listesinin varsayılanı: bugünkü görünümü değiştirmeyen
     * varyant. Seçmeyen restoran, seçmiş gibi gösterilmez.
     */
    public static function default(): self
    {
        return self::A;
    }

    /** Tanınmayan bir anahtar `null` döner; bilinmeyen asla biçim seçmez. */
    public static function tryFromKey(?string $key): ?self
    {
        return $key === null ? null : self::tryFrom(strtolower(trim($key)));
    }

    /**
     * Sahibe gösterilecek ad. Mühendislik değil, GÖRÜNÜM anlatır: sahip
     * "b varyantı" değil, ekranda ne göreceğini seçer.
     */
    public function label(): string
    {
        return match ($this) {
            self::A => 'Sade çerçeve',
            self::B => 'Yumuşak köşe',
            self::C => 'İnce çizgi',
            self::D => 'Belirgin kart',
            self::E => 'Çerçevesiz',
            self::F => 'Yuvarlak ve yükseltilmiş',
        };
    }
}
