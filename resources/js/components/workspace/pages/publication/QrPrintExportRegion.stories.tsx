import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrPrintExportRegion } from './QrPrintExportRegion';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * QR baskı bölgesi — ürünün kalbi.
 *
 * Bu dosyanın story'si YOKTU ve kök sebep buydu (`docs/104`): `surface`
 * katmanı her görsel kapının dışında kalıyor, ekrana kimse bakmıyordu. Story
 * artık var: üç hâl (tek kod, çok kod, hiç kod) izole olarak görülebilir.
 */
const item = (
    id: number,
    token: string,
    tableName: string | null = null,
    areaLabel: string | null = null,
): QrCodeItem => ({
    id,
    workspaceId: 7,
    locationId: 3,
    menuId: 11,
    token,
    resolverUrl: `https://zabuno.com/q/${token}`,
    tableName,
    areaLabel,
    destinationType: 'published_menu',
    state: 'active',
});

const meta: Meta<typeof QrPrintExportRegion> = {
    title: 'Surface/Workspace/QrPrintExportRegion',
    component: QrPrintExportRegion,
    decorators: [
        (Story) => (
            <div className="max-w-[44rem] bg-canvas p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
    args: {
        workspaceId: 7,
        locationId: 3,
        menuId: 11,
        hasCurrentPublication: true,
    },
};

export default meta;
type Story = StoryObj<typeof QrPrintExportRegion>;

/** Tek kod: sahibin ilk günü. Seçici çizilmez — seçilecek başka şey yok. */
export const SingleCode: Story = {
    args: { items: [item(1, 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf')] },
};

/** Birden çok kod: seçici belirir ve seçenekler MASA ADIYLA yazılır (FF-109). */
export const ManyCodes: Story = {
    args: {
        items: [
            item(1, 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf', 'T12', 'Bahçe'),
            item(2, 'k2LmNoPqRsTuVwXyZ01234567890abc', 'T13', 'Bahçe'),
        ],
    },
};

/** Hiç kod yok: ayarlar da çizilmez — olmayan bir şeyin ayarı olmaz. */
export const NoCode: Story = { args: { items: [] } };
