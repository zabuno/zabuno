import { describe, expect, it, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { DENSITY_STORAGE_KEY, useDensityControl } from './densityControl';
import { ThemeRoot } from './ThemeRoot';

/**
 * YOĞUNLUK KÖKE YAZILIR — FF-128.
 *
 * `data-density` daha önce de yazılıyordu ama değeri sabitti; yani öznitelik
 * vardı, TERCİH yoktu. Bu test tercihin gerçekten uçtan uca çalıştığını
 * dondurur: seçim kök öğeye yazılır, tarayıcıda saklanır ve sonraki açılışta
 * geri okunur.
 */
function Probe() {
    const density = useDensityControl();

    if (density === null) return <p>yok</p>;

    return (
        <button type="button" onClick={() => density.choose('compact')}>
            {density.preference}
        </button>
    );
}

describe('DensityRoot', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.documentElement.removeAttribute('data-density');
    });

    it('varsayılanı köke yazar', () => {
        render(
            <ThemeRoot>
                <Probe />
            </ThemeRoot>,
        );

        expect(document.documentElement.getAttribute('data-density')).toBe('standard');
        expect(screen.getByRole('button')).toHaveTextContent('standard');
    });

    it('seçim köke yazılır ve tarayıcıda saklanır', async () => {
        const user = userEvent.setup();

        render(
            <ThemeRoot>
                <Probe />
            </ThemeRoot>,
        );

        await user.click(screen.getByRole('button'));

        expect(document.documentElement.getAttribute('data-density')).toBe('compact');
        expect(window.localStorage.getItem(DENSITY_STORAGE_KEY)).toBe('compact');
    });

    it('saklanan tercih sonraki açılışta geri okunur', () => {
        window.localStorage.setItem(DENSITY_STORAGE_KEY, 'comfortable');

        render(
            <ThemeRoot>
                <Probe />
            </ThemeRoot>,
        );

        expect(document.documentElement.getAttribute('data-density')).toBe('comfortable');
    });

    it('bozuk bir saklanan değer varsayılana düşer — ölçek dışına çıkılmaz', () => {
        window.localStorage.setItem(DENSITY_STORAGE_KEY, 'tiny');

        render(
            <ThemeRoot>
                <Probe />
            </ThemeRoot>,
        );

        expect(document.documentElement.getAttribute('data-density')).toBe('standard');
    });
});
