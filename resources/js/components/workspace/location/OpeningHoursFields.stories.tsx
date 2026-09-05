import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';

import { OpeningHoursFields } from './OpeningHoursFields';
import { suggestedDraft, type OpeningHoursDraft } from './openingHours';
import { ThemeRoot } from '../../theme/ThemeRoot';

/**
 * Şubenin haftalık çalışma saati girişi (`docs/109` §6.4).
 *
 * Hikâyeler tam olarak ekrana bakmadan doğrulanamayan hâlleri gösterir:
 * saat HİÇ girilmemiş şube (yedi boş satır yok), kapalı günün sönmüş saat
 * alanları, ve gece yarısını aşan kapanışın satırda söylenmesi.
 */
function Harness({ initial }: { initial: OpeningHoursDraft[] | null }) {
    const [draft, setDraft] = useState<OpeningHoursDraft[] | null>(initial);

    return <OpeningHoursFields idPrefix="story" draft={draft} onChange={setDraft} />;
}

const meta: Meta<typeof OpeningHoursFields> = {
    title: 'Compound/Workspace/OpeningHoursFields',
    component: OpeningHoursFields,
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div style={{ maxWidth: '40rem' }}>
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: {
        idPrefix: 'story',
        draft: suggestedDraft(),
        onChange: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof OpeningHoursFields>;

/** Hafta girilmiş: yedi gün, önerilen 09:00–23:00. */
export const Week: Story = { render: () => <Harness initial={suggestedDraft()} /> };

/** Saat HİÇ girilmemiş — meşru bir hâl; kart o satırı çizmez. */
export const NotSet: Story = { render: () => <Harness initial={null} /> };

/** Pazartesi kapalı: o günün saat alanları söner. */
export const WithClosedDay: Story = {
    render: () => (
        <Harness
            initial={suggestedDraft().map((day) =>
                day.day === 1 ? { ...day, closed: true } : day,
            )}
        />
    ),
};

/** Cuma 18:00–02:00: kapanış ertesi güne taşar ve satır bunu söyler. */
export const CrossingMidnight: Story = {
    render: () => (
        <Harness
            initial={suggestedDraft().map((day) =>
                day.day === 5 ? { ...day, opens: '18:00', closes: '02:00' } : day,
            )}
        />
    ),
};

/** Sunucunun alan hatası formda gösterilir. */
export const WithError: Story = {
    args: { draft: suggestedDraft(), errorText: 'A week needs all seven days; day 4 is missing.' },
};

/** 320 piksel: gün adı, kapalı kutusu ve iki saat alt alta iner. */
export const Mobile320: Story = {
    render: () => <Harness initial={suggestedDraft()} />,
    globals: { viewport: { value: 'xs320', isRotated: false } },
};
