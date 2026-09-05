import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { OpeningHoursFields } from './OpeningHoursFields';
import {
    draftFromEntries,
    entriesFromDraft,
    suggestedDraft,
    type OpeningHoursDraft,
} from './openingHours';

/**
 * ÇALIŞMA SAATİ GİRİŞ YÜZEYİ — `docs/109` §6.4.
 *
 * NEDEN KIRMIZI: bu bileşen yok. Alan ve uç artık var ama sahip saatini
 * hiçbir yerden GİREMİYOR; girilemeyen bir alan, veritabanında duran ölü
 * bir sütundur.
 *
 * YÜZEYİN ÜÇ KARARI:
 *
 * 1. **Saat OLMAYAN şube normaldir.** Form açıldığında yedi boş satır
 *    dayatmaz: önce "bu şubenin çalışma saati var" der. İşaretlenmemişse
 *    kartta o satır hiç çizilmez ve bu meşru bir hâldir.
 * 2. **Öneri ile seçim ayrıdır ama öneri EKRANDA durur.** İşaretlendiği
 *    anda yedi gün 09:00–23:00 ile dolar. Boş bırakılsaydı sahip Kaydet'e
 *    basar ve sunucudan 422 yerdi — kendi girmediği bir şey yüzünden.
 *    Aynı yaklaşım saat dilimi alanında da var (`RegionalFields`): önerilen
 *    değer görünür durur, sahip beğenmezse değiştirir.
 * 3. **"Ertesi gün" diye bir kutu YOKTUR.** Kapanış açılıştan erkense tek
 *    makul okuma ertesi gündür (22:00 → 02:00). Kutu koymak, herkesin
 *    bildiği bir şeyi form alanına dönüştürmek olurdu; onun yerine satır
 *    sonucu SÖYLER ("next day").
 */
afterEach(() => {
    cleanup();
});

const WEEK: OpeningHoursDraft[] = [1, 2, 3, 4, 5, 6, 7].map((day) => ({
    day,
    closed: false,
    opens: '09:00',
    closes: '23:00',
}));

describe('draftFromEntries — sunucudan gelen hafta', () => {
    it('saat girilmemişse taslak YOKTUR', () => {
        expect(draftFromEntries([])).toBeNull();
        expect(draftFromEntries(undefined)).toBeNull();
    });

    it('gece yarısını aşan kapanışı gündelik saate indirir', () => {
        const draft = draftFromEntries([
            { day: 1, closed: false, opens_minute: 1080, closes_minute: 1560 },
            { day: 2, closed: true, opens_minute: null, closes_minute: null },
            { day: 3, closed: false, opens_minute: 600, closes_minute: 1440 },
            { day: 4, closed: false, opens_minute: 540, closes_minute: 1380 },
            { day: 5, closed: false, opens_minute: 540, closes_minute: 1380 },
            { day: 6, closed: false, opens_minute: 540, closes_minute: 1380 },
            { day: 7, closed: false, opens_minute: 540, closes_minute: 1380 },
        ]);

        expect(draft?.[0]).toEqual({ day: 1, closed: false, opens: '18:00', closes: '02:00' });
        expect(draft?.[2]).toEqual({ day: 3, closed: false, opens: '10:00', closes: '00:00' });
    });
});

describe('entriesFromDraft — sunucuya giden hafta', () => {
    it('kapalı günü saatsiz gönderir', () => {
        const entries = entriesFromDraft([
            { day: 1, closed: true, opens: '09:00', closes: '23:00' },
            ...WEEK.slice(1),
        ]);

        expect(entries[0]).toEqual({
            day: 1,
            closed: true,
            opens_minute: null,
            closes_minute: null,
        });
    });

    /**
     * Sahip "18:00" ve "02:00" yazar; kaydedilen 1080 ve 1560'tır. Kapanışı
     * 120 olarak göndermek, kapanışı açılıştan önceye koyar ve sunucu haklı
     * olarak reddederdi.
     */
    it('sabaha sarkan kapanışı ertesi güne taşır', () => {
        const entries = entriesFromDraft([
            { day: 1, closed: false, opens: '18:00', closes: '02:00' },
            ...WEEK.slice(1),
        ]);

        expect(entries[0]).toMatchObject({ opens_minute: 1080, closes_minute: 1560 });
    });

    it('gece yarısı kapanışını 1440 yapar — sıfır değil', () => {
        const entries = entriesFromDraft([
            { day: 1, closed: false, opens: '10:00', closes: '00:00' },
            ...WEEK.slice(1),
        ]);

        expect(entries[0]).toMatchObject({ opens_minute: 600, closes_minute: 1440 });
    });
});

describe('OpeningHoursFields — ekran', () => {
    it('saat yokken yedi satır dayatmaz', () => {
        render(<OpeningHoursFields idPrefix="loc-1" draft={null} onChange={vi.fn()} />);

        expect(screen.queryByLabelText(/Monday/)).not.toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: 'This location has opening hours' }),
        ).not.toBeChecked();
    });

    it('işaretlenince haftayı ÖNERİLEN saatlerle açar', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();

        render(<OpeningHoursFields idPrefix="loc-1" draft={null} onChange={onChange} />);

        await user.click(screen.getByRole('checkbox', { name: 'This location has opening hours' }));

        expect(onChange).toHaveBeenCalledWith(suggestedDraft());
        expect(suggestedDraft()).toHaveLength(7);
    });

    it('haftayı yedi günün adıyla çizer', () => {
        render(<OpeningHoursFields idPrefix="loc-1" draft={WEEK} onChange={vi.fn()} />);

        for (const name of [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ]) {
            expect(screen.getByRole('group', { name })).toBeInTheDocument();
        }
    });

    /**
     * Kapalı bir günün saat alanları KAPANIR. Açık kalsaydı, sahip
     * pazartesiye saat yazıp "kapalı" işaretler, kaydeder ve ekranda iki
     * çelişen şey görürdü.
     */
    it('kapalı günün saat alanlarını kapatır', () => {
        const draft = [{ day: 1, closed: true, opens: '09:00', closes: '23:00' }, ...WEEK.slice(1)];

        render(<OpeningHoursFields idPrefix="loc-1" draft={draft} onChange={vi.fn()} />);

        const monday = screen.getByRole('group', { name: 'Monday' });

        expect(within(monday).getByLabelText('Opens')).toBeDisabled();
        expect(within(monday).getByLabelText('Closes')).toBeDisabled();
    });

    it('ertesi güne taşan kapanışı satırda söyler', () => {
        const draft = [
            { day: 1, closed: false, opens: '18:00', closes: '02:00' },
            ...WEEK.slice(1),
        ];

        render(<OpeningHoursFields idPrefix="loc-1" draft={draft} onChange={vi.fn()} />);

        expect(
            within(screen.getByRole('group', { name: 'Monday' })).getByText('next day'),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('group', { name: 'Tuesday' })).queryByText('next day'),
        ).not.toBeInTheDocument();
    });

    /** Jeton kökünü atlayan sınıflar bu depoda yasaktır (`docs/36` §5). */
    it('jeton kökünü atlayan sınıf taşımaz', () => {
        const { container } = render(
            <OpeningHoursFields idPrefix="loc-1" draft={WEEK} onChange={vi.fn()} />,
        );

        const markup = container.innerHTML;

        expect(markup).not.toMatch(/\bfont-semibold\b/);
        expect(markup).not.toMatch(/\brounded-full\b/);
        expect(markup).not.toMatch(
            /\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/,
        );
        expect(markup).not.toMatch(/\b(?:ml|mr|pl|pr|left|right|text-left|text-right)-\d/);
    });
});
