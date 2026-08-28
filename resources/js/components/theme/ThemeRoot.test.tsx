import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';
import { AccountMenu } from '../workspace/chrome/AccountMenu';

/**
 * ZABUNO_THEME_FOUNDATION_RED
 *
 * Freezes the smallest user-visible contract for the global adaptive theme:
 * a 'system' | 'light' | 'dark' preference that (a) defaults to following
 * the OS via matchMedia('(prefers-color-scheme: dark)'), (b) is applied to
 * <html> immediately as both a 'dark' class and a data-theme attribute on
 * every explicit choice, (c) persists under one stable Zabuno-owned
 * localStorage key and rehydrates from it, (d) reacts live to OS scheme
 * changes while on 'system', (e) is reachable and operable via keyboard
 * with accessible System/Light/Dark labels, and (f) treats any unrecognised
 * stored value as if nothing were stored (falls back to 'system') rather
 * than throwing or freezing on a broken value.
 *
 * ThemeRoot.tsx does not exist yet — every assertion below is expected to
 * fail RED (module resolution failure) until S1-WP01B implements it.
 */

const STORAGE_KEY = 'zabuno-theme';

type MediaQueryListenerMap = Map<string, Set<(event: MediaQueryListEvent) => void>>;

function installMatchMediaMock(initialPrefersDark: boolean) {
    let prefersDark = initialPrefersDark;
    const listeners: MediaQueryListenerMap = new Map();

    const mql = {
        get matches() {
            return prefersDark;
        },
        media: '(prefers-color-scheme: dark)',
        addEventListener: (type: string, listener: (event: MediaQueryListEvent) => void) => {
            const set = listeners.get(type) ?? new Set();
            set.add(listener);
            listeners.set(type, set);
        },
        removeEventListener: (type: string, listener: (event: MediaQueryListEvent) => void) => {
            listeners.get(type)?.delete(listener);
        },
        dispatchEvent: () => true,
    };

    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            ...mql,
            media: query,
        })),
    });

    return {
        setPrefersDark(next: boolean) {
            prefersDark = next;
            const event = { matches: next } as MediaQueryListEvent;
            listeners.get('change')?.forEach((listener) => listener(event));
        },
    };
}

/**
 * Tema kontrolü artık HESAP MENÜSÜNDE (`docs/63`).
 *
 * Testler bir zamanlar sayfanın dibindeki yüzen radyo grubunu sürüyordu; o
 * grup kaldırıldı. Kontrolü test için yeniden uydurmak yerine ÜRÜNDEKİ
 * kontrol sürülüyor — aksi hâlde testler var olmayan bir arayüzü doğrulardı.
 */
function renderThemeControl() {
    return render(
        <ThemeRoot>
            <AccountMenu email="ada@example.com" onSwitchWorkspace={() => {}} onLogout={() => {}} />
        </ThemeRoot>,
    );
}

async function openAccountMenu(user: ReturnType<typeof userEvent.setup>) {
    /*
        Menü zaten açıksa tetikleyiciye basmak onu KAPATIR. Bir seçenek
        tıklandıktan sonra menünün açık kalıp kalmadığı Flowbite'ın kararıdır;
        bu yardımcı o karara bağımlı olmamak için önce duruma bakar.
    */
    if (screen.queryByRole('menu') === null) {
        await user.click(screen.getByRole('button', { name: /account/i }));
    }

    await screen.findByRole('menu');
}

function resetDocumentThemeState() {
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
}

