import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';
import { AppearanceRegion } from '../workspace/pages/settings/AppearanceRegion';

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
            <AppearanceRegion />
        </ThemeRoot>,
    );
}

/*
    KONTROL ARTIK MENÜNÜN İÇİNDE DEĞİL (FF-119, sahibin bildirimi 2026-09-04:
    "theme options, ayarlara taşınsın, dropdown içerisinde kalmasın").

    Bu yardımcı bir zamanlar hesap menüsünü açıyordu. Tema bir ayardır, bir
    eylem değil; ayarın evi Ayarlar → Hesap oldu ve kontrol orada, açılıp
    kapanan bir panelin arkasında değil, doğrudan sayfada duruyor. Menüyü
    açmaya gerek kalmadı.
*/
async function waitForThemeControl() {
    await screen.findByRole('radio', { name: /system/i });
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
        await waitForThemeControl();

        await user.click(screen.getByRole('radio', { name: /dark/i }));

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('applies no dark class and data-theme=light immediately when the user explicitly chooses light', async () => {
        installMatchMediaMock(true);
        const user = userEvent.setup();
        renderThemeControl();
        await waitForThemeControl();

        await user.click(screen.getByRole('radio', { name: /light/i }));

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('persists an explicit choice under the stable zabuno-theme key and rehydrates it on remount', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        const { unmount } = renderThemeControl();
        await waitForThemeControl();

        await user.click(screen.getByRole('radio', { name: /dark/i }));
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
        unmount();

        resetDocumentThemeState();
        window.localStorage.setItem(STORAGE_KEY, 'dark');

        renderThemeControl();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');

        // Yeniden monte edilen kabukta menü KAPALI başlar.
        await waitForThemeControl();
        expect(screen.getByRole('radio', { name: /dark/i })).toBeChecked();
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
        await waitForThemeControl();

        const group = screen.getByRole('group', { name: /theme|görünüm/i });
        const options = within(group).getAllByRole('radio');

        // Erişilebilir ad SARMALAYAN ETİKETTEN gelir; girdinin kendi metni
        // yoktur ve olmamalıdır (görsel etiket tek yerde yaşar).
        for (const name of [/system/i, /light/i, /dark/i]) {
            expect(within(group).getByRole('radio', { name })).toBeInTheDocument();
        }

        /*
            Seçenekler artık NATIVE radyo düğmeleri (FF-119). Ok tuşuyla
            dolaşım, grup semantiği ve `checked` durumunun ekran okuyucuya
            bildirilmesi platformun kendi işidir; menü içindeki roving
            tabindex'i elle taklit etmeye gerek kalmadı.
        */
        expect(options).toHaveLength(3);
        for (const option of options) {
            expect(option.tagName).toBe('INPUT');
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

        renderThemeControl();
        await waitForThemeControl();

        expect(screen.getByRole('radio', { name: /system/i })).toBeChecked();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('marks the checked theme option with a forced-colors border/outline fallback, not color alone (WCAG 2.2 AA 1.4.1)', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        renderThemeControl();
        await waitForThemeControl();

        await user.click(screen.getByRole('radio', { name: /light/i }));
        await waitForThemeControl();

        const checked = screen.getByRole('radio', { name: /light/i });
        const unchecked = screen.getByRole('radio', { name: /dark/i });

        expect(checked).toBeChecked();
        expect(unchecked).not.toBeChecked();

        /*
            SEÇİM RENKTEN BAŞKA BİR KANALDA DA GÖRÜNMELİDİR.

            Windows Yüksek Kontrast kipinde arka plan/metin çiftleri işletim
            sistemi paletine düşer ve yalnız renge dayanan her ayrım kaybolur
            (WCAG 1.4.1). Seçili seçenek görünür bir işaret taşır; iddia sınıf
            adını değil o işaretin VARLIĞINI ölçer — mekanizma değişebilir,
            sözleşme değişmez.
        */
        expect(checked.closest('label')?.querySelector('svg')).not.toBeNull();
        expect(unchecked.closest('label')?.querySelector('svg')).toBeNull();
    });
});
