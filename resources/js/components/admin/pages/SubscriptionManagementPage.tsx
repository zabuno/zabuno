import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../i18n/platform';
import { WorkspaceDiscovery, type Workspace } from './subscriptions/WorkspaceDiscovery';
import {
    CurrentSubscriptionCard,
    type Subscription,
    type CurrentSubscriptionStatus,
} from './subscriptions/CurrentSubscriptionCard';
import { ManualPaymentForm, type ManualPaymentFormValues } from './subscriptions/ManualPaymentForm';
import { ManualPaymentConfirmDialog } from './subscriptions/ManualPaymentConfirmDialog';
import type { Plan } from './plans/PlanList';

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

    if (
        !Array.isArray(candidate.entitlements) ||
        !candidate.entitlements.every((item) => typeof item === 'string')
    ) {
        return false;
    }

    const amountValid =
        candidate.amount_minor === null || isNonNegativeInteger(candidate.amount_minor);
    const currencyValid =
        candidate.currency === null ||
        (typeof candidate.currency === 'string' && CURRENCY_PATTERN.test(candidate.currency));

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

function isValidSubscription(value: unknown): value is Subscription {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    if (candidate.state === 'none') {
        return true;
    }

    if (candidate.state !== 'active') {
        return false;
    }

    return (
        typeof candidate.plan_id === 'number' &&
        typeof candidate.plan_code === 'string' &&
        candidate.plan_code.length > 0 &&
        typeof candidate.plan_name === 'string' &&
        candidate.plan_name.length > 0 &&
        typeof candidate.plan_version === 'number' &&
        typeof candidate.ends_at === 'string' &&
        candidate.ends_at.length > 0
    );
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

type PlansStatus = 'loading' | 'error' | 'success';
type FormField = 'endsAt' | 'paymentNote' | 'documentReference';

/**
 * S1-WP01A platform manual-payment page: discovers real workspaces, loads
 * active plan versions, fetches the selected workspace's authoritative
 * subscription, and requires a distinct human confirmation before recording
 * a manual payment. Every value shown comes from a mocked/real network
 * response — nothing here is invented.
 */
export function SubscriptionManagementPage() {
    const [selectedWorkspace, setSelectedWorkspace] = useState<Workspace | null>(null);

    const [plansStatus, setPlansStatus] = useState<PlansStatus>('loading');
    const [activePlans, setActivePlans] = useState<Plan[]>([]);

    const [subscriptionStatus, setSubscriptionStatus] =
        useState<CurrentSubscriptionStatus>('loading');
    const [subscription, setSubscription] = useState<Subscription | null>(null);
    const subscriptionRequestRef = useRef(0);

    const [endsAt, setEndsAt] = useState('');
    const [paymentNote, setPaymentNote] = useState('');
    const [documentReference, setDocumentReference] = useState('');

    const [pendingValues, setPendingValues] = useState<ManualPaymentFormValues | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID());

    const fetchActivePlans = useCallback(async () => {
        setPlansStatus('loading');

        try {
            const response = await fetch(PLANS_ENDPOINT, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setPlansStatus('error');
                return;
            }

            const body: unknown = await response.json();

            if (!isValidPlanList(body)) {
                setPlansStatus('error');
                return;
            }

            setActivePlans(body.filter((plan) => plan.is_active));
            setPlansStatus('success');
        } catch {
            setPlansStatus('error');
        }
    }, []);

    useEffect(() => {
        void (async () => {
            await fetchActivePlans();
        })();
    }, [fetchActivePlans]);

    const fetchSubscription = useCallback(async (workspaceId: number): Promise<boolean> => {
        const requestId = ++subscriptionRequestRef.current;
        setSubscriptionStatus('loading');

        try {
            const response = await fetch(`/api/admin/workspaces/${workspaceId}/subscription`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (subscriptionRequestRef.current !== requestId) {
                return false;
            }

            if (!response.ok) {
                setSubscriptionStatus('error');
                return false;
            }

            const body: unknown = await response.json();

            if (subscriptionRequestRef.current !== requestId) {
                return false;
            }

            if (!isValidSubscription(body)) {
                setSubscriptionStatus('error');
                return false;
            }

            setSubscription(body);
            setSubscriptionStatus('success');
            return true;
        } catch {
            if (subscriptionRequestRef.current === requestId) {
                setSubscriptionStatus('error');
            }
            return false;
        }
    }, []);

    function handleWorkspaceSelect(workspace: Workspace) {
        setSelectedWorkspace(workspace);
        setSubscription(null);
        setSubmitError(null);
        setSuccessMessage(null);
        void fetchSubscription(workspace.id);
    }

    function handleFieldChange(field: FormField, value: string) {
        if (field === 'endsAt') setEndsAt(value);
        if (field === 'paymentNote') setPaymentNote(value);
        if (field === 'documentReference') setDocumentReference(value);
    }

    function handleFormSubmit(values: ManualPaymentFormValues) {
        setSubmitError(null);
        setPendingValues(values);
        setDialogOpen(true);
    }

    const handleConfirm = useCallback(async () => {
        if (!pendingValues || !selectedWorkspace) {
            return;
        }

        setSubmitting(true);

        try {
            await bootstrapCsrfCookie();
            const response = await fetch(
                `/api/admin/workspaces/${selectedWorkspace.id}/manual-payments`,
                mutationInit('POST', {
                    plan_id: pendingValues.planId,
                    ends_at: pendingValues.endsAt,
                    payment_note: pendingValues.paymentNote,
                    document_reference: pendingValues.documentReference,
                    idempotency_key: idempotencyKey,
                }),
            );

            if (!response.ok) {
                setSubmitError(t('platform.subscriptions.form.error'));
                setDialogOpen(false);
                return;
            }

            const refetched = await fetchSubscription(selectedWorkspace.id);

            setDialogOpen(false);

            if (refetched) {
                setPendingValues(null);
                setEndsAt('');
                setPaymentNote('');
                setDocumentReference('');
                setIdempotencyKey(crypto.randomUUID());
                setSubmitError(null);
                setSuccessMessage(t('platform.subscriptions.success'));
            }
        } catch {
            setSubmitError(t('platform.subscriptions.form.error'));
            setDialogOpen(false);
        } finally {
            setSubmitting(false);
        }
    }, [pendingValues, selectedWorkspace, idempotencyKey, fetchSubscription]);

    const pendingPlan = pendingValues
        ? activePlans.find((plan) => plan.id === pendingValues.planId)
        : undefined;

    return (
        <div className="flex flex-col gap-6" style={{ maxWidth: '100%' }}>
            <WorkspaceDiscovery
                selectedWorkspace={selectedWorkspace}
                onSelect={handleWorkspaceSelect}
            />

            {selectedWorkspace && (
                <>
                    <CurrentSubscriptionCard
                        status={subscriptionStatus}
                        subscription={subscription}
                        onRetry={() => void fetchSubscription(selectedWorkspace.id)}
                    />

                    {plansStatus === 'error' && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('platform.subscriptions.plans.error')}
                        </p>
                    )}

                    {plansStatus === 'success' && activePlans.length === 0 && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('platform.subscriptions.plans.blocked')}
                        </p>
                    )}

                    {plansStatus === 'success' && activePlans.length > 0 && (
                        <ManualPaymentForm
                            activePlans={activePlans}
                            endsAt={endsAt}
                            paymentNote={paymentNote}
                            documentReference={documentReference}
                            onFieldChange={handleFieldChange}
                            onSubmit={handleFormSubmit}
                        />
                    )}

                    {submitError && (
                        <div className="flex flex-col gap-2">
                            <p role="alert" className="text-body font-medium text-fg-danger">
                                {submitError}
                            </p>
                            <button
                                type="button"
                                className="self-start text-body font-medium text-fg-danger"
                                onClick={() => setDialogOpen(true)}
                            >
                                {t('platform.subscriptions.form.retry')}
                            </button>
                        </div>
                    )}

                    {dialogOpen && pendingValues && (
                        <ManualPaymentConfirmDialog
                            planLabel={
                                pendingPlan ? `${pendingPlan.name} (${pendingPlan.code})` : ''
                            }
                            endsAt={pendingValues.endsAt}
                            confirming={submitting}
                            onCancel={() => setDialogOpen(false)}
                            onConfirm={() => void handleConfirm()}
                        />
                    )}
                </>
            )}

            <p
                role="status"
                aria-live="polite"
                aria-label={t('platform.subscriptions.success.region.label')}
                className={successMessage ? 'text-body text-fg-success' : 'sr-only'}
            >
                {successMessage ?? ''}
            </p>
        </div>
    );
}

export default SubscriptionManagementPage;