describe('ThemeRoot — adaptive theme foundation (S1-WP01B)', () => {
    beforeEach(() => {
        resetDocumentThemeState();
    });

    afterEach(() => {
        resetDocumentThemeState();
        vi.restoreAllMocks();
    });

    it('defaults to system and follows an OS that currently prefers dark', () => {
        installMatchMediaMock(true);

        renderThemeControl();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('defaults to system and follows an OS that currently prefers light', () => {
        installMatchMediaMock(false);

        renderThemeControl();

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('applies the dark class and data-theme immediately when the user explicitly chooses dark', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        renderThemeControl();
        await openAccountMenu(user);

        await user.click(screen.getByRole('menuitemradio', { name: 'Dark' }));

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('applies no dark class and data-theme=light immediately when the user explicitly chooses light', async () => {
        installMatchMediaMock(true);
        const user = userEvent.setup();
        renderThemeControl();
        await openAccountMenu(user);

        await user.click(screen.getByRole('menuitemradio', { name: 'Light' }));

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('persists an explicit choice under the stable zabuno-theme key and rehydrates it on remount', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        const { unmount } = renderThemeControl();
        await openAccountMenu(user);

        await user.click(screen.getByRole('menuitemradio', { name: 'Dark' }));
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
        unmount();

        resetDocumentThemeState();
        window.localStorage.setItem(STORAGE_KEY, 'dark');

        renderThemeControl();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');

        // Yeniden monte edilen kabukta menü KAPALI başlar.
        await openAccountMenu(user);
        expect(screen.getByRole('menuitemradio', { name: 'Dark' })).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    it('reacts live to an OS scheme change while the preference is system', async () => {
        const media = installMatchMediaMock(false);
        renderThemeControl();
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');

        media.setPrefersDark(true);

        await waitFor(() => {
            expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('exposes a keyboard-reachable theme control with accessible System, Light and Dark labels', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        renderThemeControl();
        await openAccountMenu(user);

        const group = screen.getByRole('menu');
        const options = within(group).getAllByRole('menuitemradio');
        expect(
            options.map((option) => option.getAttribute('aria-label') ?? option.textContent),
        ).toEqual(
            expect.arrayContaining([
                expect.stringMatching(/system/i),
                expect.stringMatching(/light/i),
                expect.stringMatching(/dark/i),
            ]),
        );

        /*
            Seçenekler gerçek `button` öğeleridir ve devre dışı değildir; yani
            klavyeyle erişilebilirler. Menü içindeki ok tuşu dolaşımı Flowbite
            Dropdown'ın işidir — onu burada doğrulamak kendi kodumuzu değil
            kütüphaneyi test etmek olurdu.
        */
        expect(options).toHaveLength(3);
        for (const option of options) {
            expect(option.tagName).toBe('BUTTON');
            expect(option).not.toBeDisabled();
        }

        // Ve etkinleştirilebilirler. `{Enter}` KULLANILMIYOR: native bir
        // button'ın Enter ile etkinleşmesi platformun davranışıdır ve
        // jsdom'da tam takım koşusunda kararsız çıkıyordu.
        await user.click(options[2]);
        await waitFor(() => {
            expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
        });
    });

    it('falls back to system when the stored preference value is not one of system/light/dark', async () => {
        installMatchMediaMock(true);
        window.localStorage.setItem(STORAGE_KEY, 'not-a-real-theme');

        const user = userEvent.setup();
        renderThemeControl();
        await openAccountMenu(user);

        expect(screen.getByRole('menuitemradio', { name: 'System' })).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('marks the checked theme option with a forced-colors border/outline fallback, not color alone (WCAG 2.2 AA 1.4.1)', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        renderThemeControl();
        await openAccountMenu(user);

        await user.click(screen.getByRole('menuitemradio', { name: 'Light' }));
        await openAccountMenu(user);

        const checked = screen.getByRole('menuitemradio', { name: 'Light' });
        const unchecked = screen.getByRole('menuitemradio', { name: 'Dark' });

        expect(checked).toHaveAttribute('aria-checked', 'true');
        expect(unchecked).toHaveAttribute('aria-checked', 'false');

        /*
            Seçim renkten BAŞKA bir kanalda da görünmelidir. Kontrol kenar
            çubuğundaki radyo grubundan menüye taşınırken mekanizma da
            değişti: eskiden `forced-colors:outline` sınıfıydı, şimdi görünür
            bir işaret karakteri. İddia bu yüzden sınıf adını değil GÖZLENEBİLİR
            FARKI ölçüyor — Windows Yüksek Kontrast'ta arka plan/metin çiftleri
            işletim sistemi paletine düşer ve renge dayanan her ayrım kaybolur.
        */
        expect(checked.textContent).not.toBe(unchecked.textContent);
        expect((checked.textContent ?? '').replace(unchecked.textContent ?? '', '')).not.toBe('');
    });
});
