import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

import { InvoiceListPanel, type InvoiceRecord } from './InvoiceListPanel';
import { WorkspaceInvoices } from './WorkspaceInvoices';

/**
 * FATURA YÜZEYİ (docs/107 Faz 1.4, docs/130).
 *
 * Dondurulan sözleşme:
 *  - Belge numarası, tarihi ve indirme bağlantısı görünür.
 *  - Şirket bilgisi eksikken panel bunu SÖYLER; ünvan uydurulmaz.
 *  - KDV oranı yoksa "ayrım yok" yazılır; sıfır KDV gösterilmez.
 *  - Belgesi olmayan tahsilat sayısı gizlenmez.
 *  - e-arşiv bağlı değilken bu açıkça yazılır; "gönderildi" denmez.
 *  - Panelde hiçbir YAZMA kontrolü yoktur: belge elle kesilemez.
 */
const record: InvoiceRecord = {
    id: 7,
    kind: 'invoice',
    document_number: '2026-000001',
    issued_at: '2026-09-07T10:00:00+00:00',
    currency: 'TRY',
    amount_minor: 149900,
    vat_rate_basis_points: null,
    net_minor: null,
    vat_minor: null,
    plan_name: 'Pro',
    period_days: 30,
    seller: { complete: false, missing: ['legal_name'] },
    document_url: '/api/workspaces/1/invoices/7/document.pdf',
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('InvoiceListPanel', () => {
    it('shows the document number and a download link for the paper copy', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[record]}
                paymentsWithoutDocument={0}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(screen.getByText(/2026-000001/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Download PDF' })).toHaveAttribute(
            'href',
            '/api/workspaces/1/invoices/7/document.pdf',
        );
    });

    it('says the company details are incomplete instead of inventing them', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[record]}
                paymentsWithoutDocument={0}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(screen.getByText(/company details are not complete/i)).toBeInTheDocument();
    });

    it('shows no VAT breakdown when no rate is configured', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[record]}
                paymentsWithoutDocument={0}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(screen.getByText(/No VAT rate is configured/i)).toBeInTheDocument();
        expect(screen.queryByText(/^VAT /)).not.toBeInTheDocument();
    });

    it('never claims an e-archive dispatch that did not happen', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[record]}
                paymentsWithoutDocument={0}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(
            screen.getByText(/No e-Arşiv \/ e-Fatura provider is connected/i),
        ).toBeInTheDocument();
        expect(screen.queryByText(/sent to the tax authority/i)).not.toBeInTheDocument();
    });

    it('does not hide a collected payment that has no document', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[]}
                paymentsWithoutDocument={2}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(screen.getByText(/2 collected payment\(s\) have no document/i)).toBeInTheDocument();
    });

    it('offers no control that could create or edit a document by hand', () => {
        render(
            <InvoiceListPanel
                state="ready"
                invoices={[record]}
                paymentsWithoutDocument={0}
                earchiveConfigured={false}
                onRetry={() => undefined}
            />,
        );

        expect(screen.queryAllByRole('button')).toHaveLength(0);
    });
});

describe('WorkspaceInvoices', () => {
    it('reads the server and renders what it returns', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    invoices: [record],
                    payments_without_document: 0,
                    earchive: { configured: false, state: 'not_configured' },
                }),
            }),
        );

        render(<WorkspaceInvoices workspaceId={1} />);

        await waitFor(() => {
            expect(screen.getByText(/2026-000001/)).toBeInTheDocument();
        });
    });

    it('treats an unrecognised response body as an error, not as an empty list', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ invoices: [{ id: 'seven' }] }),
            }),
        );

        render(<WorkspaceInvoices workspaceId={1} />);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Invoices could not be loaded.');
        });
    });
});
