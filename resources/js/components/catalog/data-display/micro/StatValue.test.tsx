import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatValue } from './StatValue';
import {
    WCAG_AA_NORMAL_TEXT,
    contrastRatio,
    readCustomProperties,
    resolveColor,
} from '../../../../design-system/contrast';
import { themeScope, readStyleLayers } from '../../../../design-system/cssSources';

/**
 * Kontrast, sabit bir Tailwind hex'ine değil, bileşenin GERÇEKTEN okuduğu
 * token'a karşı ölçülür. Eski hâli `text-green-700` sınıfının varlığını
 * arıyordu; o sınıf tasarım sisteminden kaçan bir ham renkti ve test onu
 * koruyordu.
 */
/*
    Jetonlar iki katmanda yaşıyor (FF-131): ham değerler `resources/css/aep/`,
    takma adlar `app.css`. Yalnız `app.css` okumak, `var(--aep-*)` metnini
    renk sanıp ölçümü sessizce boşa çıkarırdı.
*/
const LIGHT = themeScope(':root', readCustomProperties);
const DARK = themeScope('.dark', readCustomProperties);

function tokenContrast(scope: Record<string, string>, fg: string, bg: string): number {
    const foreground = resolveColor(scope[fg], scope);
    const background = resolveColor(scope[bg], scope);

    if (foreground === null || background === null) {
        throw new Error(`Token çözülemedi: ${fg} / ${bg}`);
    }

    return contrastRatio(foreground, background);
}

describe('StatValue', () => {
    it('renders the value', () => {
        render(<StatValue value="1,204" />);
        expect(screen.getByText('1,204')).toBeInTheDocument();
    });

    it('does not render a trend indicator when trend is omitted', () => {
        render(<StatValue value="1,204" />);
        expect(screen.queryByText('(trending up)')).not.toBeInTheDocument();
    });

    it.each([
        ['up', '(trending up)'],
        ['down', '(trending down)'],
        ['flat', '(flat)'],
    ] as const)('announces the %s trend to assistive tech', (trend, label) => {
        render(<StatValue value="1,204" trend={trend} />);
        expect(screen.getByText(label)).toBeInTheDocument();
    });

    /*
        RAKAM METRİK ÖLÇEKTE (FF-131, AEP `DESIGN_SPEC` §2 "Sayı kartları").

        Teslim paketinin çalışan Home ekranında sayı kartındaki rakam
        34 piksel, 700 ağırlıkta ve `tabular-nums`. Depodaki karşılığı
        `text-title` taşıyordu: aynı ölçek SAYFA BAŞLIĞININ ölçeğidir.
        Yani bir sayının, bir başlığın ve bir kart başlığının hepsi
        birbirine yakın büyüklükteydi ve gözün "burada bir ÖLÇÜM var"
        diyebileceği tek bir işaret kalmıyordu.

        AEP bu iş için ayrı bir basamak yayınlıyor: `--aep-text-metric`.
        Test onu ADIYLA donduruyor, ham piksel değeriyle değil — ölçek
        yarın değişirse bu test değil, jetonun kendisi değişir.

        `tabular-nums` ayrı bir şart: oransal rakamlarda "1" diğer
        rakamlardan dar olduğu için alt alta duran iki kartın sayıları
        farklı genişlikte görünür ve sütun kayar.
    */
    it('rakamı AEP metrik ölçeğinde ve tabular rakamlarla çizer', () => {
        render(<StatValue value="1,204" />);
        const valueNode = screen.getByText('1,204');

        expect(valueNode.style.fontSize).toBe('var(--aep-text-metric)');
        expect(valueNode.style.lineHeight).toBe('var(--aep-text-metric-lh)');
        expect(valueNode.className).toContain('tabular-nums');

        /*
            Jeton GERÇEKTEN var mı? `var()` çözülemezse tarayıcı hata
            vermez, öğe ebeveyninin boyutunu sessizce miras alır — yani
            yukarıdaki üç satır "geçti" der ve ekranda hiçbir şey değişmez.
        */
        const styles = readStyleLayers();
        expect(styles).toContain('--aep-text-metric:');
        expect(styles).toContain('--aep-text-metric-lh:');
    });

    /*
        AĞIRLIK 700. AEP yalnız üç ağırlık yayınlıyor (400/500/700) ve
        `font-semibold` (600) o üçünün dışında: seçilen ağırlık yazı
        tipinde yoksa tarayıcı onu SENTEZLER ve rakamlar kalınlaşırken
        biçimleri hafifçe bozulur.
    */
    it('rakamı AEP ağırlık merdiveninin dışına çıkmadan kalınlaştırır', () => {
        render(<StatValue value="1,204" />);
        const valueNode = screen.getByText('1,204');

        expect(valueNode.className).toContain('font-bold');
        expect(valueNode.className).not.toContain('font-semibold');
    });

    it('renders the "up" trend at WCAG 2.2 AA text contrast in both themes (1.4.3)', () => {
        render(<StatValue value="1,204" trend="up" />);
        const trendNode = screen.getByText('(trending up)').parentElement;
        expect(trendNode).not.toBeNull();

        expect(trendNode?.className).toContain('text-fg-success');
        expect(trendNode?.className).not.toMatch(/text-green-\d/);

        expect(tokenContrast(LIGHT, '--fg-success', '--surface')).toBeGreaterThanOrEqual(
            WCAG_AA_NORMAL_TEXT,
        );
        expect(tokenContrast(DARK, '--fg-success', '--surface')).toBeGreaterThanOrEqual(
            WCAG_AA_NORMAL_TEXT,
        );
    });
});
