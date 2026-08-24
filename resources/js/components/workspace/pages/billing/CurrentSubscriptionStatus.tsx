import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../../i18n/workspace';

type Subscription =
    | { state: 'none' }
    | {
          state: 'active';
          plan_code: string;
          plan_name: string;
          plan_version: number;
          ends_at: string;
      };

type Status = 'loading' | 'error' | 'success';

type CurrentSubscriptionStatusProps = {
    workspaceId: number;
};

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
        typeof candidate.plan_code === 'string' &&
        candidate.plan_code.length > 0 &&
        typeof candidate.plan_name === 'string' &&
        candidate.plan_name.length > 0 &&
        typeof candidate.plan_version === 'number' &&
        typeof candidate.ends_at === 'string' &&
        candidate.ends_at.length > 0
    );
}

/**
 * Read-only, server-authoritative "Current plan" region: GET
 * /api/workspaces/{workspaceId}/subscription on mount, honest
 * loading/none/active/error states from the exact server plan code/name/
 * version and end date — never a fabricated value. No manual-payment
 * mutation control lives here; that is platform finance's responsibility.
 */
export function CurrentSubscriptionStatus({ workspaceId }: CurrentSubscriptionStatusProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [subscription, setSubscription] = useState<Subscription | null>(null);
    const requestRef = useRef(0);

    const fetchSubscription = useCallback(async () => {
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

            if (!isValidSubscription(body)) {
                setStatus('error');
                return;
            }

            setSubscription(body);
            setStatus('success');
        } catch {
            if (requestRef.current === requestId) {
                setStatus('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await fetchSubscription();
        })();
    }, [fetchSubscription]);

    return (
        <div
            role="region"
            aria-label={t('workspace.billing.currentPlan.region')}
            className="flex flex-col gap-3"
        >
            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.billing.currentPlan.region')}
            </p>

            {status === 'loading' && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.billing.currentPlan.loading')}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                        {t('workspace.billing.currentPlan.error')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-sm font-medium text-red-600 dark:text-red-400"
                        onClick={() => void fetchSubscription()}
                    >
                        {t('workspace.billing.currentPlan.retry')}
                    </button>
                </div>
            )}

            {status === 'success' && subscription?.state === 'none' && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.billing.currentPlan.none')}
                </p>
            )}

            {status === 'success' && subscription?.state === 'active' && (
                <div className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    <span className="font-medium text-gray-900 dark:text-white">
                        {subscription.plan_name}
                    </span>
                    <span>{subscription.plan_code}</span>
                    <span>
                        {t('workspace.billing.currentPlan.version', {
                            version: String(subscription.plan_version),
                        })}
                    </span>
                    <span>{subscription.ends_at}</span>
                </div>
            )}
        </div>
    );
}

export default CurrentSubscriptionStatus;
