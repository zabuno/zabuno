import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrCardWizard } from './QrCardWizard';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * Masa kartı sihirbazı.
 *
 * Önizleme görseli gerçek uca (`/api/.../card.svg`) bağlıdır ve Storybook'ta
 * yüklenmez — burada doğrulanan şey ADIM AKIŞI ve kontrollerin dizilimi.
 * Kartın kendisi üretilen PDF'e gözle bakılarak doğrulandı (FF-120).
 */
const item: QrCodeItem = {
    id: 4021,
    workspaceId: 7,
    locationId: 3,
    menuId: 11,
    token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    resolverUrl: 'https://zabuno.com/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    tableName: 'T12',
    areaLabel: 'Bahçe',
    destinationType: 'published_menu',
    state: 'active',
};

const meta = {
    title: 'Surface/Workspace/QrCardWizard',
    component: QrCardWizard,
    args: { item },
    decorators: [
        (Story) => (
            <div className="max-w-[52rem] bg-canvas p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof QrCardWizard>;

export default meta;
type Story = StoryObj<typeof meta>;

/** 1. adım: tasarım ve kartın üzerindeki cümle. */
export const Design: Story = {};
