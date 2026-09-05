import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrCodeListItem, type QrCodeItem } from './QrCodeListItem';

/**
 * Bir QR kodunun listedeki satırı — sahibin "hangi masa?" sorusunu yanıtlayan
 * tek yer.
 *
 * Bu dosyanın story'si YOKTU ve kök sebep buydu (`docs/104`): `surface`
 * katmanı her görsel kapının dışında kalıyor, bu satıra kimse bakmıyordu.
 */
const base: QrCodeItem = {
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

const meta: Meta<typeof QrCodeListItem> = {
    title: 'Surface/Workspace/QrCodeListItem',
    component: QrCodeListItem,
    decorators: [
        (Story) => (
            <ul className="max-w-[40rem] bg-canvas p-[var(--space-fluid-lg)]">
                <Story />
            </ul>
        ),
    ],
    args: {
        item: base,
        onDisable: () => undefined,
        onEnable: () => undefined,
        onStartMove: () => undefined,
    },
};

export default meta;
type Story = StoryObj<typeof meta>;

/** Masaya bağlı kod: ad ve alan önde, adres sessiz bir ayrıntı. */
export const Named: Story = {};

/** Masaya bağlı olmayan kod: ad uydurulmaz, "giriş kodu" denir. */
export const Unnamed: Story = {
    args: { item: { ...base, tableName: null, areaLabel: null } },
};

/**
 * Kapatılmış kod KİMLİĞİNİ KORUR: eskiden satır yalnız "Disabled" kelimesine
 * iniyor, hangi kodun kapatıldığı anlaşılmıyordu.
 */
export const Disabled: Story = {
    args: { item: { ...base, state: 'disabled' } },
};

/** Şube taşıma açıkken. */
export const Moving: Story = {
    args: {
        moving: true,
        otherLocations: [
            { id: 2, displayName: 'Beşiktaş' },
            { id: 3, displayName: 'Bebek' },
        ],
        onRetarget: () => undefined,
        onCancelMove: () => undefined,
    },
};
