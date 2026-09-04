import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatValue } from './StatValue';
import {
    WCAG_AA_NORMAL_TEXT,
    contrastRatio,
    readCustomProperties,
    resolveColor,
} from '../../../../design-system/contrast';
import { themeScope } from '../../../../design-system/cssSources';

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
