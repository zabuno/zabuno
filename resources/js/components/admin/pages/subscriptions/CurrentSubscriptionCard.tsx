import { t } from '../../../../i18n/platform';

export type Subscription =
    | { state: 'none' }
    | {
          state: 'active';
          plan_id: number;
          plan_code: string;
          plan_name: string;
          plan_version: number;
          ends_at: string;
      };

export type CurrentSubscriptionStatus = 'loading' | 'error' | 'success';

type CurrentSubscriptionCardProps = {
    status: CurrentSubscriptionStatus;
    subscription: Subscription | null;
    onRetry: () => void;
};

/**
 * Micro/compound: renders the authoritative subscription state for the
 * currently selected workspace — honest loading/error/none/active, never a
 * fabricated plan or end date.
 */
export function CurrentSubscriptionCard({
    status,
    subscription,
    onRetry,
}: CurrentSubscriptionCardProps) {
    return (
        <div
            role="region"
            aria-label={t('platform.subscriptions.subscription.region.label')}
            className="flex flex-col gap-3"
        >
            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('platform.subscriptions.subscription.region.label')}
            </p>

            {status === 'loading' && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('platform.subscriptions.subscription.loading')}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                        {t('platform.subscriptions.subscription.error')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-sm font-medium text-red-600 dark:text-red-400"
                        onClick={onRetry}
                    >
                        {t('platform.subscriptions.subscription.retry')}
                    </button>
                </div>
            )}

            {status === 'success' && subscription?.state === 'none' && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('platform.subscriptions.subscription.none')}
                </p>
            )}

            {status === 'success' && subscription?.state === 'active' && (
                <div className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    <span className="font-medium text-gray-900 dark:text-white">
                        {subscription.plan_name}
                    </span>
                    <span>{subscription.plan_code}</span>
                    <span>{t('platform.subscriptions.subscription.active.label')}</span>
                    <span>{subscription.ends_at}</span>
                </div>
            )}
        </div>
    );
}

export default CurrentSubscriptionCard;
