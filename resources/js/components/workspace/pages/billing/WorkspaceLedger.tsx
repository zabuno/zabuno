import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from 'flowbite-react';

import { t } from '../../../../i18n/workspace';
import { formatMoneyOr } from '../../../../money/format';
import { KeyValueList } from '../../../catalog/data-display/compound/KeyValueList';
import {
    ResponsiveDataTable,
    type DataTableColumn,
} from '../../../catalog/data-display/compound/ResponsiveDataTable';

type LedgerEntry = {
    id: number;
    reference: string;
    debitAccount: string;
    creditAccount: string;
    amountMinor: number;
    currencyCode: string;
    description: string | null;
    occurredAt: string;
};

type LedgerPayload = {
    entries: LedgerEntry[];
    balances: Record<string, number>;
    currency: string | null;
};

type Status = 'loading' | 'error' | 'success';

type WorkspaceLedgerProps = {
    workspaceId: number;
};

function isLedgerEntry(value: unknown): value is LedgerEntry {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate.id === 'number' &&
        typeof candidate.reference === 'string' &&
        typeof candidate.debitAccount === 'string' &&
        typeof candidate.creditAccount === 'string' &&
        typeof candidate.amountMinor === 'number' &&
        typeof candidate.currencyCode === 'string' &&
        typeof candidate.occurredAt === 'string'
    );
}

function isLedgerPayload(value: unknown): value is LedgerPayload {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        Array.isArray(candidate.entries) &&
        candidate.entries.every(isLedgerEntry) &&
        typeof candidate.balances === 'object' &&
        candidate.balances !== null
    );
}

/**
 * Defterin okuma yüzeyi (Surface katmanı): GET
 * /api/workspaces/{workspaceId}/ledger sonucunu gösterir. Hiçbir yazma
 * kontrolü taşımaz — defter yalnız gerçek bir tahsilatla yazılır, elle
 * değil. Bu bilinçli bir kısıttır: elle düzeltilebilen bir defter, kanıt
 * değeri taşımaz.
 */
export function WorkspaceLedger({ workspaceId }: WorkspaceLedgerProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [payload, setPayload] = useState<LedgerPayload | null>(null);
    const requestRef = useRef(0);

    const fetchLedger = useCallback(async () => {
        const requestId = ++requestRef.current;
        setStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/ledger`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (requestRef.current !== requestId) {
                return;
            }

            if (!response.ok) {
                setStatus('error');
                return;
            }

            const body: unknown = await response.json();

            if (!isLedgerPayload(body)) {
                setStatus('error');
                return;
            }

            setPayload(body);
            setStatus('success');
        } catch {
            if (requestRef.current === requestId) {
                setStatus('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await fetchLedger();
        })();
    }, [fetchLedger]);

    const columns: readonly DataTableColumn<LedgerEntry>[] = [
        {
            key: 'occurredAt',
            header: t('workspace.billing.ledger.column.occurredAt'),
            render: (row) => row.occurredAt,
        },
        {
            key: 'reference',
            header: t('workspace.billing.ledger.column.reference'),
            render: (row) => row.reference,
        },
        {
            key: 'debit',
            header: t('workspace.billing.ledger.column.debit'),
            render: (row) => row.debitAccount,
        },
        {
            key: 'credit',
            header: t('workspace.billing.ledger.column.credit'),
            render: (row) => row.creditAccount,
        },
        {
            key: 'amount',
            header: t('workspace.billing.ledger.column.amount'),
            align: 'end',
            render: (row) =>
                formatMoneyOr(row.amountMinor, row.currencyCode, String(row.amountMinor)),
        },
    ];

    const balances = Object.entries(payload?.balances ?? {});

    return (
        <div
            role="region"
            aria-label={t('workspace.billing.ledger.region')}
            className="flex flex-col gap-3"
        >
            <p className="text-sm font-semibold text-fg">{t('workspace.billing.ledger.region')}</p>
            <p className="text-sm text-fg-muted">{t('workspace.billing.ledger.description')}</p>

            {status === 'error' ? (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {t('workspace.billing.ledger.error')}
                    </p>
                    <Button
                        size="sm"
                        color="light"
                        className="self-start"
                        onClick={() => {
                            void fetchLedger();
                        }}
                    >
                        {t('workspace.billing.ledger.retry')}
                    </Button>
                </div>
            ) : (
                <>
                    <ResponsiveDataTable<LedgerEntry>
                        caption={t('workspace.billing.ledger.region')}
                        columns={columns}
                        rows={payload?.entries ?? []}
                        getRowKey={(row) => String(row.id)}
                        loading={status === 'loading'}
                        emptyMessage={t('workspace.billing.ledger.empty')}
                    />

                    {balances.length > 0 && (
                        <section aria-label={t('workspace.billing.ledger.balances')}>
                            <p className="text-sm font-semibold text-fg">
                                {t('workspace.billing.ledger.balances')}
                            </p>
                            <KeyValueList
                                entries={balances.map(([account, minor]) => ({
                                    key: account,
                                    label: account,
                                    value: formatMoneyOr(
                                        minor,
                                        payload?.currency ?? 'TRY',
                                        String(minor),
                                    ),
                                }))}
                            />
                        </section>
                    )}
                </>
            )}
        </div>
    );
}

export default WorkspaceLedger;
