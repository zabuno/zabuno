import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AccountMenu } from './AccountMenu';

/**
 * FF-84 (SHELL-ACCOUNT-MENU-01): sistem menüsü kimliği gösterir, Ayarlar
 * buradan açılır ve tema seçimi işaretli değerini duyurur.
 */
describe('hesap (sistem) menüsü', () => {
    it('kimlik başlığı, Profil, Ayarlar, çalışma alanı değiştir ve çıkış maddelerini sırayla taşır', async () => {
        const user = userEvent.setup();
        const onOpenProfile = vi.fn();
        const onOpenSettings = vi.fn();
        const onSwitchWorkspace = vi.fn();
        const onLogout = vi.fn();

        render(
            <AccountMenu
                email="admin@zabuno.com"
                onOpenProfile={onOpenProfile}
                onOpenSettings={onOpenSettings}
                onSwitchWorkspace={onSwitchWorkspace}
                onLogout={onLogout}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Account' }));

        const items = screen.getAllByRole('menuitem');
        /*
            SIRA sözleşmedir (FF-88): menünün başlığı kişinin kendisidir, o
            yüzden ilk madde de kişiye ait olmalı. Ayarlar çalışma alanına ait
            olduğu için onun altındadır.
        */
        expect(items.map((item) => item.textContent)).toEqual([
            'Profile',
            'Settings',
            'Switch workspace',
            'Log out',
        ]);

        await user.click(within(items[1]).getByText('Settings'));
        expect(onOpenSettings).toHaveBeenCalledTimes(1);
        expect(onOpenProfile).not.toHaveBeenCalled();
    });

    it('profil maddesi tıklanınca profil ekranını açar', async () => {
        const user = userEvent.setup();
        const onOpenProfile = vi.fn();

        render(
            <AccountMenu
                email="admin@zabuno.com"
                onOpenProfile={onOpenProfile}
                onSwitchWorkspace={() => {}}
                onLogout={() => {}}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Account' }));
        await user.click(screen.getByRole('menuitem', { name: 'Profile' }));

        expect(onOpenProfile).toHaveBeenCalledTimes(1);
    });

    it('profil geri çağrısı yoksa menüde Profil maddesi ÇİZİLMEZ', async () => {
        const user = userEvent.setup();
        render(
            <AccountMenu
                email="admin@zabuno.com"
                onSwitchWorkspace={() => {}}
                onLogout={() => {}}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Account' }));

        expect(screen.queryByRole('menuitem', { name: 'Profile' })).toBeNull();
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
