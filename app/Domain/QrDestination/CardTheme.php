<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

/**
 * Basılacak kartın TASARIMI — FF-120, sahibin talebi (2026-09-04).
 *
 * "Themes" bu ekranda yanlış şeyi adlandırıyordu: karekodun piksel renklerini.
 * Sahibin sorduğu şey başkaydı — masadaki pleksiglas kartın nasıl görüneceği.
 * Karekodun rengi bir tema değil bir KISITTIR (`QrContrast`): koyu modül, açık
 * zemin, oran ≥ 4:1. Tema ise kartın kendisidir ve her restoranın marka
 * kimliği ayrı olduğu için markadan beslenir.
 *
 * Tasarımların hepsi MARKA RENGİNİ kullanır. Renk, karekodun kendisine değil
 * kartın çerçevesine/şeridine/zeminine uygulanır: kod her zaman siyah beyaz
 * basılır, çünkü taranabilirlik pazarlık konusu değildir ve masadaki okunmayan
 * bir kart, hiç kart olmamasından kötüdür.
 *
 * KOYU VE TABELA — panel v3.1 kanonik kaynağı (`docs/reference/panel-v3/
 * panel-v3.1.dc.html`, QR bölümü), sahibin 2026-09-05 kuralı: tasarımı sahip
 * veriyorsa eski belgelere bağımlı kalınmaz.
 *
 * Bu iki tasarım bir kez REDDEDİLMİŞTİ ve reddin gerekçesi doğruydu: eski
 * kaynağın "Koyu"su karekodu BEYAZ MODÜL / SİYAH ZEMİN çiziyordu; tarayıcılar
 * koyu-üstüne-açık varsayar (ISO/IEC 18004) ve ters basılan bir kod birçok
 * telefonda hiç okunmaz. Yeni kaynak o kusuru kendi düzeltiyor — `koyu` ve
 * `tabela` temalarının kod çifti hâlâ `codeBg: #FFFFFF, codeFg: #080616`,
 * yani KOYU MODÜL / AÇIK ZEMİN. Koyulaşan şey kartın zemini, kodun kendisi
 * değil. Reddin sebebi ortadan kalktığı için tasarımlar doğdu; kod her iki
 * temada da beyaz bir plakanın üstünde basılır (`QrCardSvg`).
 */
enum CardTheme: string
{
    /** Beyaz kart, üstte restoran adı, altta çağrı. En az mürekkep. */
    case Classic = 'classic';

    /** Yalnız kod ve tek satır. Adı zaten pleksiglasın üstünde yazan işletme için. */
    case Minimal = 'minimal';

    /** Marka renginde geniş bir başlık şeridi. Uzaktan görünür. */
    case Banner = 'banner';

    /** Marka renginde ince çerçeve. Kesildiğinde kenarı belli olur. */
    case Frame = 'frame';

    /** Koyu kart, beyaz yazı. Kod beyaz plakanın üstünde kalır. */
    case Dark = 'dark';

    /** Zemin markanın kendi rengi. Uzaktan bakıldığında bir tabela gibi okunur. */
    case Signage = 'signage';

    /**
     * Kartta restoran adı yazılır mı?
     *
     * `Minimal` yazmaz ve bu bir eksiklik değil bir karardır: adı zaten
     * standın üstünde ya da masada yazan işletme için ikinci kez yazmak
     * kartı kalabalıklaştırır.
     */
    public function showsBrandName(): bool
    {
        return $this !== self::Minimal;
    }

    /** Marka rengi hangi öğede görünür. */
    public function accentRole(): string
    {
        return match ($this) {
            self::Classic => 'rule',
            self::Minimal => 'none',
            self::Banner => 'banner',
            self::Frame => 'frame',
            // Koyu ve Tabela'da vurgu ZEMİNİN kendisidir; kartın üstüne
            // ikinci bir şerit ya da çerçeve çizmek aynı rengi iki kez
            // söylemek olurdu.
            self::Dark, self::Signage => 'ground',
        };
    }

    /**
     * Kartın zemini koyu mu?
     *
     * Bu bir renk zevki değil bir BASKI kısıtıdır: koyu zeminde karekodun
     * altına beyaz bir plaka konmazsa kodun sessiz bölgesi (ISO/IEC 18004: 4
     * modül) koyu kalır ve kod birçok telefonda hiç okunmaz. Yazının rengi de
     * buradan türer — koyu zeminde `#333333` bir başlık okunmaz.
     */
    public function hasDarkGround(): bool
    {
        return $this === self::Dark || $this === self::Signage;
    }
}
