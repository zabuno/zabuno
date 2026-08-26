import { formatMoneyOr } from '../../../../money/format';
import { useCallback, useEffect, useRef, useState } from 'react';

export type Plan = {
    id: number;
    name: string;
    code: string;
    entitlements: string[];
    amount_minor: number | null;
    currency: string | null;
};

type PlanCatalogStatus = 'loading' | 'error' | 'success';

type PlanCatalogProps = {
    workspaceId: number;
    label: string;
    loadingText: string;
    emptyText: string;
    errorText: string;
    retryText: string;
    priceUnavailableText: string;
};

const CURRENCY_PATTERN = /^[A-Z]{3}$/;

function isNonNegativeInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}

function isValidCurrency(value: unknown): value is string {
    return typeof value === 'string' && CURRENCY_PATTERN.test(value);
}

function isValidPlan(value: unknown): value is Plan {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    if (!(typeof candidate.id === 'number' && Number.isInteger(candidate.id) && candidate.id > 0)) {
        return false;
    }

    if (typeof candidate.name !== 'string' || candidate.name.length === 0) {
        return false;
    }

    if (typeof candidate.code !== 'string' || candidate.code.length === 0) {
        return false;
    }

    if (
        !Array.isArray(candidate.entitlements) ||
        !candidate.entitlements.every((item) => typeof item === 'string')
    ) {
        return false;
    }

    const amountValid =
        candidate.amount_minor === null || isNonNegativeInteger(candidate.amount_minor);
    const currencyValid = candidate.currency === null || isValidCurrency(candidate.currency);

    if (!amountValid || !currencyValid) {
        return false;
    }

    const amountPresent = candidate.amount_minor !== null;
    const currencyPresent = candidate.currency !== null;

    return amountPresent === currencyPresent;
}

function isValidPlanList(value: unknown): value is Plan[] {
    return Array.isArray(value) && value.every(isValidPlan);
}

function derivePriceLabel(plan: Plan, priceUnavailableText: string): string {
    if (!isNonNegativeInteger(plan.amount_minor) || !isValidCurrency(plan.currency)) {
        return priceUnavailableText;
    }

    // Biçimlendirme CORE-12'ye aittir; burada tekrar edilmez (docs/13 §4).
    return formatMoneyOr(plan.amount_minor, plan.currency, priceUnavailableText);
}

/**
 * Server-authoritative plan catalog: GET /api/workspaces/{workspaceId}/plans
 * on mount, same-origin credentials, honest loading/empty/error/success
 * states — never a fabricated plan name, code, entitlement, or price.
 */
export function PlanCatalog({
    workspaceId,
    label,
    loadingText,
    emptyText,
    errorText,
    retryText,
    priceUnavailableText,
}: PlanCatalogProps) {
    const [status, setStatus] = useState<PlanCatalogStatus>('loading');
    const [plans, setPlans] = useState<Plan[]>([]);
    const requestRef = useRef(0);

    const fetchPlans = useCallback(async () => {
        const requestId = ++requestRef.current;
        setStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/plans`, {
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

            if (!isValidPlanList(body)) {
                setStatus('error');

                return;
            }

            setPlans(body);
            setStatus('success');
        } catch {
            if (requestRef.current === requestId) {
                setStatus('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await fetchPlans();
        })();
    }, [fetchPlans]);

    return (
        <div role="region" aria-label={label} className="flex flex-col gap-3">
            <p className="text-sm font-semibold text-fg">{label}</p>

            {status === 'loading' && (
                <p role="status" className="text-sm text-fg-muted">
                    {loadingText}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {errorText}
                    </p>
                    <button
                        type="button"
                        className="self-start text-sm font-medium text-fg-danger"
                        onClick={() => void fetchPlans()}
                    >
                        {retryText}
                    </button>
                </div>
            )}

            {status === 'success' && plans.length === 0 && (
                <p role="status" className="text-sm text-fg-muted">
                    {emptyText}
                </p>
            )}

            {status === 'success' && plans.length > 0 && (
                <ul
                    className="grid gap-4"
                    style={{
                        gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 16rem), 1fr))',
                    }}
                >
                    {plans.map((plan) => (
                        <li
                            key={plan.id}
                            className="flex flex-col gap-2 rounded-lg border border-border p-3 text-sm text-fg-secondary"
                        >
                            <span className="font-medium text-fg">{plan.name}</span>
                            <span className="text-fg-muted">{plan.code}</span>
                            <ul className="flex flex-col gap-1">
                                {plan.entitlements.map((entitlement) => (
                                    <li key={entitlement} className="text-fg-secondary">
                                        {entitlement}
                                    </li>
                                ))}
                            </ul>
                            <span className="font-medium text-fg">
                                {derivePriceLabel(plan, priceUnavailableText)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default PlanCatalog;
