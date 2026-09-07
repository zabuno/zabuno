import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * DENETİM GÜNLÜĞÜ EKRANI — `docs/122` §3 boşluk 6, dalga Y2.
 *
 * Ölçülen cümle: "Kayıt yazılıyor, okunacak yer yok." Dört tablo aylardır
 * doluyor ve platform düzeyinde hiçbirini okuyan yok. Okunmayan denetim izi
 * yoktur.
 *
 * Bu ekran YALNIZ OKUR ve satır başına yalnız "kim, ne, ne zaman" taşır:
 * menü izinin öncesi/sonrası değerleri (fiyat, alerjen) kiracının kendi
 * ekranında yerindedir, kiracılar arası bir listede fazladan veridir.
 */

const PAGE_ONE = {
    entries: [
        {
            id: 'credential:1',
            source: 'credential',
            action: 'set',
            subject: 'openai',
            actor: 'sahip@ornek.test',
            workspaceId: null,
            workspaceName: null,
            at: '2026-09-01 13:00:00',
        },
        {
            id: 'menu:4',
            source: 'menu',
            action: 'item_price_changed',
            subject: 'Adana Kebap',
            actor: 'sahip@ornek.test',
            workspaceId: 7,
            workspaceName: 'Kebapçı Ali',
            at: '2026-09-01 10:00:00',
        },
    ],
    page: 1,
    perPage: 50,
    hasMore: true,
    sources: ['media', 'menu', 'publication', 'credential'],
};

const PAGE_TWO = {
    ...PAGE_ONE,
    entries: [
        {
            id: 'media:2',
            source: 'media',
            action: 'deleted',
            subject: 'Kuzu pirzola',
            actor: null,
            workspaceId: 7,
            workspaceName: 'Kebapçı Ali',
            at: '2026-08-30 08:00:00',
        },
    ],
    page: 2,
    hasMore: false,
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function stubFetch(status = 200) {
    const mock = vi.fn(async (url: string) =>
        jsonResponse(status, String(url).includes('page=2') ? PAGE_TWO : PAGE_ONE),
    );
    vi.stubGlobal('fetch', mock);

    return mock;
}

async function importPage() {
    return import('./AuditLogPage') as unknown as Promise<{
        AuditLogPage: React.ComponentType;
    }>;
}

function rowFor(region: HTMLElement, text: string): HTMLElement {
    const row = within(region)
        .getAllByRole('row')
        .find((candidate) => within(candidate).queryByText(text) !== null);

    if (row === undefined) throw new Error(`"${text}" satırı yok.`);

    return row;
}

describe('AuditLogPage', () => {
    beforeEach(() => {
        stubFetch();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows who did what and when, with the source of each row', async () => {
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);

        const region = await screen.findByRole('region', { name: 'Events' });
        const menuRow = rowFor(region, 'Adana Kebap');

        expect(within(menuRow).getByText('item_price_changed')).toBeInTheDocument();
        expect(within(menuRow).getByText('sahip@ornek.test')).toBeInTheDocument();
        expect(within(menuRow).getByText('Kebapçı Ali')).toBeInTheDocument();
        expect(within(menuRow).getByText('Menu')).toBeInTheDocument();
        expect(within(menuRow).getByText('2026-09-01 10:00:00')).toBeInTheDocument();
    });

    it('leaves the workspace cell of a platform-level row empty instead of guessing a tenant', async () => {
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);

        const region = await screen.findByRole('region', { name: 'Events' });
        const row = rowFor(region, 'openai');

        expect(within(row).queryByText('Kebapçı Ali')).not.toBeInTheDocument();
        expect(within(row).getByText('Credential vault')).toBeInTheDocument();
    });

    it('keeps a row whose actor is gone instead of hiding the event', async () => {
        const mock = stubFetch();
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);
        await screen.findByRole('region', { name: 'Events' });

        await userEvent.click(screen.getByRole('button', { name: 'Next page' }));

        const region = await screen.findByRole('region', { name: 'Events' });
        expect(rowFor(region, 'Kuzu pirzola')).toBeInTheDocument();

        expect(mock).toHaveBeenCalledWith(expect.stringContaining('page=2'), expect.anything());
    });

    it('stops offering a next page when the server says there is none', async () => {
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);
        await screen.findByRole('region', { name: 'Events' });

        await userEvent.click(screen.getByRole('button', { name: 'Next page' }));
        await screen.findByText('Kuzu pirzola');

        expect(screen.getByRole('button', { name: 'Next page' })).toBeDisabled();
    });

    it('narrows the log through the endpoint when a source is chosen', async () => {
        const mock = stubFetch();
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);
        await screen.findByRole('region', { name: 'Events' });

        await userEvent.selectOptions(screen.getByLabelText('Source'), 'menu');

        expect(mock).toHaveBeenCalledWith(
            expect.stringContaining('source=menu'),
            expect.anything(),
        );
    });

    it('says it could not read rather than drawing an empty log', async () => {
        stubFetch(500);
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'We could not load the audit log.',
        );
        expect(screen.queryByRole('region', { name: 'Events' })).not.toBeInTheDocument();
    });

    it('offers no way to edit or erase a recorded event', async () => {
        const { AuditLogPage } = await importPage();

        render(<AuditLogPage />);
        await screen.findByRole('region', { name: 'Events' });

        const forbidden = /delete|remove|edit|erase|clear log/i;

        for (const control of [
            ...screen.queryAllByRole('button'),
            ...screen.queryAllByRole('link'),
        ]) {
            expect(control).not.toHaveAccessibleName(forbidden);
        }
    });
});
