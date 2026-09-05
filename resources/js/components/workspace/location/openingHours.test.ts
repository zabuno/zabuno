import { describe, expect, it } from 'vitest';

import {
    clockToMinute,
    closingMinuteFrom,
    minuteToClock,
    summarizeOpeningHours,
    type OpeningHoursEntry,
} from './openingHours';

/**
 * ŞUBE ÇALIŞMA SAATLERİNİN OKUNMASI VE ÖZETLENMESİ — `docs/109` §6.4.
 *
 * NEDEN KIRMIZI: bu modül yok. Sunucu artık haftayı gün gün taşıyor
 * (`opening_hours`), kaynağın kartı ise TEK aralık gösteriyor
 * (`panel.dc.html`, "Şubeler"). Aradaki çeviriyi bir yerin yapması gerek ve
 * o yer kartın içi olamaz: çeviri kuralları (tek tip hafta mı, bugün ne
 * yazıyor, gece yarısı nasıl gösterilir) ekrana bakmadan doğrulanabilmeli.
 *
 * ÖZETİN NAMUSU:
 *   - Hafta HİÇ girilmemişse özet YOKTUR ve kart o satırı çizmez. Uydurma
 *     bir "09:00–23:00" yazmak, sahibin hiç söylemediği bir iddiayı
 *     ekranda doğruymuş gibi göstermek olurdu.
 *   - Hafta TEK TİPSE (yedi gün aynı aralık, kapalı gün yok) aralık
 *     koşulsuz yazılır: her gün doğrudur.
 *   - Hafta DEĞİŞİYORSA tek bir aralık yazmak yalan olur; özet o zaman
 *     BUGÜNÜ söyler ve bunu açıkça belirtir ("Today …" / "Closed today").
 *   - "Bugün" ŞUBENİN saat dilimine göredir (`locations.timezone`,
 *     `docs/62`). Tarayıcının saatine göre hesaplansaydı, Berlin'den
 *     bakan bir sahip İstanbul şubesinin gününü yanlış görürdü.
 */
const NINE_TO_ELEVEN = { opens_minute: 540, closes_minute: 1380 };

function week(overrides: Partial<Record<number, OpeningHoursEntry>> = {}): OpeningHoursEntry[] {
    const days: OpeningHoursEntry[] = [];

    for (let day = 1; day <= 7; day++) {
        days.push(overrides[day] ?? { day, closed: false, ...NINE_TO_ELEVEN });
    }

    return days;
}

/**
 * 2026-09-07 bir PAZARTESİDİR. Bu an İstanbul'da pazartesi 16:00,
 * Auckland'da ise SALI 01:00 — testin saat dilimi iddiası tam olarak bu
 * farkın üstünde durur.
 */
const MONDAY_AFTERNOON = new Date('2026-09-07T13:00:00Z');

describe('minuteToClock — dakikadan saate', () => {
    it('gün içindeki dakikayı saat olarak yazar', () => {
        expect(minuteToClock(540)).toBe('09:00');
        expect(minuteToClock(1380)).toBe('23:00');
        expect(minuteToClock(0)).toBe('00:00');
    });

    /**
     * Gece yarısı aşımı SAKLANIRKEN 1440'ı geçer (10:00–00:00 → 1440,
     * 18:00–02:00 → 1560) ama ekranda saat yine gündelik saattir. Kullanıcı
     * "24:00" ya da "26:00" diye bir saat bilmez.
     */
    it('gece yarısını aşan dakikayı gündelik saate indirir', () => {
        expect(minuteToClock(1440)).toBe('00:00');
        expect(minuteToClock(1560)).toBe('02:00');
    });
});

describe('clockToMinute — saatten dakikaya', () => {
    it('saati dakikaya çevirir', () => {
        expect(clockToMinute('09:00')).toBe(540);
        expect(clockToMinute('23:30')).toBe(1410);
    });

    it('okunamayan girdiye null der', () => {
        expect(clockToMinute('')).toBeNull();
        expect(clockToMinute('akşam')).toBeNull();
    });
});

