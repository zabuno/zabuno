import type { Meta, StoryObj } from '@storybook/react-vite';

import { LocationCard, type LocationCardLocation } from './LocationCard';
import { ThemeRoot } from '../../../theme/ThemeRoot';

/**
 * Şube kartı (`docs/109` §6.4). Hikâyeler tam olarak kartın ÇİZMEDİĞİ şeyleri
 * göstermek için var: ölçüm kapalıyken tarama satırı yok olur, masası olmayan
 * şubede kurulum rozeti belirir. İkisi de ekrana bakmadan doğrulanamaz.
 */
const location: LocationCardLocation = {
    id: 811,
    workspace_id: 7,
    brand_id: 3,
    display_name: 'Zeytin Kadıköy',
    country_code: 'TR',
    timezone: 'Europe/Istanbul',
    city: 'İstanbul',
    address_line1: 'Moda Caddesi 12',
    address_line2: null,
    postal_code: null,
    table_count: 12,
};

const meta: Meta<typeof LocationCard> = {
    title: 'Surface/Workspace/LocationCard',
    component: LocationCard,
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div style={{ maxWidth: '22rem' }}>
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: {
        location,
        weeklyScans: 340,
        editing: false,
        onOpenTables: () => {},
        onToggleEdit: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof LocationCard>;

/** Kurulumu bitmiş, ölçümü açık şube. */
export const Measured: Story = {};

/** Ölçüm KAPALI: tarama satırı hiç çizilmez, yerine "0" yazılmaz. */
export const WithoutMeasurement: Story = { args: { weeklyScans: null } };

/** Gerçek sıfır: o hafta kimse taramamış — bu bir cevaptır. */
export const ZeroScans: Story = { args: { weeklyScans: 0 } };

/** Masası olmayan şube: taranamaz, yani kurulumu bitmemiştir. */
export const InSetup: Story = {
    args: { location: { ...location, table_count: 0 }, weeklyScans: null },
};

/** Adres alanı boş kalmış eski bir kayıt. */
export const WithoutAddress: Story = {
    args: { location: { ...location, address_line1: '', address_line2: null } },
};

const uniformWeek = [1, 2, 3, 4, 5, 6, 7].map((day) => ({
    day,
    closed: false,
    opens_minute: 540,
    closes_minute: 1380,
}));

/** Tek tip hafta: aralık koşulsuz yazılır, her gün doğrudur. */
export const WithUniformHours: Story = {
    args: { location: { ...location, opening_hours: uniformWeek } },
};

/**
 * Hafta DEĞİŞİYOR (çarşamba kapalı): tek bir aralık yalan olurdu, kart
 * bugünü söyler ve bunu açıkça belirtir.
 */
export const WithVaryingHours: Story = {
    args: {
        location: {
            ...location,
            opening_hours: uniformWeek.map((day) =>
                day.day === 3
                    ? { ...day, closed: true, opens_minute: null, closes_minute: null }
                    : day,
            ),
        },
    },
};

/** Saat hiç girilmemiş: kart o satırı ÇİZMEZ — uydurma varsayılan yok. */
export const WithoutHours: Story = {
    args: { location: { ...location, opening_hours: [] } },
};
