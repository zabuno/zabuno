import { useCallback, useEffect, useRef, useState } from 'react';

import { InvoiceListPanel, type InvoiceRecord } from './InvoiceListPanel';

type InvoicePayload = {
    invoices: InvoiceRecord[];
    payments_without_document: number;
    earchive: { configured: boolean; state: string };
};

type WorkspaceInvoicesProps = {
    workspaceId: number;
};

function isInvoiceRecord(value: unknown): value is InvoiceRecord {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;
    const seller = candidate.seller as Record<string, unknown> | undefined;

    return (
        typeof candidate.id === 'number' &&
        (candidate.kind === 'invoice' || candidate.kind === 'credit_note') &&
        typeof candidate.document_number === 'string' &&
        typeof candidate.issued_at === 'string' &&
        typeof candidate.currency === 'string' &&
        typeof candidate.amount_minor === 'number' &&
        typeof candidate.plan_name === 'string' &&
        typeof candidate.period_days === 'number' &&
        typeof candidate.document_url === 'string' &&
        typeof seller === 'object' &&
        seller !== null &&
        typeof seller.complete === 'boolean' &&
        Array.isArray(seller.missing)
    );
}

function isInvoicePayload(value: unknown): value is InvoicePayload {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;
    const earchive = candidate.earchive as Record<string, unknown> | undefined;

    return (
        Array.isArray(candidate.invoices) &&
        candidate.invoices.every(isInvoiceRecord) &&
        typeof candidate.payments_without_document === 'number' &&
        typeof earchive === 'object' &&
        earchive !== null &&
        typeof earchive.configured === 'boolean'
    );
}

/**
 * Faturaların KAP katmanı: sunucuyu okur, `InvoiceListPanel`'e verir
 * (docs/130 §K6).
 *
 * Yazma kontrolü yoktur ve olmayacaktır — belge yalnız gerçek bir
 * tahsilatla doğar. Sunucudan gelen gövde biçimine güvenilmez: tanınmayan
 * bir cevap "hata"dır, yarım çizilmiş bir belge listesi değil.
 */
export function WorkspaceInvoices({ workspaceId }: WorkspaceInvoicesProps) {
    const [state, setState] = useState<'loading' | 'error' | 'ready'>('loading');
    const [payload, setPayload] = useState<InvoicePayload | null>(null);
    const requestRef = useRef(0);

    const fetchInvoices = useCallback(async () => {
        const requestId = ++requestRef.current;
        setState('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/invoices`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (requestRef.current !== requestId) {
                return;
            }

            if (!response.ok) {
                setState('error');
                return;
            }

            const body: unknown = await response.json();

            if (!isInvoicePayload(body)) {
                setState('error');
                return;
            }

            setPayload(body);
            setState('ready');
        } catch {
            if (requestRef.current === requestId) {
                setState('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await fetchInvoices();
        })();
    }, [fetchInvoices]);

    return (
        <InvoiceListPanel
            state={state}
            invoices={payload?.invoices ?? []}
            paymentsWithoutDocument={payload?.payments_without_document ?? 0}
            earchiveConfigured={payload?.earchive.configured ?? false}
            onRetry={() => {
                void fetchInvoices();
            }}
        />
    );
}

export default WorkspaceInvoices;
