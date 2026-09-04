<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Klasör ağacının derinlik kuralı — kaynağın kendi verisinden okunmuştur.
 *
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html` içindeki
 * `folderDefs` listesinde yalnız `depth: 0` ve `depth: 1` vardır
 * ("Ürünler" → "Tatlılar"). Kütüphane süzgeci de doğrudan eşleşmeye bakar,
 * alt klasörün dosyalarını üste toplamaz. İki seviye bu yüzden bir kısıt
 * değil, kaynağın anlattığı düzenin kendisidir.
 *
 * Kural burada, tek bir yerde durur: göç, port ve denetleyici aynı sayıyı
 * tekrar tekrar yazsaydı, ileride derinliği artırma kararı üç ayrı yerde
 * değiştirilmesi gereken bir sayıya dönüşürdü.
 */
final class FolderNesting
{
    /** Kök (1) + tek alt seviye (2). Üçüncü seviye reddedilir. */
    public const MAX_DEPTH = 2;

    /** Klasör adı için üst sınır — kenar çubuğu tek satırda okunabilir kalmalı. */
    public const MAX_NAME_LENGTH = 120;
}
