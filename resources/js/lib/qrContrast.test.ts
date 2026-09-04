import { describe, expect, it } from 'vitest';

import { isBrandColorPrintable, isQrScannable, QR_MIN_CONTRAST_RATIO } from './qrContrast';

/**
 * QRTHEME-SCANNABLE-01 (istemci yarısı) — FF-112, `docs/104` Döngü 10.
 *
 * Sunucu karar vericidir (`App\Domain\QrDestination\QrContrast`); buradaki
 * kopya yalnız ekranın ÖNCEDEN konuşabilmesi için var. Eşiklerin ikisinde de
 * aynı olması bir sözleşmedir: ayrışırlarsa ekran "markalı basılacak" der,
 * yazıcıdan siyah çıkar.
 */
describe('qrContrast', () => {
    it('siyah/beyazı kabul, ters kontrastı REDDEDER', () => {
        expect(isQrScannable('000000', 'FFFFFF')).toBe(true);
        // Oran yine 21:1 ama tarayıcıların çoğu koyu-üstüne-açık varsayar.
        expect(isQrScannable('FFFFFF', '000000')).toBe(false);
    });

    it('göze güzel ama kameraya görünmez bir marka rengini reddeder', () => {
        expect(isBrandColorPrintable('#FFE066')).toBe(false);
        expect(isBrandColorPrintable('#1B4332')).toBe(true);
    });

    it('marka rengi yoksa uydurmaz', () => {
        expect(isBrandColorPrintable(null)).toBe(false);
    });

    it('okunamayan bir renk değerini SİYAH sayar', () => {
        // Bilinmeyeni "muhtemelen açıktır" saymak, taranamayan bir kod
        // basmanın yoludur.
        expect(isQrScannable('not-a-color', 'FFFFFF')).toBe(true);
    });

    it('eşik sunucudaki `QrContrast::MIN_RATIO` ile aynıdır', () => {
        expect(QR_MIN_CONTRAST_RATIO).toBe(4);
    });
});
