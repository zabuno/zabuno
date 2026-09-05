/**
 * ŞUBE ÇALIŞMA SAATLERİ — okuma, yazma ve KARTIN TEK SATIRI.
 *
 * Sunucu haftayı GÜN GÜN taşır (`opening_hours`, `docs/109` §6.4); kaynağın
 * kartı ise tek bir aralık gösterir (`panel.dc.html`, "Şubeler"). Aradaki
 * çeviri buradadır ve bilerek kartın DIŞINDADIR: "hafta tek tip mi", "bugün
 * ne yazıyor", "gece yarısı nasıl gösterilir" soruları ekrana bakmadan
 * doğrulanabilmeli.
 *
 * SAAT BİRİMİ DAKİKADIR ve gün başından sayılır (09:00 → 540). Kapanış
 * gece yarısını aşabilir ve 1440'ı geçer (10:00–00:00 → 1440,
 * 18:00–02:00 → 1560). Aynı birim menü servis aralıklarında da kullanılıyor,
 * yani depo tek bir saat dili konuşur.
 */

/** Sunucudan gelen bir günün kaydı. */
export type OpeningHoursEntry = {
    /** ISO-8601: 1 = Pazartesi … 7 = Pazar. */
    day: number;
    closed: boolean;
    opens_minute: number | null;
    closes_minute: number | null;
};

/**
 * Kartın çizeceği özet. `null` "söylenmemiş" demektir ve kart o satırı HİÇ
 * çizmez — uydurma bir varsayılan yoktur.
 */
export type OpeningHoursSummary =
    /** Yedi gün aynı aralık, kapalı gün yok: aralık her gün doğrudur. */
    | { kind: 'always'; range: string }
    /** Hafta değişiyor: tek bir aralık yalan olurdu, BUGÜN söylenir. */
    | { kind: 'today'; range: string }
    | { kind: 'todayClosed' }
    | { kind: 'closedAllWeek' };

const MINUTES_PER_DAY = 1440;

/** Bir günün dakika sayısı; gece yarısı aşımı bunun üstüne eklenir. */
export const DAY_MINUTES = MINUTES_PER_DAY;

/** Haftanın günleri, ISO sırasıyla. */
export const ISO_DAYS = [1, 2, 3, 4, 5, 6, 7] as const;

/**
 * Dakikayı GÜNDELİK saate çevirir.
 *
 * 1440 ve üstü, saklamada gece yarısı aşımını ifade eder; ekranda ise saat
 * yine gündelik saattir. Kullanıcı "24:00" ya da "26:00" diye bir saat
 * bilmez.
 */
export function minuteToClock(minute: number): string {
    const wrapped = ((minute % MINUTES_PER_DAY) + MINUTES_PER_DAY) % MINUTES_PER_DAY;
    const hours = Math.floor(wrapped / 60);
    const minutes = wrapped % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

/** `<input type="time">` değerini dakikaya çevirir; okunamazsa `null`. */
export function clockToMinute(clock: string): number | null {
    const match = /^(\d{1,2}):(\d{2})$/.exec(clock.trim());

    if (match === null) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

/**
 * Kapanış saatini KAYDEDİLECEK dakikaya çevirir.
 *
 * Kullanıcı iki saat girer ve "ertesi gün" diye bir kutu işaretlemez.
 * Kapanış açılıştan erken ya da ona eşitse, tek makul okuma ertesi gündür:
 * 22:00'de açılıp 02:00'de kapanan bir yer sabah 02:00'de kapanır. Bunu
 * kullanıcıya sordurmak, herkesin bildiği bir şeyi form alanına
 * dönüştürmek olurdu.
 */
export function closingMinuteFrom(opensMinute: number, closesMinute: number): number {
    return closesMinute <= opensMinute ? closesMinute + MINUTES_PER_DAY : closesMinute;
}

const WEEKDAY_TO_ISO: Record<string, number> = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
};

/**
 * ŞUBENİN gününü bulur — tarayıcının değil.
 *
 * Aynı an, İstanbul'da pazartesi 12:00 iken Auckland'da salı 21:00'dir.
 * Sahibi nereden bakarsa baksın kart, şubenin kendi gününü göstermeli
 * (`locations.timezone`, `docs/62`).
 *
 * Saat dilimi tanınmazsa (bozuk kayıt, çok eski tarayıcı) tarayıcının
 * gününe düşülür: özet yanlış GÜN gösterebilir ama kart hiç çizilmemekten
 * iyidir ve yazılan aralık yine gerçek kayıttan gelir.
 */
function isoWeekdayIn(timezone: string, now: Date): number {
    try {
        const label = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            weekday: 'short',
        }).format(now);

        return WEEKDAY_TO_ISO[label] ?? ((now.getDay() + 6) % 7) + 1;
    } catch {
        return ((now.getDay() + 6) % 7) + 1;
    }
}

