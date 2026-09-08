import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamMemberTableDesktop } from './TeamMemberTableDesktop';
import type { TeamMemberListSurfaceContext } from '../team/memberListSurface';

/**
 * EKİP TABLOSU — MASAÜSTÜ (`docs/151` §B).
 *
 * İki soru donuyor ve ikisi de dokunmalı listede sorulamaz:
 *
 * 1. **Giriş kipi gerçekten var mı?** Klavyeyle gezinme, çoklu seçim, toplu
 *    rol değişimi, toplu çıkarma ve sağ tıkın klavye karşılığı.
 * 2. **Toplu işlem YETKİ SINIRINI genişletiyor mu?** Bu ikincisi asıl
 *    tehlikeli olandır: masaüstü sürümü tek tek yapılamayan bir işi toplu
 *    hâlde yapabilseydi, ekran sunucunun reddedeceği bir işi vaat eder ya
 *    da — daha kötüsü — sahip satırını sessizce değiştirirdi.
 */

const MEMBERS = [
    { id: 1, name: 'Ayşe', email: 'ayse@example.test', role: 'owner' },
    { id: 2, name: 'Bora', email: 'bora@example.test', role: 'editor' },
    { id: 3, name: 'Ceren', email: 'ceren@example.test', role: 'member' },
];

function surface(
    overrides: Partial<TeamMemberListSurfaceContext> = {},
): TeamMemberListSurfaceContext {
    return {
        status: 'success',
        members: MEMBERS,
        label: 'Team members',
        loadingText: 'Loading team members…',
        errorText: 'Team members failed to load.',
        emptyText: 'No members yet.',
        onRemoveMember: vi.fn(() => Promise.resolve('success' as const)),
        onChangeRole: vi.fn(() => Promise.resolve('success' as const)),
        assignableRoles: [
            { value: 'editor', label: 'Editor' },
            { value: 'manager', label: 'Manager' },
        ],
        roleLabelFor: (name) => `Role for ${name}`,
        roleErrorText: 'The role could not be changed.',
        removeButtonText: 'Remove',
        removeConfirmText: 'Confirm remove',
        removeCancelText: 'Cancel',
        removeBusyText: 'Removing…',
        removeErrorText: 'Unable to remove this member.',
        removeForbiddenText: 'Only the owner can remove people.',
        removeMissingText: 'This person is no longer in the team list.',
        removeSuccessText: 'Member removed.',
        removeRetryText: 'Retry',
        removableRoles: ['editor', 'manager', 'member'],
        viewerIsOwner: true,
        onTransferOwnership: vi.fn(() => Promise.resolve('success' as const)),
        transferButtonText: 'Transfer ownership',
        transferDialogTitle: 'Transfer workspace ownership',
        transferDialogBody: 'Body',
        transferConfirmText: 'Confirm',
        transferCancelText: 'Cancel',
        transferBusyText: 'Transferring…',
        transferErrorText: 'Unable to transfer ownership.',
        transferRetryText: 'Retry',
        transferSuccessText: 'Ownership transferred.',
        ...overrides,
    };
}

async function rows() {
    return within(await screen.findByRole('listbox')).findAllByRole('option');
}

