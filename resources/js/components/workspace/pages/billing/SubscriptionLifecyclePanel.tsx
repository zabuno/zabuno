import { t } from '../../../../i18n/workspace';
import { Button } from '../../../catalog/forms/micro/Button';

export type LifecyclePhase = 'none' | 'active' | 'cancelling' | 'grace' | 'suspended' | 'ended';

export type LifecycleSubscription = {
    phase: LifecyclePhase;
    plan_name: string | null;
    ends_at: string | null;
    grace_ends_at: string | null;
    scheduled_plan_name: string | null;
};

export type DowngradeTarget = {
    id: number;
    name: string;
};

export type ChangePreview = {
    target_plan_id: number;
    target_plan_name: string;
    effective_at: string;
    losing: ReadonlyArray<{ key: string; label: string }>;
};

export type SubscriptionLifecyclePanelProps = {
    status: 'loading' | 'error' | 'ready';
    subscription: LifecycleSubscription | null;
    /** Yalnız BUGÜNKÜNDEN UCUZ planlar; yükseltmenin yolu ödemedir. */
    downgradeTargets: ReadonlyArray<DowngradeTarget>;
    selectedTargetId: number | null;
    onSelectTarget: (planId: number) => void;
    preview: ChangePreview | null;
    previewError: boolean;
    /** İptal ONAY adımı açık mı — tek tıkla iptal edilmez, ama saklanmaz da. */
    confirmingCancel: boolean;
    onAskCancel: () => void;
    onDismissCancel: () => void;
    onConfirmCancel: () => void;
    onResume: () => void;
    onScheduleChange: () => void;
    onWithdrawChange: () => void;
    busy: boolean;
    actionError: string | null;
    onRetry: () => void;
};

/**
 * ABONELİĞİN EKSİK YARISI — SUNUM katmanı (`docs/107` Faz 1.3, `docs/134`).
 *
 * Fetch ve durum yönetimi `SubscriptionLifecycle` kabındadır; burası yalnız
 * kendisine verileni çizer, böylece her evre bir hikâyedir ve
 * `scripts/mobile-ux-audit` hepsini 320×568'de ÖLÇER.
 *
 * ═══ İPTAL DÜĞMESİ NEREDE DURUR ═══
 *
 * Saklanmaz: uzaktan satışta çıkış yolunu gizlemek, tüketici hukukunun tam
 * olarak hoşlanmadığı şeydir ve ürün de bunu yapmaz — düğme kendi bölümünde,
 * kendi başlığıyla ve okunur büyüklükte durur.
 *
 * Ama birincil eylemin YANINDA da durmaz ve tek tıkla çalışmaz: bölümün EN
 * ALTINDA, ikincil renkte ve bir onay adımının arkasındadır. Onay metni ne
 * olacağını TARİHİYLE yazar ("X tarihine kadar kullanmaya devam edeceksiniz")
 * ve geri dönüş yolunu aynı cümlede söyler.
 *
 * ═══ DAR EKRAN TABAN ═══
 *
 * Tek sütun, `gap-2`/`gap-3` sıkı boşluk, her dokunma hedefi `min-h-11`.
 * İç içe kap dolgusu birikmesin diye panelin kendi dolgusu yoktur; sayfa
 * çerçevesi zaten bir dolgu veriyor.
 */
