import { t } from '../../../../i18n/workspace';
import { formatMoneyOr } from '../../../../money/format';
import { Button } from '../../../catalog/forms/micro/Button';

export type InvoiceRecord = {
    id: number;
    kind: 'invoice' | 'credit_note';
    document_number: string;
    issued_at: string;
    currency: string;
    amount_minor: number;
    vat_rate_basis_points: number | null;
    net_minor: number | null;
    vat_minor: number | null;
    plan_name: string;
    period_days: number;
    seller: { complete: boolean; missing: string[] };
    document_url: string;
};

export type InvoiceListPanelProps = {
    state: 'loading' | 'error' | 'ready';
    invoices: readonly InvoiceRecord[];
    /** Belgesi olmayan başarılı tahsilat sayısı — eksiklik saklanmaz. */
    paymentsWithoutDocument: number;
    earchiveConfigured: boolean;
    onRetry: () => void;
};

/**
 * Kesilmiş belgelerin EKRAN kipi (docs/107 Faz 1.4, docs/130 §K6).
 *
 * Kâğıt kipi ayrıdır ve ayrı olması bilinçlidir: A4 belgesi sunucuda
 * üretilir (`…/invoices/{id}/document.pdf`). Burada tablo YOKTUR — 320
 * pikselde beş sütunlu bir tablo ya yana kayar ya da sütunları okunmaz
 * hale gelir; her belge kendi bloğunda, tek sütunda durur.
 *
 * Panel hiçbir yazma kontrolü taşımaz: fatura yalnız gerçek bir tahsilatla
 * doğar, elle değil. Elle kesilebilen bir belge kanıt değeri taşımaz.
 */
export function InvoiceListPanel({
    state,
    invoices,
    paymentsWithoutDocument,
    earchiveConfigured,
    onRetry,
}: InvoiceListPanelProps) {
    return (
        <section
            role="region"
            aria-label={t('workspace.billing.invoices.region')}
            className="flex w-full flex-col gap-3"
        >
            <h2 className="text-body font-bold text-fg">
                {t('workspace.billing.invoices.region')}
            </h2>
            <p className="text-body text-fg-muted">{t('workspace.billing.invoices.description')}</p>

            {state === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.invoices.loading')}
                </p>
            )}

            {state === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.invoices.error')}
                    </p>
                    <Button size="sm" color="light" className="self-start" onClick={onRetry}>
                        {t('workspace.billing.invoices.retry')}
                    </Button>
                </div>
            )}

            {state === 'ready' && invoices.length === 0 && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.invoices.empty')}
                </p>
            )}

            {state === 'ready' && invoices.length > 0 && (
                <ul className="m-0 flex list-none flex-col gap-2 p-0">
                    {invoices.map((invoice) => (
                        <li
                            key={invoice.id}
                            className="flex flex-col gap-1 rounded-lg border border-border px-3 py-2"
                        >
                            <p className="text-body font-bold text-fg">
                                {invoice.kind === 'credit_note'
                                    ? t('workspace.billing.invoices.kind.creditNote')
                                    : t('workspace.billing.invoices.kind.invoice')}{' '}
                                {invoice.document_number}
                            </p>
                            <p className="text-body text-fg-muted">
                                {t('workspace.billing.invoices.issuedAt', {
                                    date: invoice.issued_at,
                                })}
                            </p>
                            <p className="text-body text-fg">
                                {formatMoneyOr(
                                    invoice.amount_minor,
                                    invoice.currency,
                                    String(invoice.amount_minor),
                                )}
                            </p>
                            <p className="text-body text-fg-muted">
                                {t('workspace.billing.invoices.line', {
                                    plan: invoice.plan_name,
                                    days: String(invoice.period_days),
                                })}
                            </p>
                            {invoice.vat_minor === null ? (
                                <p className="text-body text-fg-muted">
                                    {t('workspace.billing.invoices.vatUnknown')}
                                </p>
                            ) : (
                                <p className="text-body text-fg-muted">
                                    {t('workspace.billing.invoices.vat', {
                                        amount: formatMoneyOr(
                                            invoice.vat_minor,
                                            invoice.currency,
                                            String(invoice.vat_minor),
                                        ),
                                    })}
                                </p>
                            )}
                            {!invoice.seller.complete && (
                                /*
                                    Şirket bilgisi girilmemişken belge EKSİK
                                    olduğunu söyler; uydurulmuş bir ünvan
                                    yazmak yerine (FF-198).
                                */
                                <p className="text-body font-medium text-fg-warning">
                                    {t('workspace.billing.invoices.sellerIncomplete')}
                                </p>
                            )}
                            <a
                                href={invoice.document_url}
                                className="inline-flex min-h-11 items-center self-start text-body font-medium text-fg-link"
                            >
                                {t('workspace.billing.invoices.download')}
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            {state === 'ready' && paymentsWithoutDocument > 0 && (
                <p role="status" className="text-body font-medium text-fg-warning">
                    {t('workspace.billing.invoices.missingDocuments', {
                        count: String(paymentsWithoutDocument),
                    })}
                </p>
            )}

            {!earchiveConfigured && (
                <p className="text-body text-fg-muted">
                    {t('workspace.billing.invoices.earchiveNotConfigured')}
                </p>
            )}
        </section>
    );
}

export default InvoiceListPanel;
