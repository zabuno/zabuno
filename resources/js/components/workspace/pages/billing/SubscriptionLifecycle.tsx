import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { classifyResponse, networkFailure } from '../../../../lib/requestFailure';
import { messageForFailure } from '../../forms/failureMessage';
import {
    SubscriptionLifecyclePanel,
    type ChangePreview,
    type DowngradeTarget,
    type LifecyclePhase,
    type LifecycleSubscription,
} from './SubscriptionLifecyclePanel';
import type { CatalogPlansState } from './SubscribeCheckout';

type SubscriptionLifecycleProps = {
    workspaceId: number;
    /** Plan kataloğu SAYFADAN gelir: aynı liste üçüncü kez indirilmez. */
    plans: CatalogPlansState;
    /** Bir yazma başarılı olduğunda sayfanın geri kalanı da tazelensin. */
    onChanged?: () => void;
};

const PHASES: ReadonlyArray<LifecyclePhase> = [
    'none',
    'active',
    'cancelling',
    'grace',
    'suspended',
    'ended',
];

function isPhase(value: unknown): value is LifecyclePhase {
    return typeof value === 'string' && (PHASES as ReadonlyArray<string>).includes(value);
}

function readSubscription(body: unknown): LifecycleSubscription | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    const candidate = body as Record<string, unknown>;

    /*
        ABONELİĞİ OLMAYAN ÇALIŞMA ALANININ CEVABI TAM OLARAK {state:'none'};
        sunucu oraya `phase` yazmaz ve bu sözleşme dondurulmuştur. Evre
        burada UYDURULMAZ, "yok"tan türetilir.
    */
    if (candidate.state === 'none') {
        return {
            phase: 'none',
            plan_name: null,
            ends_at: null,
            grace_ends_at: null,
            scheduled_plan_name: null,
        };
    }

    if (candidate.state !== 'active' || !isPhase(candidate.phase)) {
        return null;
    }

    return {
        phase: candidate.phase,
        plan_name: typeof candidate.plan_name === 'string' ? candidate.plan_name : null,
        ends_at: typeof candidate.ends_at === 'string' ? candidate.ends_at : null,
        grace_ends_at: typeof candidate.grace_ends_at === 'string' ? candidate.grace_ends_at : null,
        scheduled_plan_name:
            typeof candidate.scheduled_plan_name === 'string'
                ? candidate.scheduled_plan_name
                : null,
    };
}

function readPreview(body: unknown): ChangePreview | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    const candidate = body as Record<string, unknown>;

    if (
        typeof candidate.target_plan_id !== 'number' ||
        typeof candidate.target_plan_name !== 'string' ||
        typeof candidate.effective_at !== 'string' ||
        !Array.isArray(candidate.losing)
    ) {
        return null;
    }

    const losing = candidate.losing.filter(
        (entry): entry is { key: string; label: string } =>
            typeof entry === 'object' &&
            entry !== null &&
            typeof (entry as { key?: unknown }).key === 'string' &&
            typeof (entry as { label?: unknown }).label === 'string',
    );

    return {
        target_plan_id: candidate.target_plan_id,
        target_plan_name: candidate.target_plan_name,
        effective_at: candidate.effective_at,
        losing,
    };
}

/**
 * ABONELİĞİN EKSİK YARISI — KAP (`docs/107` Faz 1.3, `docs/134`).
 *
 * Dört yazma ucunu (iptal, cayma, düşürmeyi zamanlama, düşürmeden vazgeçme)
 * ve bir sorgu ucunu (düşürme önizlemesi) sürer. HİÇBİRİNİ UYDURMAZ: ne
 * kaybedileceğini de, yürürlük tarihini de sunucu söyler — arayüzde ikinci
 * bir yetenek listesi ya da ikinci bir tarih hesabı yoktur.
 *
 * Düşürme hedefleri sayfanın kataloğundan SÜZÜLÜR (bugünkü plandan ucuz
 * olanlar); yükseltme burada hiç görünmez çünkü onun yolu ödemedir.
 */
