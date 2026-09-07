import type { Meta, StoryObj } from '@storybook/react-vite';

import { ThemeRoot } from '../../../theme/ThemeRoot';
import { SubscriptionLifecyclePanel } from './SubscriptionLifecyclePanel';

/**
 * Aboneliğin eksik yarısı (`docs/107` Faz 1.3, `docs/134`).
 *
 * Her evre ayrı bir hikâyedir çünkü her evre sahibin başka bir gününe denk
 * gelir: kartının süresi dolan restoran sahibi üçüncü gün `Grace`,
 * onuncu gün `Suspended` görür. `scripts/mobile-ux-audit` hepsini 320×568'de
 * ÖLÇER — dokunma hedefi ≥44 px, yatay taşma yok, dolgu birikmiyor.
 */
const meta: Meta<typeof SubscriptionLifecyclePanel> = {
    title: 'Surface/Workspace/SubscriptionLifecyclePanel',
    component: SubscriptionLifecyclePanel,
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-fluid-md)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: {
        status: 'ready',
        subscription: {
            phase: 'active',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: null,
        },
        downgradeTargets: [{ id: 11, name: 'Starter' }],
        selectedTargetId: null,
        onSelectTarget: () => undefined,
        preview: null,
        previewError: false,
        confirmingCancel: false,
        onAskCancel: () => undefined,
        onDismissCancel: () => undefined,
        onConfirmCancel: () => undefined,
        onResume: () => undefined,
        onScheduleChange: () => undefined,
        onWithdrawChange: () => undefined,
        busy: false,
        actionError: null,
        onRetry: () => undefined,
    },
};

export default meta;

type Story = StoryObj<typeof SubscriptionLifecyclePanel>;

/** Ödenmiş dönem sürüyor: çıkış yolu en altta, saklanmadan. */
export const Active: Story = {};

/** İptal onayı: ne olacağı TARİHİYLE yazılı, geri dönüş aynı ekranda. */
export const CancelConfirmation: Story = {
    args: { confirmingCancel: true },
};

/** İptal edildi, hizmet sürüyor: cayma tek düğme, yeniden ödeme yok. */
export const Cancelling: Story = {
    args: {
        subscription: {
            phase: 'cancelling',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: null,
        },
    },
};

/** Düşürme seçildi: kaybedilecek yetenek onaydan ÖNCE ve adıyla. */
export const DowngradePreview: Story = {
    args: {
        selectedTargetId: 11,
        preview: {
            target_plan_id: 11,
            target_plan_name: 'Starter',
            effective_at: '2026-09-30',
            losing: [
                { key: 'menu.rich-media', label: 'Zengin görsel' },
                { key: 'analytics.reporting', label: 'Analitik raporlama' },
            ],
        },
    },
};

/** Düşürme kayıtlı: dönem sonuna kadar hiçbir şey değişmez. */
export const DowngradeScheduled: Story = {
    args: {
        subscription: {
            phase: 'active',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: 'Starter',
        },
    },
};

/** Ödeme gelmedi, ödemesiz süre: hesap kapanmadı ve kapanacağı gün yazılı. */
export const GracePeriod: Story = {
    args: {
        subscription: {
            phase: 'grace',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: null,
        },
    },
};

/** Askı: yetenekler kapalı, menü yayında, veri duruyor. */
export const Suspended: Story = {
    args: {
        subscription: {
            phase: 'suspended',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: null,
        },
    },
};

/** Abonelik bitti (iptal edilmişti ve dönem doldu). */
export const Ended: Story = {
    args: {
        subscription: {
            phase: 'ended',
            plan_name: 'Pro',
            ends_at: '2026-09-30',
            grace_ends_at: '2026-10-07',
            scheduled_plan_name: null,
        },
    },
};
