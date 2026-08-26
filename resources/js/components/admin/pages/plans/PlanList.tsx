import { Button } from '../../../catalog/forms/micro/Button';
import { formatMoneyOr } from '../../../../money/format';
import { t } from '../../../../i18n/platform';

export type Plan = {
    id: number;
    name: string;
    code: string;
    version: number;
    entitlements: string[];
    amount_minor: number | null;
    currency: string | null;
    sort_order: number;
    is_active: boolean;
};

export function formatPlanPrice(plan: Pick<Plan, 'amount_minor' | 'currency'>): string {
    if (plan.amount_minor === null || plan.currency === null) {
        return t('platform.plans.priceUnavailable');
    }

    // Biçimlendirme CORE-12'ye aittir; burada tekrar edilmez (docs/13 §4).
    return formatMoneyOr(plan.amount_minor, plan.currency, t('platform.plans.priceUnavailable'));
}

type PlanListStatus = 'loading' | 'error' | 'success';

type PlanListProps = {
    status: PlanListStatus;
    plans: Plan[];
    onRetry: () => void;
    onActivateRequest: (plan: Plan) => void;
};

export function PlanList({ status, plans, onRetry, onActivateRequest }: PlanListProps) {
    return (
        <div
            role="region"
            aria-label={t('platform.plans.region.label')}
            className="flex flex-col gap-3"
        >
            {status === 'loading' && (
                <p role="status" className="text-sm text-fg-muted">
                    {t('platform.plans.loading')}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {t('platform.plans.error')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-sm font-medium text-fg-danger"
                        onClick={onRetry}
                    >
                        {t('platform.plans.retry')}
                    </button>
                </div>
            )}

            {status === 'success' && plans.length === 0 && (
                <p role="status" className="text-sm text-fg-muted">
                    {t('platform.plans.empty')}
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
                            <span className="font-medium text-fg">{formatPlanPrice(plan)}</span>
                            {!plan.is_active && (
                                <span className="text-fg-muted">
                                    {t('platform.plans.inactive')}
                                </span>
                            )}
                            {!plan.is_active && (
                                <Button
                                    type="button"
                                    className="self-start"
                                    onClick={() => onActivateRequest(plan)}
                                >
                                    {t('platform.plans.activate.button')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default PlanList;