export function SubscriptionLifecyclePanel({
    status,
    subscription,
    downgradeTargets,
    selectedTargetId,
    onSelectTarget,
    preview,
    previewError,
    confirmingCancel,
    onAskCancel,
    onDismissCancel,
    onConfirmCancel,
    onResume,
    onScheduleChange,
    onWithdrawChange,
    busy,
    actionError,
    onRetry,
}: SubscriptionLifecyclePanelProps) {
    const phase = subscription?.phase ?? 'none';
    const plan = subscription?.plan_name ?? '';
    const date = subscription?.ends_at ?? '';
    const graceDate = subscription?.grace_ends_at ?? '';

    return (
        <section
            role="region"
            aria-label={t('workspace.billing.lifecycle.region')}
            className="flex w-full flex-col gap-3"
        >
            <h2 className="text-body font-bold text-fg">
                {t('workspace.billing.lifecycle.region')}
            </h2>

            {status === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.lifecycle.loading')}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.lifecycle.error')}
                    </p>
                    <Button size="sm" color="light" className="self-start" onClick={onRetry}>
                        {t('workspace.billing.lifecycle.retry')}
                    </Button>
                </div>
            )}

            {status === 'ready' && (
                <>
                    {phase === 'none' && (
                        <p role="status" className="text-body text-fg-muted">
                            {t('workspace.billing.lifecycle.none')}
                        </p>
                    )}

                    {phase === 'active' && (
                        <p role="status" className="text-body text-fg-secondary">
                            {t('workspace.billing.lifecycle.active', { plan, date })}
                        </p>
                    )}

                    {phase === 'cancelling' && (
                        <div className="flex flex-col gap-2">
                            <p role="status" className="text-body font-medium text-fg">
                                {t('workspace.billing.lifecycle.cancelled', { plan, date })}
                            </p>
                            <p className="text-body text-fg-muted">
                                {t('workspace.billing.lifecycle.resume.hint')}
                            </p>
                            <Button
                                className="min-h-11 self-start"
                                onClick={onResume}
                                loading={busy}
                            >
                                {t('workspace.billing.lifecycle.resume')}
                            </Button>
                        </div>
                    )}

                    {phase === 'grace' && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('workspace.billing.lifecycle.grace', { plan, date, graceDate })}
                        </p>
                    )}

                    {phase === 'suspended' && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('workspace.billing.lifecycle.suspended', { plan, graceDate })}
                        </p>
                    )}

                    {phase === 'ended' && (
                        <p role="status" className="text-body text-fg-secondary">
                            {t('workspace.billing.lifecycle.ended', { date })}
                        </p>
                    )}

                    {(phase === 'grace' || phase === 'suspended' || phase === 'ended') && (
                        <p className="text-body text-fg-muted">
                            {t('workspace.billing.lifecycle.guestUnaffected')}
                        </p>
                    )}

                    {subscription?.scheduled_plan_name != null && (
                        <div className="flex flex-col gap-2 rounded-lg border border-border p-3">
                            <p role="status" className="text-body font-medium text-fg">
                                {t('workspace.billing.lifecycle.scheduled', {
                                    date,
                                    plan: subscription.scheduled_plan_name,
                                })}
                            </p>
                            <Button
                                size="sm"
                                color="light"
                                className="min-h-11 self-start"
                                onClick={onWithdrawChange}
                                loading={busy}
                            >
                                {t('workspace.billing.lifecycle.scheduled.withdraw')}
                            </Button>
                        </div>
                    )}

                    {(phase === 'active' || phase === 'cancelling') &&
                        subscription?.scheduled_plan_name == null && (
                            <div className="flex flex-col gap-2">
                                <h3 className="text-body font-bold text-fg">
                                    {t('workspace.billing.lifecycle.change.heading')}
                                </h3>
                                <p className="text-body text-fg-muted">
                                    {t('workspace.billing.lifecycle.change.upgradeHint')}
                                </p>

                                {downgradeTargets.length === 0 ? (
                                    <p role="status" className="text-body text-fg-muted">
                                        {t('workspace.billing.lifecycle.change.empty')}
                                    </p>
                                ) : (
                                    <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
                                        <legend className="mb-1 text-body font-medium text-fg">
                                            {t('workspace.billing.lifecycle.change.legend')}
                                        </legend>
                                        {downgradeTargets.map((target) => (
                                            <label
                                                key={target.id}
                                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2 text-body text-fg"
                                            >
                                                <input
                                                    type="radio"
                                                    name="downgrade-plan"
                                                    value={target.id}
                                                    checked={selectedTargetId === target.id}
                                                    onChange={() => onSelectTarget(target.id)}
                                                    className="h-5 w-5 shrink-0"
                                                />
                                                <span className="font-medium">{target.name}</span>
                                            </label>
                                        ))}
                                    </fieldset>
                                )}

                                {previewError && (
                                    <p
                                        role="alert"
                                        className="text-body font-medium text-fg-danger"
                                    >
                                        {t('workspace.billing.lifecycle.change.previewError')}
                                    </p>
                                )}

                                {preview !== null && (
                                    <div className="flex flex-col gap-2 rounded-lg border border-border p-3">
                                        <p className="text-body text-fg-secondary">
                                            {t('workspace.billing.lifecycle.change.effective', {
                                                date: preview.effective_at,
                                            })}
                                        </p>

                                        {preview.losing.length === 0 ? (
                                            <p className="text-body text-fg-muted">
                                                {t(
                                                    'workspace.billing.lifecycle.change.losingNothing',
                                                )}
                                            </p>
                                        ) : (
                                            <>
                                                {/*
                                                    KAYBEDİLECEK YETENEK, ONAYDAN ÖNCE VE ADIYLA.
                                                    Sahip neyi kaybedeceğini faturasından değil
                                                    buradan öğrenir (`docs/134` §2).
                                                */}
                                                <p className="text-body font-medium text-fg-danger">
                                                    {t(
                                                        'workspace.billing.lifecycle.change.losing',
                                                        { date: preview.effective_at },
                                                    )}
                                                </p>
                                                <ul className="m-0 flex list-disc flex-col gap-1 ps-5">
                                                    {preview.losing.map((capability) => (
                                                        <li
                                                            key={capability.key}
                                                            className="text-body text-fg"
                                                        >
                                                            {capability.label}
                                                        </li>
                                                    ))}
                                                </ul>
                                                <p className="text-body text-fg-muted">
                                                    {t(
                                                        'workspace.billing.lifecycle.change.guestWarning',
                                                    )}
                                                </p>
                                            </>
                                        )}

                                        <Button
                                            className="min-h-11 self-start"
                                            onClick={onScheduleChange}
                                            loading={busy}
                                        >
                                            {t('workspace.billing.lifecycle.change.submit')}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}

                    {/*
                        ÇIKIŞ YOLU EN ALTTA, KENDİ BAŞLIĞIYLA — saklanmadan,
                        birincil eylemin yanına da konmadan.
                    */}
                    {(phase === 'active' || phase === 'grace') && (
                        <div className="flex flex-col gap-2 border-t border-border pt-3">
                            {confirmingCancel ? (
                                <>
                                    <p className="text-body font-medium text-fg">
                                        {t('workspace.billing.lifecycle.cancel.confirmHeading')}
                                    </p>
                                    <p className="text-body text-fg-secondary">
                                        {t('workspace.billing.lifecycle.cancel.confirmBody', {
                                            date,
                                        })}
                                    </p>
                                    <div className="flex flex-col gap-2">
                                        <Button
                                            className="min-h-11"
                                            color="light"
                                            onClick={onDismissCancel}
                                        >
                                            {t('workspace.billing.lifecycle.cancel.dismiss')}
                                        </Button>
                                        <Button
                                            className="min-h-11"
                                            color="light"
                                            onClick={onConfirmCancel}
                                            loading={busy}
                                        >
                                            {t('workspace.billing.lifecycle.cancel.confirm')}
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <Button
                                    color="light"
                                    className="min-h-11 self-start"
                                    onClick={onAskCancel}
                                >
                                    {t('workspace.billing.lifecycle.cancel')}
                                </Button>
                            )}
                        </div>
                    )}

                    {actionError !== null && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {actionError}
                        </p>
                    )}
                </>
            )}
        </section>
    );
}

export default SubscriptionLifecyclePanel;
