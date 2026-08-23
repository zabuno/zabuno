import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../i18n/platform';
import { PlanActivationDialog } from './plans/PlanActivationDialog';
import { PlanForm, type PlanCreatePayload } from './plans/PlanForm';
import { PlanList, type Plan } from './plans/PlanList';

const PLANS_ENDPOINT = '/api/admin/plans';
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';
const CURRENCY_PATTERN = /^[A-Z]{3}$/;

function isNonNegativeInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0;
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

    if (!isNonNegativeInteger(candidate.version)) {
        return false;
    }

    if (!Array.isArray(candidate.entitlements) || !candidate.entitlements.every((item) => typeof item === 'string')) {
        return false;
    }

    const amountValid = candidate.amount_minor === null || isNonNegativeInteger(candidate.amount_minor);
    const currencyValid = candidate.currency === null || (typeof candidate.currency === 'string' && CURRENCY_PATTERN.test(candidate.currency));

    if (!amountValid || !currencyValid) {
        return false;
    }

    if ((candidate.amount_minor === null) !== (candidate.currency === null)) {
        return false;
    }

    if (!isNonNegativeInteger(candidate.sort_order)) {
        return false;
    }

    return typeof candidate.is_active === 'boolean';
}

function isValidPlanList(value: unknown): value is Plan[] {
    return Array.isArray(value) && value.every(isValidPlan);
}

function readXsrfCookie(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function bootstrapCsrfCookie(): Promise<void> {
    const response = await fetch(CSRF_ENDPOINT, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('CSRF bootstrap failed');
    }
}

function mutationInit(method: string, body: unknown): RequestInit {
    const headers = new Headers({ Accept: 'application/json', 'Content-Type': 'application/json' });
    const xsrfToken = readXsrfCookie();
    if (xsrfToken) {
        headers.set('X-XSRF-TOKEN', xsrfToken);
    }

    return {
        method,
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(body),
    };
}

type ListStatus = 'loading' | 'error' | 'success';
type Announcement = { kind: 'success' | 'error'; message: string } | null;

export function PlanManagementPage() {
    const [status, setStatus] = useState<ListStatus>('loading');
    const [plans, setPlans] = useState<Plan[]>([]);
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState<string | null>(null);
    const [activationTarget, setActivationTarget] = useState<Plan | null>(null);
    const [activating, setActivating] = useState(false);
    const [announcement, setAnnouncement] = useState<Announcement>(null);
    const requestRef = useRef(0);

    const fetchPlans = useCallback(async () => {
        const requestId = ++requestRef.current;
        setStatus('loading');

        try {
            const response = await fetch(PLANS_ENDPOINT, {
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
    }, []);

    useEffect(() => {
        void (async () => {
            await fetchPlans();
        })();
    }, [fetchPlans]);

    const handleCreate = useCallback(
        async (payload: PlanCreatePayload) => {
            setCreating(true);
            setCreateError(null);

            try {
                await bootstrapCsrfCookie();
                const response = await fetch(PLANS_ENDPOINT, mutationInit('POST', payload));

                if (!response.ok) {
                    setCreateError(t('platform.plans.form.error'));
                    return;
                }

                await fetchPlans();
            } catch {
                setCreateError(t('platform.plans.form.error'));
            } finally {
                setCreating(false);
            }
        },
        [fetchPlans],
    );

    const handleActivateConfirm = useCallback(async () => {
        if (!activationTarget) {
            return;
        }

        const target = activationTarget;
        setActivating(true);

        try {
            await bootstrapCsrfCookie();
            const response = await fetch(`${PLANS_ENDPOINT}/${target.id}/activate`, mutationInit('POST', {}));

            if (!response.ok) {
                setAnnouncement({ kind: 'error', message: t('platform.plans.activate.error') });
                return;
            }

            await fetchPlans();
            setActivationTarget(null);
            setAnnouncement({ kind: 'success', message: t('platform.plans.activate.success') });
        } catch {
            setAnnouncement({ kind: 'error', message: t('platform.plans.activate.error') });
        } finally {
            setActivating(false);
        }
    }, [activationTarget, fetchPlans]);

    return (
        <div className="flex flex-col gap-6" style={{ maxWidth: '100%' }}>
            <PlanList status={status} plans={plans} onRetry={() => void fetchPlans()} onActivateRequest={setActivationTarget} />

            <PlanForm onSubmit={(payload) => void handleCreate(payload)} submitting={creating} />

            {createError && (
                <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                    {createError}
                </p>
            )}

            {activationTarget && (
                <PlanActivationDialog
                    plan={activationTarget}
                    confirming={activating}
                    onCancel={() => setActivationTarget(null)}
                    onConfirm={() => void handleActivateConfirm()}
                />
            )}

            <p role="status" aria-live="polite" aria-label={t('platform.plans.activate.dialog.heading')} className="sr-only">
                {announcement?.message ?? ''}
            </p>
        </div>
    );
}

export default PlanManagementPage;