export function SubscriptionLifecycle({
    workspaceId,
    plans,
    onChanged,
}: SubscriptionLifecycleProps) {
    const [status, setStatus] = useState<'loading' | 'error' | 'ready'>('loading');
    const [subscription, setSubscription] = useState<LifecycleSubscription | null>(null);
    const [currentPlanId, setCurrentPlanId] = useState<number | null>(null);
    const [selectedTargetId, setSelectedTargetId] = useState<number | null>(null);
    const [preview, setPreview] = useState<ChangePreview | null>(null);
    const [previewError, setPreviewError] = useState(false);
    const [confirmingCancel, setConfirmingCancel] = useState(false);
    const [busy, setBusy] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const requestRef = useRef(0);

    const load = useCallback(async () => {
        const requestId = ++requestRef.current;
        setStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/subscription`, {
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
            const parsed = readSubscription(body);

            if (parsed === null) {
                setStatus('error');
                return;
            }

            const planId = (body as { plan_id?: unknown }).plan_id;
            setCurrentPlanId(typeof planId === 'number' ? planId : null);
            setSubscription(parsed);
            setStatus('ready');
        } catch {
            if (requestRef.current === requestId) {
                setStatus('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await load();
        })();
    }, [load]);

    /*
        Bugünkü planın fiyatı katalogdan OKUNUR, saklanmaz: iki kaynaktan
        türeyen bir değeri ayrıca duruma yazmak, katalog ile abonelik farklı
        anlarda geldiğinde bir render boyunca yanlış listeyi göstermek olurdu.
        Düşürme hedefleri ondan ucuz olan planlardır; fiyat bilinmiyorsa
        hiçbir hedef gösterilmez — bir düşürme listesi TAHMİNLE kurulmaz.
    */
    const currentAmount =
        currentPlanId === null || plans.state !== 'success'
            ? null
            : (plans.items.find((plan) => plan.id === currentPlanId)?.amount_minor ?? null);

    const downgradeTargets: DowngradeTarget[] =
        currentAmount === null
            ? []
            : plans.items
                  .filter(
                      (plan) =>
                          plan.id !== currentPlanId &&
                          plan.amount_minor !== null &&
                          plan.amount_minor < currentAmount,
                  )
                  .map((plan) => ({ id: plan.id, name: plan.name }));

    const selectTarget = useCallback(
        async (planId: number) => {
            setSelectedTargetId(planId);
            setPreview(null);
            setPreviewError(false);

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/subscription/plan-change?plan_id=${planId}`,
                    { credentials: 'same-origin', headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    setPreviewError(true);
                    return;
                }

                const parsed = readPreview(await response.json());

                if (parsed === null) {
                    setPreviewError(true);
                    return;
                }

                setPreview(parsed);
            } catch {
                setPreviewError(true);
            }
        },
        [workspaceId],
    );

    const mutate = useCallback(
        async (path: string, method: 'POST' | 'DELETE', body: unknown, errorText: string) => {
            if (busy) {
                return;
            }

            setBusy(true);
            setActionError(null);

            try {
                await bootstrapCsrfCookie();

                const init = buildAuthRequestInit({
                    method,
                    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
                });
                const headers = new Headers(init.headers);
                headers.set('Content-Type', 'application/json');

                const response = await fetch(`/api/workspaces/${workspaceId}/${path}`, {
                    ...init,
                    headers,
                });

                if (!response.ok) {
                    setActionError(
                        response.status === 422
                            ? errorText
                            : messageForFailure(classifyResponse(response)),
                    );
                    return;
                }

                setConfirmingCancel(false);
                setSelectedTargetId(null);
                setPreview(null);
                await load();
                onChanged?.();
            } catch {
                setActionError(messageForFailure(networkFailure()));
            } finally {
                setBusy(false);
            }
        },
        [busy, workspaceId, load, onChanged],
    );

    return (
        <SubscriptionLifecyclePanel
            status={status}
            subscription={subscription}
            downgradeTargets={downgradeTargets}
            selectedTargetId={selectedTargetId}
            onSelectTarget={(planId) => void selectTarget(planId)}
            preview={preview}
            previewError={previewError}
            confirmingCancel={confirmingCancel}
            onAskCancel={() => setConfirmingCancel(true)}
            onDismissCancel={() => setConfirmingCancel(false)}
            onConfirmCancel={() =>
                void mutate(
                    'subscription/cancellation',
                    'POST',
                    undefined,
                    t('workspace.billing.lifecycle.cancel.error'),
                )
            }
            onResume={() =>
                void mutate(
                    'subscription/cancellation',
                    'DELETE',
                    undefined,
                    t('workspace.billing.lifecycle.resume.error'),
                )
            }
            onScheduleChange={() => {
                if (preview !== null) {
                    void mutate(
                        'subscription/plan-change',
                        'POST',
                        { plan_id: preview.target_plan_id },
                        t('workspace.billing.lifecycle.change.error'),
                    );
                }
            }}
            onWithdrawChange={() =>
                void mutate(
                    'subscription/plan-change',
                    'DELETE',
                    undefined,
                    t('workspace.billing.lifecycle.scheduled.error'),
                )
            }
            busy={busy}
            actionError={actionError}
            onRetry={() => void load()}
        />
    );
}

export default SubscriptionLifecycle;
