<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

enum QrTheme: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Bold = 'bold';
    case Rounded = 'rounded';
    case Branded = 'branded';
    case HighContrast = 'highContrast';

    public function foregroundRgb(): string
    {
        return match ($this) {
            self::Classic => '000000',
            self::Minimal => '1F2937',
            self::Bold => '111827',
            self::Rounded => '064E3B',
            self::Branded => '1E3A8A',
            self::HighContrast => '000000',
        };
    }

    public function backgroundRgb(): string
    {
        return match ($this) {
            self::Classic => 'FFFFFF',
            self::Minimal => 'F9FAFB',
            self::Bold => 'FDE68A',
            self::Rounded => 'D1FAE5',
            /*
                MARKALI TEMA BEYAZ ZEMİNDEDİR (FF-112).

                Eskiden açık mavi bir zemini vardı ve ön plan sabit bir
                laciverttti — yani "markalı" adı taşıyan tema, kiracının
                markasıyla hiç ilgilenmiyordu. Artık ön plan markanın gerçek
                rengi; zemin beyaz kalır çünkü kart zaten beyaz kâğıda basılır
                ve renkli bir zemin hem mürekkep yakar hem de marka rengiyle
                arasındaki kontrastı düşürür.
            */
            self::Branded => 'FFFFFF',
            self::HighContrast => 'FFFF00',
        };
    }
}
