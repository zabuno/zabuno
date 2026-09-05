import { describe, expect, it } from 'vitest';

import {
    CARD_GEOMETRY_MM,
    cardCodeSideMm,
    type CardGeometrySizeKey,
    type CardGeometryThemeKey,
} from './qrCardGeometry';
import { CARD_SIZE_MM } from '../components/workspace/pages/publication/QrCardWizard';

/**
 * SUNUCU İLE İSTEMCİ AYNI KARTI ÖLÇER.
 *
 * Panel v3.1 kanonik kaynağının önizleme paneli "Kod 88 mm — masa mesafesinden
 * rahat okunur" diyor. Bu cümleyi ekran yazıyor ama kartı sunucu basıyor: iki
 * taraf ayrışırsa ekran, basılmayan bir ölçüyü yazar ve sahip kırk kartı o
 * cümleye güvenerek bastırır.
 *
 * Aşağıdaki tablo `tests/Unit/QrDestination/QrCardSvgTest.php` içindeki
 * `codeSizes` sağlayıcısının AYNISIDIR. Biri değişirse diğerinin testi kırılır
 * — ayrışma sessiz kalamaz.
 */
const PINNED: Array<[CardGeometryThemeKey, CardGeometrySizeKey, boolean, boolean, number]> = [
    ['classic', 'A6', false, false, 88.2],
    ['classic', 'A6', false, true, 88.2],
    ['classic', 'A6', true, false, 65.186],
    ['classic', 'A6', true, true, 58.091],
    ['minimal', 'A6', false, false, 88.2],
    ['banner', 'A6', false, false, 88.2],
    ['dark', '1:2', false, false, 63.0],
    ['signage', '16:9', false, true, 46.708],
    ['classic', 'A3', false, false, 249.48],
];

describe('qrCardGeometry — sunucunun bastığı ölçünün aynası', () => {
    it.each(PINNED)(
        '%s · %s · yatay=%s · masa adı=%s → %s mm',
        (theme, size, landscape, printsTableName, expected) => {
            expect(cardCodeSideMm(theme, size, landscape, printsTableName)).toBeCloseTo(
                expected,
                2,
            );
        },
    );

    it('mm tablosu kart sihirbazının tablosuyla birebir aynıdır', () => {
        /*
            Tablo iki yerde duruyor çünkü `lib/` katmanı bir bileşene
            bağlanamaz. İki kopya ayrışırsa ekranın bir yerinde "105 × 148 mm",
            başka bir yerinde başka bir ölçü yazar ve sahip hangisinin doğru
            olduğunu ancak yazıcıdan kâğıt çıkınca öğrenir.
        */
        for (const [key, value] of Object.entries(CARD_GEOMETRY_MM)) {
            expect(CARD_SIZE_MM[key as keyof typeof CARD_SIZE_MM]).toEqual([...value]);
        }

        expect(Object.keys(CARD_GEOMETRY_MM).sort()).toEqual(Object.keys(CARD_SIZE_MM).sort());
    });

    it('masa adı satırı dikey alanı yer: dar kartta kod küçülür', () => {
        // Kod küçülmeseydi masa adı kodun üstüne binerdi ve bunu ancak
        // yazıcıdan çıkan kart gösterirdi.
        expect(cardCodeSideMm('classic', 'A6', true, true)).toBeLessThan(
            cardCodeSideMm('classic', 'A6', true, false),
        );
    });
});