describe('closingMinuteFrom — kapanış ertesi güne taşabilir', () => {
    /**
     * Kullanıcı iki saat girer ve "ertesi gün" diye bir kutu işaretlemez.
     * Kapanış açılıştan ERKEN ya da ona EŞİTSE, tek makul okuma ertesi
     * gündür: 22:00'de açılıp 02:00'de kapanan bir yer, sabah 02:00'de
     * kapanır. Bunu kullanıcıya sordurmak, herkesin bildiği bir şeyi
     * form alanına dönüştürmek olurdu.
     */
    it('kapanış açılıştan sonraysa olduğu gibi kalır', () => {
        expect(closingMinuteFrom(540, 1380)).toBe(1380);
    });

    it('gece yarısı kapanışı 1440 olur — sıfır değil', () => {
        expect(closingMinuteFrom(600, 0)).toBe(1440);
    });

    it('sabaha sarkan kapanış ertesi güne taşınır', () => {
        expect(closingMinuteFrom(1080, 120)).toBe(1560);
    });
});

describe('summarizeOpeningHours — kartın tek satırı', () => {
    it('hafta girilmemişse özet YOKTUR', () => {
        expect(summarizeOpeningHours([], 'Europe/Istanbul', MONDAY_AFTERNOON)).toBeNull();
        expect(summarizeOpeningHours(undefined, 'Europe/Istanbul', MONDAY_AFTERNOON)).toBeNull();
    });

    it('tek tip haftada aralığı koşulsuz yazar', () => {
        expect(summarizeOpeningHours(week(), 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'always',
            range: '09:00–23:00',
        });
    });

    /**
     * Bir gün kapalıysa hafta artık tek tip değildir: "09:00–23:00" yazan
     * bir kart, pazartesi kapalı olan bir restoran için YALAN söyler.
     */
    it('kapalı bir gün varsa tek tip saymaz, bugünü söyler', () => {
        const hours = week({
            3: { day: 3, closed: true, opens_minute: null, closes_minute: null },
        });

        expect(summarizeOpeningHours(hours, 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'today',
            range: '09:00–23:00',
        });
    });

    it('bugün kapalıysa bunu söyler', () => {
        const hours = week({
            1: { day: 1, closed: true, opens_minute: null, closes_minute: null },
        });

        expect(summarizeOpeningHours(hours, 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'todayClosed',
        });
    });

    it('haftanın tamamı kapalıysa aralık uydurmaz', () => {
        const hours = week(
            Object.fromEntries(
                [1, 2, 3, 4, 5, 6, 7].map((day) => [
                    day,
                    { day, closed: true, opens_minute: null, closes_minute: null },
                ]),
            ),
        );

        expect(summarizeOpeningHours(hours, 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'closedAllWeek',
        });
    });

    it('gece yarısını aşan aralığı gündelik saatlerle yazar', () => {
        const hours = week(
            Object.fromEntries(
                [1, 2, 3, 4, 5, 6, 7].map((day) => [
                    day,
                    { day, closed: false, opens_minute: 600, closes_minute: 1440 },
                ]),
            ),
        );

        expect(summarizeOpeningHours(hours, 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'always',
            range: '10:00–00:00',
        });
    });

    /**
     * "Bugün" ŞUBENİN saat dilimindedir. Aynı an, İstanbul'da pazartesi
     * 16:00 iken Auckland'da salı 01:00'dir; sahibi nereden bakarsa baksın
     * kart şubenin kendi gününü göstermeli.
     */
    it('bugünü şubenin saat dilimine göre bulur', () => {
        const hours = week({
            1: { day: 1, closed: true, opens_minute: null, closes_minute: null },
            2: { day: 2, closed: false, opens_minute: 600, closes_minute: 1200 },
        });

        expect(summarizeOpeningHours(hours, 'Europe/Istanbul', MONDAY_AFTERNOON)).toEqual({
            kind: 'todayClosed',
        });
        expect(summarizeOpeningHours(hours, 'Pacific/Auckland', MONDAY_AFTERNOON)).toEqual({
            kind: 'today',
            range: '10:00–20:00',
        });
    });
});
