import type { ReactNode } from 'react';

import { t } from '../../../../i18n/workspace';
import { formatMoneyOr } from '../../../../money/format';
import { Button } from '../../../catalog/forms/micro/Button';

export type PurchasablePlan = {
    id: number;
    name: string;
    amount_minor: number;
    currency: string;
};

export type BillingProfileData = {
    legal_name: string;
    tax_number: string;
    tax_office: string;
    address: string;
    city: string;
    country: string;
    email: string;
    phone: string;
};

export type CheckoutProfileState =
    | { state: 'loading' }
    | { state: 'error' }
    | { state: 'missing' }
    | { state: 'complete'; profile: BillingProfileData };

export type LatestTransaction = {
    id: number;
    state: 'initiated' | 'succeeded' | 'failed' | 'refunded';
    failure_reason: string | null;
    redirect_url: string | null;
};

export type CheckoutPanelProps = {
    /** Sunucunun söylediği etkin kip; henüz okunmadıysa null. */
    mode: 'sandbox' | 'live' | null;
    periodDays: number;
    plans: { state: 'loading' | 'error' | 'ready'; items: PurchasablePlan[] };
    selectedPlanId: number | null;
    onSelectPlan: (planId: number) => void;
    profile: CheckoutProfileState;
    profileFormOpen: boolean;
    /** Kap, form açıkken formu kendisi verir; panel formun içini bilmez. */
    profileForm?: ReactNode;
    onOpenProfileForm: () => void;
    statusState: 'loading' | 'error' | 'ready';
    latest: LatestTransaction | null;
    proceeding: boolean;
    proceedError: string | null;
    onProceed: () => void;
    onRetryLoad: () => void;
};

