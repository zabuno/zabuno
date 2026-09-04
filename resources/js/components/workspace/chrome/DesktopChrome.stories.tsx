import type { Meta, StoryObj } from '@storybook/react-vite';
import { Gear, UserCircle } from '@phosphor-icons/react';

import { DesktopSidebar } from './DesktopChrome';

/**
 * Rayın kendisi bir HİKÂYE taşır (FF-127).
 *
 * Bu bölüm, ürüne girmeden görülemeyen tek kabuk parçasıydı: sabit blok
 * yalnız oturum açmış bir çalışma alanında çiziliyordu, dolayısıyla
 * "değişikliği ekranda gör" kuralı burada uygulanamıyordu ve tam bu yüzden
 * `docs/102` §5b'deki körlük burada tekrar edebilirdi.
 */
const meta: Meta<typeof DesktopSidebar> = {
    title: 'Macro/Layout/DesktopSidebar',
    component: DesktopSidebar,
    parameters: { layout: 'fullscreen' },
};

export default meta;

type Story = StoryObj<typeof DesktopSidebar>;

const navGroups = [
    {
        key: 'primary',
        label: 'Your menu',
        items: [
            { key: 'home', label: 'Home', href: '/app/zeytin/dashboard' },
            { key: 'menus', label: 'Menus', href: '/app/zeytin/menu' },
            { key: 'qr', label: 'QR codes', href: '/app/zeytin/qr-codes' },
        ],
    },
];

const railSections = [
    {
        key: 'profile',
        label: 'Profile',
        href: '/app/zeytin/profile',
        icon: <UserCircle size={18} weight="regular" />,
    },
    {
        key: 'settings',
        label: 'Settings',
        href: '/app/zeytin/settings',
        icon: <Gear size={18} weight="regular" />,
    },
];

export const WithAccountRail: Story = {
    args: {
        navGroups,
        activeNavKey: 'home',
        navLabel: 'Restaurant admin',
        workspaceName: 'Zeytin Restoranları',
        railSections,
    },
    render: (args) => (
        <div className="flex h-dvh bg-canvas">
            <DesktopSidebar {...args} />
        </div>
    ),
};

/** Ayarlar açıkken: aktif satır zeminle işaretlenir, ikinci bir işaret yok. */
export const SettingsActive: Story = {
    ...WithAccountRail,
    args: {
        ...WithAccountRail.args,
        activeNavKey: 'settings',
        railSections: railSections.map((section) => ({
            ...section,
            active: section.key === 'settings',
        })),
    },
};
