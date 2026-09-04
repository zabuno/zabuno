/**
 * Karekodun taranabilirlik kısıtı — `docs/104` Döngü 10.
 *
 * SUNUCU KARAR VERİCİDİR: dışa aktarımda temayı `App\Domain\QrDestination\
 * QrContrast` çözer ve marka rengi taranabilir değilse klasiğe düşer. Buradaki
 * kopya yalnız ekranın ÖNCEDEN konuşabilmesi içindir — sahip "markalı"yı
 * seçtiğinde neyin olacağını, indirme düğmesine basmadan önce öğrenir.
 *
 * İki kural pazarlıksızdır ve ikisi de araştırmadan gelir:
 *   1. Koyu modül, AÇIK zemin üstünde. Ters kontrast birçok tarayıcıda hiç
 *      okunmaz — "bazı telefonlarda çalışır" bir destek talebidir.
 *   2. Kontrast oranı ≥ 4:1. Düşük ışıkta, buğulu bir kamerayla ve yıpranmış
 *      kâğıtla da okunmalı.
 */
export const QR_MIN_CONTRAST_RATIO = 4;

function relativeLuminance(rgb: string): number {
    const hex = rgb.replace(/^#/, '');

    if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
        // Okunamayan bir renk SİYAH sayılır: bilinmeyeni "muhtemelen açıktır"
        // saymak, taranamayan bir kod basmanın yoludur.
        return 0;
    }

    const channels = [
        Number.parseInt(hex.slice(0, 2), 16) / 255,
        Number.parseInt(hex.slice(2, 4), 16) / 255,
        Number.parseInt(hex.slice(4, 6), 16) / 255,
    ].map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

export function qrContrastRatio(foreground: string, background: string): number {
    const first = relativeLuminance(foreground);
    const second = relativeLuminance(background);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

/** Bu renk çifti basılabilir mi? */
export function isQrScannable(foreground: string, background: string): boolean {
    if (relativeLuminance(foreground) >= relativeLuminance(background)) {
        return false;
    }

    return qrContrastRatio(foreground, background) >= QR_MIN_CONTRAST_RATIO;
}

/** Markanın rengi karekodda kullanılabilir mi (beyaz zemin üstünde)? */
export function isBrandColorPrintable(brandPrimaryColor: string | null): boolean {
    return brandPrimaryColor !== null && isQrScannable(brandPrimaryColor, 'FFFFFF');
}
