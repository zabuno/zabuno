import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SidebarMenu } from './SidebarMenu';

/**
 * FF-96 — sahibin isteği (2026-09-04): "bu butonu dropdown gibi düşün. Sağ
 * tarafında chevron ok işareti, ama yukarıya işaret ediyor (drop down vs drop
 * up) ve açılan panel ile genişliği aynı, açılan panel ise bu buton ile
 * bütünleşik açılıyor."
 *
 * Gereksinim: SIDEBAR-MENU-WIDTH-01 (panel tetikleyiciyle aynı kutuda),
 * SIDEBAR-MENU-PLACEMENT-02 (yön), SIDEBAR-MENU-ATTACHED-03 (yapışıklık),
 * SIDEBAR-MENU-KEYBOARD-04 (Escape ve ok tuşları).
 */
function renderMenu(placement: 'down' | 'up' = 'down', onSelect = vi.fn()) {
    render(
        <SidebarMenu
            label="Workspace"
            placement={placement}
            triggerContent={<span>Zeytin Kebap</span>}
            items={[
                { key: 'a', label: 'Zeytin Kebap', selected: true, onSelect },
                { key: 'b', label: 'Deniz Kebap', selected: false, onSelect },
            ]}
        />,
    );

    return { trigger: screen.getByRole('button', { name: 'Workspace' }), onSelect };
}

describe('kenar çubuğu menüsü', () => {
    // --- SIDEBAR-MENU-WIDTH-01 --------------------------------------------
    it('panel tetikleyicinin kutusuna göre konumlanır; genişlik ayrıca hesaplanmaz', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu();

        await user.click(trigger);

        const panel = screen.getByRole('menu', { name: 'Workspace' });
        /*
            `inset-x-0` panelin iki kenarını tetikleyicinin kutusuna bağlar.
            Yüzen bir katman olsaydı genişlik her açılışta ölçülmek zorunda
            kalır ve ölçüm bir kare geç geldiğinde panel önce yanlış
            genişlikte görünürdü.
        */
        expect(panel.className).toContain('inset-x-0');
        expect(panel.parentElement?.className).toContain('relative');
        expect(panel.parentElement?.className).toContain('w-full');
    });

    // --- SIDEBAR-MENU-PLACEMENT-02 / 03 -----------------------------------
    it('yukarı açılan menü tetikleyicinin ÜSTÜNE yapışır', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu('up');

        await user.click(trigger);

        const panel = screen.getByRole('menu', { name: 'Workspace' });
        expect(panel.className).toContain('bottom-full');
        // Aradaki köşe yuvarlaklığı düşer: iki kutu tek yüzey gibi okunur.
        expect(panel.className).toContain('rounded-t-');
        expect(trigger.className).toContain('rounded-t-none');
    });

    it('aşağı açılan menü tetikleyicinin ALTINA yapışır', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu('down');

        await user.click(trigger);

        const panel = screen.getByRole('menu', { name: 'Workspace' });
        expect(panel.className).toContain('top-full');
        expect(trigger.className).toContain('rounded-b-none');
    });

    it("kapalıyken panel DOM'da hiç yoktur ve tetikleyici bunu duyurur", () => {
        const { trigger } = renderMenu();

        expect(trigger).toHaveAttribute('aria-expanded', 'false');
        expect(trigger).toHaveAttribute('aria-haspopup', 'menu');
        expect(screen.queryByRole('menu')).toBeNull();
    });

    // --- SIDEBAR-MENU-KEYBOARD-04 -----------------------------------------
    it('Escape menüyü kapatır ve odağı tetikleyiciye geri verir', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu();

        await user.click(trigger);
        expect(screen.getByRole('menu', { name: 'Workspace' })).toBeInTheDocument();

        await user.keyboard('{Escape}');

        await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
        expect(trigger).toHaveFocus();
    });

    it('ok tuşları satırlar arasında dolaşır', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu();

        await user.click(trigger);
        const rows = screen.getAllByRole('menuitemradio');
        rows[0].focus();

        await user.keyboard('{ArrowDown}');
        expect(rows[1]).toHaveFocus();

        // Son satırdan sonra başa döner: liste bir döngüdür, çıkmaz değil.
        await user.keyboard('{ArrowDown}');
        expect(rows[0]).toHaveFocus();
    });

    it('bir satır seçilince menü kapanır ve seçim yukarı bildirilir', async () => {
        const user = userEvent.setup();
        const { trigger, onSelect } = renderMenu();

        await user.click(trigger);
        await user.click(screen.getByRole('menuitemradio', { name: 'Deniz Kebap' }));

        expect(onSelect).toHaveBeenCalledTimes(1);
        await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
    });

    it('içinde bulunulan satır SEÇİLİ olarak duyurulur', async () => {
        const user = userEvent.setup();
        const { trigger } = renderMenu();

        await user.click(trigger);

        expect(screen.getByRole('menuitemradio', { name: 'Zeytin Kebap' })).toBeChecked();
        expect(screen.getByRole('menuitemradio', { name: 'Deniz Kebap' })).not.toBeChecked();
    });
});
