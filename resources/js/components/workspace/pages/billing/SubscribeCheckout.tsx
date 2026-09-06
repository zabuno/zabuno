import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { classifyResponse, networkFailure } from '../../../../lib/requestFailure';
import { messageForFailure } from '../../forms/failureMessage';
import { BillingProfileForm } from './BillingProfileForm';
import {
    CheckoutPanel,
    type BillingProfileData,
    type CheckoutProfileState,
    type LatestTransaction,
    type PurchasablePlan,
} from './CheckoutPanel';

export type CatalogPlansState = {
    state: 'loading' | 'error' | 'success';
    items: ReadonlyArray<{
        id: number;
        name: string;
        amount_minor: number | null;
        currency: string | null;
    }>;
};

type SubscribeCheckoutProps = {
    workspaceId: number;
    /** Plan kataloğu SAYFADAN gelir: aynı liste ikinci kez indirilmez. */
    plans: CatalogPlansState;
    /** Ödeme sayfasına gidiş; testler bunu yakalar, ürün tarayıcıyı yönlendirir. */
    navigateToPayment?: (url: string) => void;
};

type StatusData = {
    mode: 'sandbox' | 'live';
    period_days: number;
    profile_complete: boolean;
    latest: LatestTransaction | null;
};

const PROFILE_FIELDS = [
    'legal_name',
    'tax_number',
    'tax_office',
    'address',
    'city',
    'country',
    'email',
    'phone',
] as const;

function isPurchasablePlan(value: unknown): value is PurchasablePlan {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate.id === 'number' &&
        Number.isInteger(candidate.id) &&
        candidate.id > 0 &&
        typeof candidate.name === 'string' &&
        candidate.name.length > 0 &&
        typeof candidate.amount_minor === 'number' &&
        Number.isInteger(candidate.amount_minor) &&
        candidate.amount_minor >= 0 &&
        typeof candidate.currency === 'string' &&
        /^[A-Z]{3}$/.test(candidate.currency)
    );
}

function isProfileData(value: unknown): value is BillingProfileData {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return PROFILE_FIELDS.every((field) => typeof candidate[field] === 'string');
}

function isLatest(value: unknown): value is LatestTransaction {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate.id === 'number' &&
        (candidate.state === 'initiated' ||
            candidate.state === 'succeeded' ||
            candidate.state === 'failed' ||
            candidate.state === 'refunded') &&
        (candidate.failure_reason === null || typeof candidate.failure_reason === 'string') &&
        (candidate.redirect_url === null || typeof candidate.redirect_url === 'string')
    );
}

function isStatusData(value: unknown): value is StatusData {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        (candidate.mode === 'sandbox' || candidate.mode === 'live') &&
        typeof candidate.period_days === 'number' &&
        typeof candidate.profile_complete === 'boolean' &&
        (candidate.latest === null || isLatest(candidate.latest))
    );
}