function isHttpsUrl(value: string | null): value is string {
    if (value === null) {
        return false;
    }

    try {
        return new URL(value).protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * Abonelik satın alma paneli — SUNUM katmanı (docs/107 Faz 1.1, docs/123).
 *
 * Fetch, yönlendirme ve durum yönetimi `SubscribeCheckout`'ta; burası
 * yalnız kendisine verileni çizer. Böylece hikâyeler her durumu ağ olmadan
 * gösterir ve 320 pikselde ÖLÇÜLÜR.
 *
 * Kart alanı burada YOKTUR ve olmayacaktır: kart yalnız Iyzico'nun barındırdığı
 * ödeme sayfasına girilir. Bu panel plan seçtirir, fatura profilini doğrular
 * ve "Proceed to payment" ile o sayfaya gönderir.
 *
 * Dar ekran TABAN: tek sütun, dokunma hedefi ≥44 px (`min-h-11` satırlar,
 * `Button` kontrol yüksekliği), boşluk sıkı (`gap-2`/`gap-3`).
 */
export function CheckoutPanel({
    mode,
    periodDays,
    plans,
    selectedPlanId,
    onSelectPlan,
    profile,
    profileFormOpen,
    profileForm,
    onOpenProfileForm,
    statusState,
    latest,
    proceeding,
    proceedError,
    onProceed,
    onRetryLoad,
}: CheckoutPanelProps) {
    const canProceed =
        selectedPlanId !== null &&
        profile.state === 'complete' &&
        statusState === 'ready' &&
        !proceeding;
    const days = String(periodDays);

    return (
        <section
            role="region"
            aria-label={t('workspace.billing.checkout.region')}
            className="flex w-full flex-col gap-3"
        >
            <h2 className="text-body font-bold text-fg">
                {t('workspace.billing.checkout.region')}
            </h2>
            <p className="text-body text-fg-muted">
                {t('workspace.billing.checkout.description', { days })}
            </p>

            {mode === 'sandbox' && (
                <p role="status" className="text-body font-medium text-fg-secondary">
                    {t('workspace.billing.checkout.mode.sandbox')}
                </p>
            )}
            {mode === 'live' && (
                <p className="text-body text-fg-secondary">
                    {t('workspace.billing.checkout.mode.live')}
                </p>
            )}

            {statusState === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.checkout.status.loading')}
                </p>
            )}
            {statusState === 'error' && (
                <div className="flex flex-col gap-2">
                    <p className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.checkout.status.error')}
                    </p>
                    <Button size="sm" color="light" className="self-start" onClick={onRetryLoad}>
                        {t('workspace.billing.checkout.retry')}
                    </Button>
                </div>
            )}

            {latest?.state === 'failed' && (
                <div className="flex flex-col gap-1">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {latest.failure_reason
                            ? t('workspace.billing.checkout.latest.failed', {
                                  reason: latest.failure_reason,
                              })
                            : t('workspace.billing.checkout.latest.failedNoReason')}
                    </p>
                    <p className="text-body text-fg-muted">
                        {t('workspace.billing.checkout.latest.failedHint')}
                    </p>
                </div>
            )}
            {latest?.state === 'succeeded' && (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.billing.checkout.latest.succeeded')}
                </p>
            )}
            {latest?.state === 'refunded' && (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.billing.checkout.latest.refunded')}
                </p>
            )}
            {latest?.state === 'initiated' && (
                <div className="flex flex-col gap-2">
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.billing.checkout.latest.initiated')}
                    </p>
                    {isHttpsUrl(latest.redirect_url) && (
                        <a
                            href={latest.redirect_url}
                            className="inline-flex min-h-11 items-center self-start rounded-lg border border-border px-3 text-body font-medium text-fg-link"
                        >
                            {t('workspace.billing.checkout.latest.continue')}
                        </a>
                    )}
                </div>
            )}

            <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
                <legend className="mb-1 text-body font-bold text-fg">
                    {t('workspace.billing.checkout.plan.legend')}
                </legend>
                {plans.state === 'loading' && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.billing.checkout.plan.loading')}
                    </p>
                )}
                {plans.state === 'error' && (
                    <p className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.checkout.plan.error')}
                    </p>
                )}
                {plans.state === 'ready' && plans.items.length === 0 && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.billing.checkout.plan.empty')}
                    </p>
                )}
                {plans.state === 'ready' &&
                    plans.items.map((plan) => (
                        <label
                            key={plan.id}
                            className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2 text-body text-fg"
                        >
                            <input
                                type="radio"
                                name="checkout-plan"
                                value={plan.id}
                                checked={selectedPlanId === plan.id}
                                onChange={() => onSelectPlan(plan.id)}
                                className="h-5 w-5 shrink-0"
                            />
                            <span className="font-medium">{plan.name}</span>
                            <span className="ms-auto text-end text-fg-secondary">
                                {t('workspace.billing.checkout.plan.price', {
                                    price: formatMoneyOr(
                                        plan.amount_minor,
                                        plan.currency,
                                        `${plan.amount_minor} ${plan.currency}`,
                                    ),
                                    days,
                                })}
                            </span>
                        </label>
                    ))}
            </fieldset>

            <div className="flex flex-col gap-2">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.billing.checkout.profile.heading')}
                </h3>
                {profile.state === 'loading' && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.billing.checkout.profile.loading')}
                    </p>
                )}
                {profile.state === 'error' && (
                    <div className="flex flex-col gap-2">
                        <p className="text-body font-medium text-fg-danger">
                            {t('workspace.billing.checkout.profile.error')}
                        </p>
                        <Button
                            size="sm"
                            color="light"
                            className="self-start"
                            onClick={onRetryLoad}
                        >
                            {t('workspace.billing.checkout.retry')}
                        </Button>
                    </div>
                )}
                {profile.state === 'missing' && (
                    <p className="text-body text-fg-secondary">
                        {t('workspace.billing.checkout.profile.missing')}
                    </p>
                )}
                {profile.state === 'complete' && !profileFormOpen && (
                    <div className="flex flex-col gap-0.5 text-body text-fg-secondary">
                        <span className="font-medium text-fg">{profile.profile.legal_name}</span>
                        <span>
                            {t('workspace.billing.checkout.profile.summary.taxNumber', {
                                number: profile.profile.tax_number,
                            })}
                        </span>
                        <span>
                            {profile.profile.city}, {profile.profile.country}
                        </span>
                    </div>
                )}
                {!profileFormOpen && profile.state !== 'loading' && profile.state !== 'error' && (
                    <Button
                        size="sm"
                        color="light"
                        className="self-start"
                        onClick={onOpenProfileForm}
                    >
                        {profile.state === 'complete'
                            ? t('workspace.billing.checkout.profile.edit')
                            : t('workspace.billing.checkout.profile.add')}
                    </Button>
                )}
                {profileFormOpen && profileForm}
            </div>

            {proceedError && (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {proceedError}
                </p>
            )}

            <Button
                type="button"
                className="w-full"
                disabled={!canProceed}
                loading={proceeding}
                loadingText={t('workspace.billing.checkout.proceeding')}
                onClick={onProceed}
            >
                {t('workspace.billing.checkout.proceed')}
            </Button>
        </section>
    );
}

export default CheckoutPanel;
