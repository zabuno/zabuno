import type { Meta, StoryObj } from '@storybook/react-vite';

import { ThemeRoot } from '../../../theme/ThemeRoot';
import { InvoiceListPanel, type InvoiceRecord } from './InvoiceListPanel';

/**
 * Fatura listesi — EKRAN kipi (docs/107 Faz 1.4, docs/130 §K6).
 *
 * Hikâyeler sunum katmanını ağ olmadan gösterir; `scripts/mobile-ux-audit`
 * her durumu 320×568'de ÖLÇER. Burada tablo yok: beş sütunlu bir tablo dar
 * ekranda ya yana kayar ya okunmaz olurdu. Kâğıt kipi ayrıdır ve sunucuda
 * üretilen A4 PDF'tir.
 */
const record: InvoiceRecord = {
    id: 1,
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
    seller: { complete: false, missing: ['legal_name', 'address', 'tax_number'] },
    document_url: '/api/workspaces/1/invoices/1/document.pdf',
};

const meta: Meta<typeof InvoiceListPanel> = {
    title: 'Surface/Workspace/BillingInvoiceList',
    component: InvoiceListPanel,
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-fluid-md)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: {
        state: 'ready',
        invoices: [record],
        paymentsWithoutDocument: 0,
        earchiveConfigured: false,
        onRetry: () => undefined,
    },
};

export default meta;

type Story = StoryObj<typeof InvoiceListPanel>;

/** Şirket bilgisi henüz girilmedi: belge eksik olduğunu SÖYLER. */
export const SellerNotYetProvided: Story = {};

/** Şirket bilgisi tam ve KDV oranı yapılandırılmış. */
export const CompleteWithVat: Story = {
    args: {
        invoices: [
            {
                ...record,
                seller: { complete: true, missing: [] },
                vat_rate_basis_points: 2000,
                net_minor: 124917,
                vat_minor: 24983,
            },
        ],
    },
};

/** Bir satış ve onun iade belgesi yan yana: fatura silinmez. */
export const WithCreditNote: Story = {
    args: {
        invoices: [
            {
                ...record,
                id: 2,
                kind: 'credit_note',
                document_number: '2026-000002',
                seller: { complete: true, missing: [] },
            },
            { ...record, seller: { complete: true, missing: [] } },
        ],
    },
};

/** Henüz tahsilat yok. */
export const Empty: Story = {
    args: { invoices: [] },
};

/** Alıcısı kaydedilmemiş bir tahsilatın belgesi kesilemedi — sayı görünür. */
export const PaymentWithoutDocument: Story = {
    args: { invoices: [], paymentsWithoutDocument: 1 },
};

/** Liste okunamadı: yol yeniden açık. */
export const LoadError: Story = {
    args: { state: 'error', invoices: [] },
};
