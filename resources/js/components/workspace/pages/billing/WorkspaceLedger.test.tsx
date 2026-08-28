import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { WorkspaceLedger } from './WorkspaceLedger';

/**
 * CORE-12 defter okuma yüzeyi — LEDGER-UI-09.
 *
 * Dondurulan sözleşme: bileşen sunucudan ne geliyorsa onu gösterir, hiçbir
 * tutar uydurmaz, yazma kontrolü sunmaz ve hata hâlinde sessizce boş
 * görünmez.
 */
const WORKSPACE_ID = 51;
const LEDGER_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/ledger`;

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function ledgerPayload() {
    return {
        entries: [
            {
                id: 7,
                reference: 'iyzico-sandbox:3',
                debitAccount: 'cash',
                creditAccount: 'revenue',
                amountMinor: 149900,
                currencyCode: 'TRY',
                description: 'Iyzico sandbox tahsilatı',
                occurredAt: '2026-08-26 10:00:00',
            },
        ],
        balances: { cash: 149900, revenue: -149900 },
        currency: 'TRY',
    };
}

describe('WorkspaceLedger', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('reads the workspace ledger from the server and shows the entry as recorded', async () => {
        vi.mocked(fetch).mockResolvedValue(jsonResponse(200, ledgerPayload()));

        render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

        const region = await screen.findByRole('region', { name: 'Ledger' });

        await waitFor(() => {
            expect(within(region).getByText('iyzico-sandbox:3')).toBeInTheDocument();
        });

        expect(fetch).toHaveBeenCalledWith(
            LEDGER_ENDPOINT,
            expect.objectContaining({ credentials: 'same-origin' }),
        );
        // Hesap adı hem satırda hem bakiye özetinde görünür.
        expect(within(region).getAllByText('cash')).toHaveLength(2);
        expect(within(region).getAllByText('revenue')).toHaveLength(2);

        const balances = within(region).getByRole('region', { name: 'Balances' });
        // 149900 kuruş = 1.499,00 TL — kuruş, para biriminin ondalık
        // basamağına göre çevrilir, sabit 100'e bölünerek değil.
        expect(within(balances).getByText('TRY 1,499.00')).toBeInTheDocument();
        expect(within(balances).getByText('-TRY 1,499.00')).toBeInTheDocument();
    });

    it('shows a Turkish reader Turkish money formatting, from the document language alone', async () => {
        // Biçim, bileşenin içine gömülü değildir: belge dili değişince tutar
        // da değişir. Zabuno'nun asıl kitlesi Türk restoranlarıdır ve
        // "TRY 1,499.00" onlara yabancıdır.
        document.documentElement.lang = 'tr';
        vi.mocked(fetch).mockResolvedValue(jsonResponse(200, ledgerPayload()));

        try {
            render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

            const region = await screen.findByRole('region', { name: 'Ledger' });
            const balances = await within(region).findByRole('region', { name: 'Balances' });

            expect(within(balances).getByText('₺1.499,00')).toBeInTheDocument();
        } finally {
            document.documentElement.lang = '';
        }
    });

    it('offers no control that could write or edit a ledger entry', async () => {
        vi.mocked(fetch).mockResolvedValue(jsonResponse(200, ledgerPayload()));

        render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

        const region = await screen.findByRole('region', { name: 'Ledger' });
        await waitFor(() => {
            expect(within(region).getByText('iyzico-sandbox:3')).toBeInTheDocument();
        });

        // Defter yalnız gerçek bir tahsilatla yazılır; elle düzeltilebilen
        // bir defter kanıt değeri taşımaz.
        expect(within(region).queryAllByRole('button')).toHaveLength(0);
        expect(within(region).queryAllByRole('textbox')).toHaveLength(0);
    });

    it('says the ledger is empty instead of pretending there is nothing to bill', async () => {
        vi.mocked(fetch).mockResolvedValue(
            jsonResponse(200, { entries: [], balances: {}, currency: null }),
        );

        render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

        expect(
            await screen.findByText(
                'No ledger entry yet. The first successful payment writes the first entry.',
            ),
        ).toBeInTheDocument();
    });

    it('surfaces a failure and lets the owner retry rather than showing a false empty ledger', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(500, {}));

        render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'The ledger could not be loaded.',
        );

        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(200, ledgerPayload()));
        await userEvent.click(screen.getByRole('button', { name: 'Retry' }));

        expect(await screen.findByText('iyzico-sandbox:3')).toBeInTheDocument();
    });

    it('refuses a malformed payload instead of rendering a wrong amount', async () => {
        vi.mocked(fetch).mockResolvedValue(
            jsonResponse(200, { entries: [{ id: 'seven' }], balances: {} }),
        );

        render(<WorkspaceLedger workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'The ledger could not be loaded.',
        );
    });
});
