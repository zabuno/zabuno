import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GlobalCreateMenu, type GlobalCreateTarget } from './GlobalCreateMenu';

/**
 * Global oluştur menüsü — `docs/64`.
 */
const TARGETS: GlobalCreateTarget[] = [
    {
        key: 'location',
        labelKey: 'workspace.create.location',
        destination: 'locations/new',
        available: true,
    },
    { key: 'menu', labelKey: 'workspace.create.menu', destination: 'menu', available: false },
];

describe('global oluştur menüsü', () => {
    /**
     * Ön koşulu sağlanmayan hedef LİSTELENMEZ. Şubesiz bir çalışma alanında
     * "Menü" maddesi kullanıcıyı çıkışsız bir ekrana götürürdü.
     */
    it('yalnız ön koşulu sağlanan hedefleri gösterir', async () => {
        const user = userEvent.setup();
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={vi.fn()} />);

        await user.click(screen.getByRole('button', { name: 'Create' }));

        expect(await screen.findByRole('menuitem', { name: 'Location' })).toBeInTheDocument();
        expect(screen.queryByRole('menuitem', { name: 'Menu' })).toBeNull();
    });

    it('seçilen hedefe götürür', async () => {
        const user = userEvent.setup();
        const onNavigate = vi.fn();
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={onNavigate} />);

        await user.click(screen.getByRole('button', { name: 'Create' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Location' }));

        // Bölüm İÇİ yol: liste değil, formun açık olduğu adres.
        expect(onNavigate).toHaveBeenCalledWith('locations/new');
    });

    /**
     * Boş bir "Oluştur" düğmesi, tıklandığında hiçbir şey sunmayan bir
     * vaattir. Hiçbir hedef uygun değilse menü hiç çizilmez.
     */
    it('uygun hedef yoksa hiç çizilmez', () => {
        const { container } = render(
            <GlobalCreateMenu
                targets={TARGETS.map((target) => ({ ...target, available: false }))}
                onNavigate={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
