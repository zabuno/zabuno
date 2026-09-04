import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AccountMenu } from './AccountMenu';

/**
 * FF-84 (SHELL-ACCOUNT-MENU-01): sistem menüsü kimliği gösterir, Ayarlar
 * buradan açılır ve tema seçimi işaretli değerini duyurur.
 */
describe('hesap (sistem) menüsü', () => {
    it('kimlik başlığı, Ayarlar, çalışma alanı değiştir ve çıkış maddelerini sırayla taşır', async () => {
        const user = userEvent.setup();
        const onOpenSettings = vi.fn();
        const onSwitchWorkspace = vi.fn();
        const onLogout = vi.fn();

        render(
            <AccountMenu
                email="admin@zabuno.com"
                onOpenSettings={onOpenSettings}
                onSwitchWorkspace={onSwitchWorkspace}
                onLogout={onLogout}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Account' }));

        const items = screen.getAllByRole('menuitem');
        expect(items.map((item) => item.textContent)).toEqual([
            'Settings',
            'Switch workspace',
            'Log out',
        ]);

        await user.click(within(items[0]).getByText('Settings'));
        expect(onOpenSettings).toHaveBeenCalledTimes(1);
    });

    it('ayarlar geri çağrısı yoksa menüde Ayarlar maddesi ÇİZİLMEZ', async () => {
        const user = userEvent.setup();
        render(
            <AccountMenu
                email="admin@zabuno.com"
                onSwitchWorkspace={() => {}}
                onLogout={() => {}}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Account' }));

        expect(screen.queryByRole('menuitem', { name: 'Settings' })).toBeNull();
    });
});
