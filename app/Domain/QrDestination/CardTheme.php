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
 * Dört tasarım var ve dördü de MARKA RENGİNİ kullanır. Renk, karekodun
 * kendisine değil kartın çerçevesine/başlığına uygulanır: kod her zaman siyah
 * beyaz basılır, çünkü taranabilirlik pazarlık konusu değildir ve masadaki
 * okunmayan bir kart, hiç kart olmamasından kötüdür.
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
        };
    }
}
