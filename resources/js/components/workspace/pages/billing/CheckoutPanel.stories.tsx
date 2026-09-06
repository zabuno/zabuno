import type { Meta, StoryObj } from '@storybook/react-vite';

import { ThemeRoot } from '../../../theme/ThemeRoot';
import { CheckoutPanel, type BillingProfileData } from './CheckoutPanel';

/**
 * Abonelik satın alma paneli (docs/107 Faz 1.1, docs/123).
 *
 * Hikâyeler sunum katmanını ağ olmadan gösterir; `scripts/mobile-ux-audit`
 * her durumu 320×568'de ÖLÇER: dokunma hedefi ≥44 px, yatay taşma yok,
 * iç içe dolgu birikmiyor. "Kart alanı yok" burada görünür: panelde bir
 * kart numarası kutusu çizilmez.
 */
const profile: BillingProfileData = {
    legal_name: 'Kadıköy Kebap Gıda Ltd. Şti.',
    tax_number: '1234567890',
    tax_office: 'Kadıköy',
    address: 'Moda Cad. No:1',
    city: 'Istanbul',
    country: 'TR',
    email: 'muhasebe@kadikoykebap.test',
    phone: '+905551112233',
};

const plans = [
    { id: 11, name: 'Starter', amount_minor: 49900, currency: 'TRY' },
    { id: 12, name: 'Pro', amount_minor: 149900, currency: 'TRY' },
];

const meta: Meta<typeof CheckoutPanel> = {
    title: 'Surface/Workspace/BillingCheckoutPanel',
    component: CheckoutPanel,
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
        mode: 'sandbox',
        periodDays: 30,
        plans: { state: 'ready', items: plans },
        selectedPlanId: 12,
        onSelectPlan: () => undefined,
        profile: { state: 'complete', profile },
        profileFormOpen: false,
        onOpenProfileForm: () => undefined,
        statusState: 'ready',
        latest: null,
        proceeding: false,
        proceedError: null,
        onProceed: () => undefined,
        onRetryLoad: () => undefined,
    },
};

export default meta;

type Story = StoryObj<typeof CheckoutPanel>;

/** Plan seçili, profil tam: "Proceed to payment" açık. */
export const ReadyToPay: Story = {};

/** Profil yok: eksiklik ADIYLA yazılır, Proceed kapalı. */
export const ProfileMissing: Story = {
    args: { selectedPlanId: null, profile: { state: 'missing' } },
};

/** Son ödeme reddedildi: sebep ekranda, yol yeniden açık. */
export const PaymentFailed: Story = {
    args: {
        latest: {
            id: 5,
            state: 'failed',
            failure_reason: 'Insufficient funds',
            redirect_url: null,
        },
    },
};

/** Canlı kip: yönlendirme cümlesi, "test mode" yok. */
export const LiveMode: Story = {
    args: { mode: 'live' },
};

/** Ödeme sayfası açılıyor. */
export const Proceeding: Story = {
    args: { proceeding: true },
};
