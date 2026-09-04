import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';
import { AccountMenu } from '../workspace/chrome/AccountMenu';
import { AppearanceRegion } from '../workspace/pages/profile/AppearanceRegion';

/**
 * Görünüm tercihinin semantiği — `docs/63`, FF-119.
 *
 * Bu dosya `ThemeMenuSemantics.test.tsx`'in yerini aldı. Eski hâli kontrolün
 * HESAP MENÜSÜNÜN İÇİNDE olduğunu donduruyordu: `menuitemradio` rolleri, menü
 * içinde radiogroup bulunmaması, menünün ayarı eylemlerden ayrı bir başlıkta
 * toplaması.
 *
 * Sahip 2026-09-04'te kontrolün menüden çıkmasını istedi ("theme options,
 * ayarlara taşınsın, dropdown içerisinde kalmasın") ve karar doğru: tema bir
 * AYARDIR, bir eylem değil. Menü eylemler içindir. Bir ayarın değerini menünün
 * içinde tutmak, aynı ayarın iki evi olması demekti.
 *
 * Sözleşme değişmedi, evi değişti: üç seçenek, tek seçim, seçimin renkten
 * başka bir kanalda da görünmesi, klavyeyle etkinleştirilebilirlik ve
 * kalıcılık. Silinmiş bir deseni test etmeye devam etmek testleri yeşil
 * tutmanın en kolay yoluydu ama hiçbir şey ölçmezdi.
 */
const STORAGE_KEY = 'zabuno-theme';

function installMatchMediaMock(prefersDark: boolean) {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: (query: string) => ({
            matches: prefersDark,
            media: query,
            onchange: null,
            addEventListener: () => {},
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
            dispatchEvent: () => false,
        }),
    });
}

function resetDocumentThemeState() {
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
}

function renderAppearance() {
    const user = userEvent.setup();

    render(
        <ThemeRoot>
            <AppearanceRegion />
        </ThemeRoot>,
    );

    return { user, group: screen.getByRole('group', { name: /theme|görünüm/i }) };
}

describe('görünüm tercihi — ayar semantiği', () => {
    beforeEach(() => {
        resetDocumentThemeState();
        installMatchMediaMock(false);
    });

    afterEach(resetDocumentThemeState);

    /**
     * Üç seçenek üç ayrı düğme olsaydı ekran okuyucu "üç ayrı eylem" derdi.
     * Oysa bunlar tek bir ayarın birbirini dışlayan değerleridir ve hangisinin
     * açık olduğu DUYULMALIDIR.
     */
    it('üç seçeneği tek bir grubun radyoları olarak sunar ve yalnız biri seçilidir', () => {
        const { group } = renderAppearance();

        const options = within(group).getAllByRole('radio');
        expect(options).toHaveLength(3);

        const checked = options.filter((option) => (option as HTMLInputElement).checked);
        expect(checked).toHaveLength(1);
        expect(checked[0]).toHaveAccessibleName(/system/i);
    });

    it('seçim renkten BAŞKA bir kanalda da görünür', () => {
        const { group } = renderAppearance();

        const options = within(group).getAllByRole('radio');
        const checked = options.find((option) => (option as HTMLInputElement).checked)!;
        const unchecked = options.find((option) => !(option as HTMLInputElement).checked)!;

        // Yüksek kontrast kipinde arka plan/metin çiftleri işletim sistemi
        // paletine düşer; renge dayanan her ayrım orada kaybolur (WCAG 1.4.1).
        expect(checked.closest('label')?.querySelector('svg')).not.toBeNull();
        expect(unchecked.closest('label')?.querySelector('svg')).toBeNull();
    });

    it('seçenek etkinleştirilince tercih uygulanır ve kalıcı olur', async () => {
        const { user, group } = renderAppearance();

        const dark = within(group).getByRole('radio', { name: /dark/i });
        expect(dark.tagName).toBe('INPUT');
        expect(dark).not.toBeDisabled();

        await user.click(dark);

        await waitFor(() => {
            expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
        });
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('hesap menüsünde artık bir tema kontrolü YOKTUR', async () => {
        /*
            Sözleşmenin yeni yarısı. Kontrolü ayarlara taşıyıp menüdekini
            bırakmak, aynı ayarın iki değeri varmış gibi görünmesine yol
            açardı — sahibin bildirimi tam olarak buydu.
        */
        const user = userEvent.setup();

        render(
            <ThemeRoot>
                <AccountMenu email="ada@example.com" onLogout={() => {}} />
            </ThemeRoot>,
        );

        await user.click(screen.getByRole('button', { name: /account/i }));
        const menu = await screen.findByRole('menu');

        expect(within(menu).queryByRole('menuitemradio')).toBeNull();
        expect(within(menu).queryByRole('radio')).toBeNull();
        expect(menu.textContent ?? '').not.toMatch(/system|light|dark/i);
    });
});
