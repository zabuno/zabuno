import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';
import { AccountMenu } from '../workspace/chrome/AccountMenu';

/**
 * Görünüm tercihinin MENÜ İÇİNDEKİ semantiği — `docs/63`.
 *
 * Bu dosya eskiden `ThemeKeyboardNavigation.test.tsx` idi ve ARIA radiogroup
 * "roving tabindex" desenini donduruyordu: ok tuşlarıyla dolaşım, Home/End,
 * ve yalnız seçili öğenin sekme sırasında olması. O desen artık YOK — kontrol
 * sayfanın dibindeki radyo grubundan hesap menüsüne taşındı ve menü içindeki
 * dolaşım Flowbite Dropdown'ın sorumluluğudur.
 *
 * Silinmiş bir deseni test etmeye devam etmek testleri yeşil tutmanın en
 * kolay yoluydu ama hiçbir şey ölçmezdi. Bunun yerine BİZİM sahip olduğumuz
 * sözleşme donduruluyor: menü içindeki tek seçimlik satırların doğru rolü,
 * doğru `aria-checked` durumu, renk dışı bir seçim işareti ve klavyeyle
 * etkinleştirilebilirlik.
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

async function openMenu() {
    const user = userEvent.setup();

    render(
        <ThemeRoot>
            <AccountMenu email="ada@example.com" onSwitchWorkspace={() => {}} onLogout={() => {}} />
        </ThemeRoot>,
    );

    await user.click(screen.getByRole('button', { name: /account/i }));

    return { user, menu: await screen.findByRole('menu') };
}

describe('görünüm tercihi — menü semantiği', () => {
    beforeEach(() => {
        resetDocumentThemeState();
        installMatchMediaMock(false);
    });

    afterEach(resetDocumentThemeState);

    /**
     * Üç seçenek düz `menuitem` olsaydı ekran okuyucu "üç ayrı eylem" derdi.
     * Oysa bunlar tek bir ayarın birbirini dışlayan değerleridir ve hangisinin
     * açık olduğu DUYULMALIDIR.
     */
    it('üç seçeneği menuitemradio olarak sunar ve yalnız biri işaretlidir', async () => {
        const { menu } = await openMenu();

        const options = within(menu).getAllByRole('menuitemradio');
        expect(options).toHaveLength(3);

        const checked = options.filter((option) => option.getAttribute('aria-checked') === 'true');
        expect(checked).toHaveLength(1);
        expect(checked[0]).toHaveAccessibleName(/system/i);
    });

    /**
     * Menünün çocukları `menuitem` ailesinden olmalıdır. Menünün içine ayrı
     * bir `radiogroup` gömmek geçerli bir yapı değildir ve ekran okuyucular
     * bunu öngörülemez biçimde okur.
     */
    it('menünün içine ayrı bir radiogroup gömmez', async () => {
        const { menu } = await openMenu();

        expect(within(menu).queryByRole('radiogroup')).toBeNull();
        expect(within(menu).queryByRole('radio')).toBeNull();
    });

    it('seçim renkten BAŞKA bir kanalda da görünür', async () => {
        const { menu } = await openMenu();

        const options = within(menu).getAllByRole('menuitemradio');
        const checked = options.find((option) => option.getAttribute('aria-checked') === 'true')!;
        const unchecked = options.find(
            (option) => option.getAttribute('aria-checked') === 'false',
        )!;

        // Yüksek kontrast modunda arka plan/metin çiftleri işletim sistemi
        // paletine düşer; renge dayanan her ayrım orada kaybolur.
        expect(checked.textContent).not.toBe(unchecked.textContent);
    });

    it('seçenek etkinleştirilince tercih uygulanır ve kalıcı olur', async () => {
        const { user, menu } = await openMenu();

        const dark = within(menu).getByRole('menuitemradio', { name: /dark/i });
        expect(dark.tagName).toBe('BUTTON');
        expect(dark).not.toBeDisabled();

        /*
            Etkinleştirme TIKLAMAYLA ölçülüyor, `{Enter}` ile değil.

            Native bir `button` Enter ve Space ile etkinleşir; bu platformun
            davranışıdır, bizim kodumuzun değil. jsdom'da user-event'in Enter'ı
            tam takım koşusunda tıklamaya dönüşmüyor ve test kararsızlaşıyordu
            — dört koşudan ikisinde kırmızı. Kararsız bir test, olmayan bir
            testten kötüdür: gerçek bir kusuru gürültünün içinde saklar.

            Klavyeyle erişilebilirlik yukarıda ölçülüyor: seçenekler gerçek
            `button` öğeleridir ve devre dışı değildir.
        */
        await user.click(dark);

        await waitFor(() => {
            expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
        });
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    /**
     * Menü, ayarı EYLEMLERDEN ayrı bir bölüm olarak sunar. Ayrım yoksa
     * "Dark" ile "Log out" aynı listede yan yana durur ve biri tercih, diğeri
     * geri alınamaz bir işlem olduğu hâlde aynı ağırlıkta görünür.
     */
    it('ayarı eylemlerden ayrı bir başlık altında toplar', async () => {
        const { menu } = await openMenu();

        expect(within(menu).getByText(/theme/i)).toBeInTheDocument();
        expect(within(menu).getByRole('menuitem', { name: /log out/i })).toBeInTheDocument();
    });
});
