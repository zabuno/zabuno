import type { Meta, StoryObj } from '@storybook/react-vite';

import { LanguageSwitcher } from './LanguageSwitcher';

const nine = [
    { code: 'en', href: '/en/product/qr-menu/' },
    { code: 'tr', href: '/tr/urun/qr-menu/' },
    { code: 'ar', href: '/ar/product/qr-menu/' },
    { code: 'ru', href: '/ru/product/qr-menu/' },
    { code: 'fa', href: '/fa/product/qr-menu/' },
    { code: 'ku', href: '/ku/product/qr-menu/' },
    { code: 'de', href: '/de/product/qr-menu/' },
    { code: 'fr', href: '/fr/product/qr-menu/' },
    { code: 'it', href: '/it/product/qr-menu/' },
];

const unavailableLabels = {
    'not-offered': 'Not ready yet',
    'no-counterpart': 'Not on this page',
} as const;

const meta: Meta<typeof LanguageSwitcher> = {
    title: 'Compound/Navigation/LanguageSwitcher',
    component: LanguageSwitcher,
    parameters: {
        docs: {
            description: {
                component:
                    'Real `<a href>` links, one per language — no script, no menu. Each language is written in its own language (endonym) and the region mark is secondary and hidden from assistive technology. See docs/120 §5.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof LanguageSwitcher>;

/** Dokuzu da hazır — altyapının tarif edebildiği dil uzayının tamamı. */
export const AllNine: Story = {
    args: {
        label: 'Language',
        options: nine,
        currentCode: 'en',
        currentLabel: 'current',
        unavailableLabels,
    },
};

/** Bugünün gerçeği: yalnız `en` sunuluyor, kalan sekizi açıkça söylüyor. */
export const ShippedToday: Story = {
    args: {
        label: 'Language',
        currentCode: 'en',
        currentLabel: 'current',
        unavailableLabels,
        options: nine.map((option) =>
            option.code === 'en'
                ? option
                : { ...option, href: null, unavailableReason: 'not-offered' as const },
        ),
    },
};

/**
 * Sağdan sola bir arayüzün içinde.
 *
 * Ölçülen şey ikisi birden: RTL bir belgede listenin kendisi bozulmuyor mu,
 * ve LTR endonimler (Deutsch, Français) o listenin içinde doğru diziliyor mu.
 */
export const RightToLeft: Story = {
    args: {
        label: 'اللغة',
        options: nine,
        currentCode: 'ar',
        currentLabel: 'الحالية',
        unavailableLabels: {
            'not-offered': 'غير جاهزة بعد',
            'no-counterpart': 'غير متوفرة في هذه الصفحة',
        },
    },
    globals: { direction: 'rtl' },
};

/** Bu sayfanın karşılığı olmayan diller — kırık bağlantı yerine dürüst cümle. */
export const MissingCounterpart: Story = {
    args: {
        label: 'Language',
        currentCode: 'tr',
        currentLabel: 'current',
        unavailableLabels,
        options: [
            { code: 'tr', href: '/tr/urun/qr-menu/' },
            { code: 'en', href: '/en/product/qr-menu/' },
            { code: 'de', href: null, unavailableReason: 'no-counterpart' as const },
            { code: 'fr', href: null, unavailableReason: 'not-offered' as const },
        ],
    },
};
