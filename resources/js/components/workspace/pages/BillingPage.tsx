import { Button } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { UnavailableRegion } from './wp05/UnavailableRegion';
import { DisabledField } from './wp05/DisabledField';
import { PlanCatalog } from './billing/PlanCatalog';
import { CurrentSubscriptionStatus } from './billing/CurrentSubscriptionStatus';
import { IyzicoSandboxCheckout } from './billing/IyzicoSandboxCheckout';
import { WorkspaceLedger } from './billing/WorkspaceLedger';

type BillingPageProps = {
    workspaceId: number;
};

/**
 * The Plan region and Current plan are server-authoritative (GET
 * /api/workspaces/{workspaceId}/plans and .../subscription). Manual payment
 * is read-only here — platform finance records it. The Iyzico sandbox
 * checkout is a frontend-only sandbox surface backed by the sandbox
 * session API — no real money is charged.
 */
export function BillingPage({ workspaceId }: BillingPageProps) {
    return (
        <div id="section-billing">
            <WorkspacePageFrame
                title={t('workspace.billing.heading')}
                description={t('workspace.billing.operational.description')}
            >
                <PlanCatalog
                    workspaceId={workspaceId}
                    label={t('workspace.billing.plan.region')}
                    loadingText={t('workspace.billing.plan.loading')}
                    emptyText={t('workspace.billing.plan.empty')}
                    errorText={t('workspace.billing.plan.error')}
                    retryText={t('workspace.billing.plan.retry')}
                    priceUnavailableText={t('workspace.billing.plan.priceUnavailable')}
                />

                <CurrentSubscriptionStatus workspaceId={workspaceId} />

                <UnavailableRegion
                    label={t('workspace.billing.manualPayment.region')}
                    statusText={t('workspace.billing.manualPayment.platformFinance')}
                >
                    <DisabledField
                        id="billing-manual-payment-plan"
                        label={t('workspace.billing.manualPayment.field.plan')}
                    />
                    <DisabledField
                        id="billing-manual-payment-end-date"
                        label={t('workspace.billing.manualPayment.field.endDate')}
                    />
                    <DisabledField
                        id="billing-manual-payment-payment-note"
                        label={t('workspace.billing.manualPayment.field.paymentNote')}
                    />
                    <DisabledField
                        id="billing-manual-payment-document-reference"
                        label={t('workspace.billing.manualPayment.field.documentReference')}
                    />
                    <Button disabled className="w-full">
                        {t('workspace.billing.recordPayment.button')}
                    </Button>
                </UnavailableRegion>

                <IyzicoSandboxCheckout workspaceId={workspaceId} />

                <WorkspaceLedger workspaceId={workspaceId} />
            </WorkspacePageFrame>
        </div>
    );
}

export default BillingPage;