function isHttpsUrl(value: unknown): value is string {
    if (typeof value !== 'string') {
        return false;
    }

    try {
        return new URL(value).protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * Kendi kendine abonelik — KAP (docs/107 Faz 1.1 + 1.3, docs/123).
 *
 * Üç şeyi sunucudan okur (planlar, fatura profili, ödeme durumu), hiçbirini
 * uydurmaz; "Proceed to payment" yalnız açık bir insan tıklamasıyla POST
 * atar ve gövde yalnız plan_id + idempotency_key taşır. Tutar hiçbir zaman
 * istemciden gitmez. 202'deki adres https değilse yönlendirme yapılmaz.
 */
export function SubscribeCheckout({
    workspaceId,
    plans,
    navigateToPayment = (url) => window.location.assign(url),
}: SubscribeCheckoutProps) {
    const [profile, setProfile] = useState<CheckoutProfileState>({ state: 'loading' });
    const [statusState, setStatusState] = useState<'loading' | 'error' | 'ready'>('loading');
    const [status, setStatus] = useState<StatusData | null>(null);
    const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
    const [profileFormOpen, setProfileFormOpen] = useState(false);
    const [proceeding, setProceeding] = useState(false);
    const [proceedError, setProceedError] = useState<string | null>(null);
    const requestRef = useRef(0);

    const load = useCallback(async () => {
        const requestId = ++requestRef.current;
        setProfile({ state: 'loading' });
        setStatusState('loading');

        const get = (path: string) =>
            fetch(`/api/workspaces/${workspaceId}/${path}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

        const [profileResult, statusResult] = await Promise.allSettled([
            get('billing-profile'),
            get('checkout'),
        ]);

        if (requestRef.current !== requestId) {
            return;
        }

        try {
            if (profileResult.status === 'fulfilled' && profileResult.value.ok) {
                const body: unknown = await profileResult.value.json();
                const candidate = body as { state?: unknown } | null;

                if (candidate?.state === 'missing') {
                    setProfile({ state: 'missing' });
                } else if (candidate?.state === 'complete' && isProfileData(body)) {
                    setProfile({ state: 'complete', profile: body });
                } else {
                    setProfile({ state: 'error' });
                }
            } else {
                setProfile({ state: 'error' });
            }
        } catch {
            setProfile({ state: 'error' });
        }

        try {
            if (statusResult.status === 'fulfilled' && statusResult.value.ok) {
                const body: unknown = await statusResult.value.json();

                if (isStatusData(body)) {
                    setStatus(body);
                    setStatusState('ready');
                } else {
                    setStatusState('error');
                }
            } else {
                setStatusState('error');
            }
        } catch {
            setStatusState('error');
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await load();
        })();
    }, [load]);

    const proceed = useCallback(async () => {
        if (proceeding || selectedPlanId === null) {
            return;
        }

        setProceeding(true);
        setProceedError(null);

        try {
            await bootstrapCsrfCookie();

            const init = buildAuthRequestInit({
                method: 'POST',
                body: JSON.stringify({
                    plan_id: selectedPlanId,
                    idempotency_key: crypto.randomUUID(),
                }),
            });
            const headers = new Headers(init.headers);
            headers.set('Content-Type', 'application/json');

            const response = await fetch(`/api/workspaces/${workspaceId}/checkout`, {
                ...init,
                headers,
            });

            if (response.status === 422) {
                const body = (await response.json()) as { reason?: unknown } | null;

                if (body?.reason === 'billing_profile_missing') {
                    setProfile({ state: 'missing' });
                    setProfileFormOpen(true);
                    setProceedError(t('workspace.billing.checkout.profile.missing'));
                    return;
                }

                setProceedError(
                    body?.reason === 'plan_not_purchasable'
                        ? t('workspace.billing.checkout.planNotPurchasable')
                        : messageForFailure(classifyResponse(response)),
                );
                return;
            }

            if (!response.ok) {
                setProceedError(messageForFailure(classifyResponse(response)));
                return;
            }

            const body = (await response.json()) as { redirect_url?: unknown } | null;

            if (!isHttpsUrl(body?.redirect_url)) {
                setProceedError(t('workspace.billing.checkout.invalidRedirect'));
                return;
            }

            navigateToPayment(body.redirect_url);
        } catch {
            setProceedError(messageForFailure(networkFailure()));
        } finally {
            setProceeding(false);
        }
    }, [proceeding, selectedPlanId, workspaceId, navigateToPayment]);

    // Yalnız FİYATI olan plan satın alınabilir; fiyatsız plan seçilemez.
    const purchasable: PurchasablePlan[] = plans.items.filter(isPurchasablePlan);

    return (
        <CheckoutPanel
            mode={status?.mode ?? null}
            periodDays={status?.period_days ?? 30}
            plans={{ state: plans.state === 'success' ? 'ready' : plans.state, items: purchasable }}
            selectedPlanId={selectedPlanId}
            onSelectPlan={setSelectedPlanId}
            profile={profile}
            profileFormOpen={profileFormOpen}
            profileForm={
                <BillingProfileForm
                    workspaceId={workspaceId}
                    initial={profile.state === 'complete' ? profile.profile : null}
                    onSaved={(saved) => {
                        setProfile({ state: 'complete', profile: saved });
                        setProfileFormOpen(false);
                        setProceedError(null);
                    }}
                    onCancel={() => setProfileFormOpen(false)}
                />
            }
            onOpenProfileForm={() => setProfileFormOpen(true)}
            statusState={statusState}
            latest={status?.latest ?? null}
            proceeding={proceeding}
            proceedError={proceedError}
            onProceed={() => void proceed()}
            onRetryLoad={() => void load()}
        />
    );
}

export default SubscribeCheckout;