describe('TeamMemberTableDesktop', () => {
    it('listeye bir kez Tab ile girilir ve içinde oklarla gezilir', async () => {
        const user = userEvent.setup();
        render(<TeamMemberTableDesktop {...surface()} />);

        const options = await rows();

        expect(options[0]).toHaveAttribute('tabindex', '0');
        expect(options[1]).toHaveAttribute('tabindex', '-1');

        options[0]?.focus();
        await user.keyboard('{ArrowDown}');

        expect(options[1]).toHaveFocus();
    });

    it('satırın erişilebilir adı ad, e-posta ve rolü birlikte söyler', async () => {
        render(<TeamMemberTableDesktop {...surface()} />);

        const options = await rows();

        // Ad arayüzde birleştirilmez, katalogdan gelir (FF-213). Ekran
        // okuyucu üç sütunu tek satırda duyar.
        expect(options[1]).toHaveAccessibleName('Bora, bora@example.test, editor');
    });

    it('toplu rol değişimi seçilen her üye için tek bir yazma yolu çağırır', async () => {
        const user = userEvent.setup();
        const context = surface();
        render(<TeamMemberTableDesktop {...context} />);

        const options = await rows();
        /*
            Önce TIKLA: roving tabindex'in etkin satırı listenin başındadır
            ve `focus()` onu taşımaz. Gerçek kullanıcı da satıra basar.
        */
        await user.click(options[1] as HTMLElement);
        await user.keyboard(' ');
        await user.keyboard('{Shift>}{ArrowDown}{/Shift}');

        await user.selectOptions(screen.getByRole('combobox'), 'manager');

        expect((context.onChangeRole as ReturnType<typeof vi.fn>).mock.calls).toEqual([
            [2, 'manager'],
            [3, 'manager'],
        ]);
    });

    it('SAHİP SATIRI toplu rol değişimine girmez ve atlandığı yazılır', async () => {
        const user = userEvent.setup();
        const context = surface();
        render(<TeamMemberTableDesktop {...context} />);

        const options = await rows();
        options[0]?.focus();
        await user.keyboard('{Control>}a{/Control}');

        await user.selectOptions(screen.getByRole('combobox'), 'editor');

        // Sahiplik silinmez, DEVREDİLİR — ve toplu bir açılır listeyle
        // kesinlikle değiştirilmez.
        expect((context.onChangeRole as ReturnType<typeof vi.fn>).mock.calls).toEqual([
            [2, 'editor'],
            [3, 'editor'],
        ]);
        expect(await screen.findByRole('status')).toHaveTextContent('1 row(s) were left');
    });

    it('sahip olmayan biri çıkarma düğmesini hiç görmez', async () => {
        const user = userEvent.setup();
        render(<TeamMemberTableDesktop {...surface({ viewerIsOwner: false })} />);

        const options = await rows();
        await user.click(options[1] as HTMLElement);
        await user.keyboard(' ');

        // Yapılamayan iş çizilmez: uç nokta yöneticiye 403 döner.
        expect(screen.queryByRole('button', { name: 'Remove' })).not.toBeInTheDocument();
    });

    it('sağ tıkın klavye karşılığı vardır ve menü yalnız yapılabilen işi taşır', async () => {
        const user = userEvent.setup();
        render(<TeamMemberTableDesktop {...surface({ viewerIsOwner: false })} />);

        const options = await rows();
        await user.click(options[1] as HTMLElement);
        await user.keyboard('{Shift>}{F10}{/Shift}');

        const menu = await screen.findByRole('menu', { name: 'Member actions' });

        expect(within(menu).getByRole('menuitem', { name: 'Editor' })).toBeInTheDocument();
        expect(within(menu).queryByRole('menuitem', { name: 'Remove' })).not.toBeInTheDocument();
    });

    it('toplu çıkarmada başarısızlık AYRI sayılır ve sebebi yazılır', async () => {
        const user = userEvent.setup();
        const context = surface({
            onRemoveMember: vi.fn(() => Promise.resolve('forbidden' as const)),
        });
        render(<TeamMemberTableDesktop {...context} />);

        const options = await rows();
        await user.click(options[1] as HTMLElement);
        await user.keyboard(' ');
        await user.click(screen.getByRole('button', { name: 'Remove' }));

        // "1 updated" deyip tutmayan isteği yutmak, bitmemiş bir işi bitmiş
        // göstermek olurdu.
        const status = await screen.findByRole('status');
        expect(status).toHaveTextContent('0 updated, 1 did not go through.');
        expect(status).toHaveTextContent('Only the owner can remove people.');
    });
});