function rangeOf(entry: OpeningHoursEntry): string | null {
    if (entry.closed || entry.opens_minute === null || entry.closes_minute === null) {
        return null;
    }

    return `${minuteToClock(entry.opens_minute)}–${minuteToClock(entry.closes_minute)}`;
}

/**
 * Kartın çizeceği tek satırı üretir.
 *
 * Kural sırası önemlidir ve hepsi tek bir ilkeden gelir: KART YALNIZ
 * KANITLAYABİLDİĞİNİ SÖYLER.
 */
export function summarizeOpeningHours(
    hours: OpeningHoursEntry[] | undefined | null,
    timezone: string,
    now: Date,
): OpeningHoursSummary | null {
    if (!hours || hours.length === 0) {
        return null;
    }

    const openDays = hours.filter((entry) => !entry.closed);

    if (openDays.length === 0) {
        return { kind: 'closedAllWeek' };
    }

    const ranges = new Set(openDays.map((entry) => rangeOf(entry)));

    /*
        TEK TİP HAFTA: yedi günün tamamı açık ve aralıkları aynı. Kapalı bir
        gün varsa hafta tek tip DEĞİLDİR — "09:00–23:00" yazan bir kart,
        pazartesi kapalı olan bir restoran için yalan söylerdi.
    */
    if (openDays.length === hours.length && ranges.size === 1) {
        const [only] = [...ranges];

        if (typeof only === 'string') {
            return { kind: 'always', range: only };
        }
    }

    const today = isoWeekdayIn(timezone, now);
    const entry = hours.find((row) => row.day === today);

    if (entry === undefined || entry.closed) {
        return { kind: 'todayClosed' };
    }

    const range = rangeOf(entry);

    return range === null ? { kind: 'todayClosed' } : { kind: 'today', range };
}

/** Formun ekranda tuttuğu bir gün: saatler `<input type="time">` biçiminde. */
export type OpeningHoursDraft = {
    /** ISO-8601: 1 = Pazartesi … 7 = Pazar. */
    day: number;
    closed: boolean;
    opens: string;
    closes: string;
};

/**
 * ÖNERİLEN hafta: yedi gün 09:00–23:00.
 *
 * Boş yedi satır açmak, sahibi kendi girmediği bir 422'ye sürerdi.
 * Öneri ile seçim arasındaki fark, önerinin EKRANDA duruyor olmasıdır —
 * aynı yaklaşım saat dilimi alanında da var (`RegionalFields`).
 */
export function suggestedDraft(): OpeningHoursDraft[] {
    return ISO_DAYS.map((day) => ({ day, closed: false, opens: '09:00', closes: '23:00' }));
}

/**
 * Sunucudan gelen haftayı forma çevirir. Saat girilmemişse `null` — yedi
 * boş satır değil.
 */
export function draftFromEntries(
    entries: OpeningHoursEntry[] | undefined | null,
): OpeningHoursDraft[] | null {
    if (!entries || entries.length === 0) {
        return null;
    }

    const byDay = new Map(entries.map((entry) => [entry.day, entry]));

    return ISO_DAYS.map((day) => {
        const entry = byDay.get(day);

        if (entry === undefined || entry.closed) {
            return { day, closed: true, opens: '09:00', closes: '23:00' };
        }

        return {
            day,
            closed: false,
            // Gece yarısı aşımı SAKLAMADA 1440'ı geçer; ekranda gündelik
            // saattir. Kimse "26:00" diye bir saat yazmaz.
            opens: minuteToClock(entry.opens_minute ?? 0),
            closes: minuteToClock(entry.closes_minute ?? 0),
        };
    });
}

/**
 * Formu sunucunun beklediği haftaya çevirir.
 *
 * Okunamayan bir saat 0'a düşürülmez, olduğu gibi bırakılmaz: kapanış
 * hesabı `closingMinuteFrom` üstünden geçtiği için "18:00 → 02:00" doğru
 * biçimde 1080 → 1560 olur. Okunamayan girdi `null` döner ve sunucu
 * reddeder — sessizce yanlış bir saat kaydetmektense reddedilmek iyidir.
 */
export function entriesFromDraft(draft: OpeningHoursDraft[]): OpeningHoursEntry[] {
    return draft.map((day) => {
        if (day.closed) {
            return { day: day.day, closed: true, opens_minute: null, closes_minute: null };
        }

        const opens = clockToMinute(day.opens);
        const closes = clockToMinute(day.closes);

        if (opens === null || closes === null) {
            return { day: day.day, closed: false, opens_minute: opens, closes_minute: closes };
        }

        return {
            day: day.day,
            closed: false,
            opens_minute: opens,
            closes_minute: closingMinuteFrom(opens, closes),
        };
    });
}
