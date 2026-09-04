/*
 * NOT (2026-08-26): tema seçimi `radiogroup`'a taşındı. Altı butonda
 * `aria-pressed`, ekran okuyucuya "altı ayrı anahtar" der; oysa kullanıcı
 * altı seçenekten BİRİNİ seçer. `radio` rolü bunu doğru anlatır ve
 * "6 seçenekten 2." bilgisini taşır. Donan davranış (tek seçim, varsayılan
 * classic, URL'e yansıması) değişmedi; yalnız doğru rol kullanılıyor.
 */
/*
 * FF-107 (2026-09-04, sahibin bildirimi: "bu sayfa atıl kalmış").
 *
 * Bu paketten önce bu dosya, ekranın ÖLÜ KONTROLLERİNİ sözleşme olarak
 * donduruyordu: tek seçenekli devre dışı bir "hedef türü", hiçbir zaman
 * etkinleşmeyen bir "bulk range" alanı, kalıcı devre dışı bir "Export"
 * düğmesi ve PNG/SVG'de devre dışı çizilen kâğıt/yön alanları.
 *
 * Devre dışı bir kontrol ekranda yer kaplar, okunur, tıklanır ve hiçbir şey
 * yapmaz — kullanıcı onu "bozuk" diye öğrenir. Tek seçenekli bir açılır liste
 * ise soru sormayan bir sorudur.
 *
 * Yeni sözleşme: olmayan seçenek ÇİZİLMEZ. Kâğıt ve yön yalnız PDF'te
 * görünür; hedef türü, bulk alanı ve ölü Export düğmesi kaldırıldı. Gerçek
 * yetenekler (üç biçim, sekiz kâğıt, iki yön, altı tema) aynen ölçülmeye
 * devam ediyor.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrPrintExportRegion } from './QrPrintExportRegion';

/**
 * QR_EXPORT_CONFIG_RED
 *
 * RED suite for the QR export configuration form described in the delivery
 * contract. QrPrintExportRegion today renders static format/theme lists and
 * two disabled buttons — it has no accessible configuration group, no
 * destination/output/paper size/orientation selects and no bulk range input.
 * These assertions must fail against current production. No production,
 * i18n, Storybook, backend or Git edits are made from this file.
 *
 * Extended for S1-WP04b2 (validated PNG QR export, frozen MASTER contract):
 * Published is now the fixed (non-selectable, single-option) destination —
 * "Menu category" is not a real backend destination yet and must not be
 * offered as a choice. PNG is the only enabled, real output format; SVG and
 * PDF remain visibly present but disabled. Paper size, orientation, and bulk
 * stay disabled — those guards below are intentionally unchanged.
 *
 * Extended for S1-WP04b3 (real SVG QR export, frozen MASTER contract): SVG
 * is now a real backend capability (GET .../export.svg) and must be
 * selectable in the Output format select alongside PNG. PDF remains the
 * only disabled option — there is still no real backend capability for it.
 * The select still defaults to PNG.
 *
 * Extended for S1-WP01A (first real server-side single-QR A4 portrait PDF
 * export, frozen MASTER contract): PDF is now also a real backend
 * capability (GET .../export.pdf) and must be selectable in the Output
 * format select alongside PNG and SVG — no disabled format remains. The
 * select still defaults to PNG.
 *
 * Extended for S1-WP04b5 (real ISO 216 paper size + orientation PDF export,
 * frozen MASTER contract): Paper size and Orientation stay disabled while
 * Output format is PNG or SVG, but become enabled — with defaults A4 /
 * Portrait, all 8 paper options and both orientations still present — as
 * soon as PDF is selected. Selecting PDF and controlling these selects must
 * never trigger a fetch/blob. Switching back to PNG/SVG must disable them
 * again. Today Paper size and Orientation are unconditionally disabled
 * regardless of the selected output format, so these assertions fail
 * against current production.
 *
 * Extended for S1-WP04b6 (six stateless basic QR themes, frozen MASTER
 * contract): the six theme buttons are now a real, enabled, stateless
 * selection control — not a disabled placeholder list. Exactly one carries
 * aria-checked="true" at a time, classic by default. Today the buttons are
 * unconditionally disabled and none carries aria-checked, so this assertion
 * fails against current production.
 */

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

/*
    AYARLAR ARTIK BİR KODUN AYARIDIR (FF-107).

    Önceden biçim/kâğıt/yön formu, hiç QR kodu yokken de çiziliyordu: var
    olmayan bir şeyin ayarları. Artık form seçili kodun yanında yaşıyor, o
    yüzden bu testler gerçek bir kodla çiziyor.
*/
const ACTIVE_ITEM = {
    id: 1,
    workspaceId: 7,
    locationId: 3,
    menuId: 11,
    destinationType: 'published',
    token: 'tkn-1',
    resolverUrl: 'https://zabuno.com/q/tkn-1',
    state: 'active' as const,
};

function renderRegion() {
    return render(<QrPrintExportRegion items={[ACTIVE_ITEM]} />);
}

describe('QrPrintExportRegion configuration form (QR_EXPORT_CONFIG_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes an accessible QR export configuration group', () => {
        renderRegion();

        expect(screen.getByRole('group', { name: /qr export configuration/i })).toBeInTheDocument();
    });

    it('renders an enabled Output format select with PNG, SVG and PDF all selectable and none disabled', () => {
        renderRegion();

        const outputFormat = screen.getByLabelText(/output format/i) as HTMLSelectElement;
        expect(outputFormat).toBeEnabled();

        const options = within(outputFormat).getAllByRole('option') as HTMLOptionElement[];
        expect(options.map((option) => option.textContent)).toEqual(['PNG', 'SVG', 'PDF']);

        const png = options.find((option) => option.textContent === 'PNG');
        const svg = options.find((option) => option.textContent === 'SVG');
        const pdf = options.find((option) => option.textContent === 'PDF');

        expect(png).not.toBeDisabled();
        expect(svg).not.toBeDisabled();
        expect(pdf).not.toBeDisabled();
        expect(outputFormat.value).toBe(png?.value ?? 'PNG');
    });

    it('PDF seçilince kâğıt boyu tam olarak A4, B4, A5, B5, A6, B6, A7, B7 sırasını taşır', async () => {
        const user = userEvent.setup();
        renderRegion();

        /*
            FF-107: kâğıt ve yön artık PNG/SVG'de DEVRE DIŞI değil, HİÇ
            ÇİZİLMİYOR. Ölçülen sözleşme aynı: bu iki alan yalnız PDF'in
            özelliğidir ve PDF seçilene kadar bir soru sormazlar.
        */
        await user.selectOptions(screen.getByLabelText(/output format/i), 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        expect(paperSize).toBeEnabled();

        const options = within(paperSize).getAllByRole('option');
        expect(options.map((option) => option.textContent)).toEqual([
            'A4',
            'B4',
            'A5',
            'B5',
            'A6',
            'B6',
            'A7',
            'B7',
        ]);
    });

    it('PDF seçilince yön tam olarak Portrait, Landscape taşır', async () => {
        const user = userEvent.setup();
        renderRegion();

        await user.selectOptions(screen.getByLabelText(/output format/i), 'pdf');

        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;
        expect(orientation).toBeEnabled();

        const options = within(orientation).getAllByRole('option');
        expect(options.map((option) => option.textContent)).toEqual(['Portrait', 'Landscape']);
    });

    it('renders the six theme buttons visible and enabled, with classic pressed by default, and no second theme select', () => {
        renderRegion();

        const themeButtons = screen.getAllByRole('radio', { name: /theme$/i });
        expect(themeButtons).toHaveLength(6);
        themeButtons.forEach((button) => {
            expect(button).toBeVisible();
            expect(button).toBeEnabled();
        });

        const pressed = themeButtons.filter(
            (button) => button.getAttribute('aria-checked') === 'true',
        );
        expect(pressed).toHaveLength(1);
        expect(pressed[0]).toHaveAccessibleName(/^classic theme$/i);

        expect(screen.queryByLabelText(/theme/i, { selector: 'select' })).not.toBeInTheDocument();
    });

    it('performs zero fetch calls on mount', () => {
        renderRegion();

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('renders a configuration subtree free of images, urls, tokens, ids, versions, artifacts, numeric defaults, fixed-pixel or breakpoint classes', () => {
        renderRegion();

        const configGroup = screen.getByRole('group', { name: /qr export configuration/i });

        expect(configGroup.querySelectorAll('img').length).toBe(0);

        const text = configGroup.textContent ?? '';
        expect(text).not.toMatch(/https?:\/\//i);
        expect(text).not.toMatch(/\btoken\b/i);
        expect(text).not.toMatch(/\bid\s*[:=]/i);
        expect(text).not.toMatch(/\bv\d+(\.\d+)*\b/i);
        expect(text).not.toMatch(/\bgenerated\b/i);

        const numericInputs = configGroup.querySelectorAll('input');
        numericInputs.forEach((input) => {
            expect(input.value).toBe('');
        });

        const classLists = collectClassLists(configGroup as HTMLElement);
        classLists.forEach((classList) => {
            expect(classList).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        });
    });
});

describe('QrPrintExportRegion paper size and orientation enable only for PDF (QR_PDF_PAPER_ORIENTATION_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('PNG seçiliyken kâğıt ve yön HİÇ ÇİZİLMEZ', () => {
        renderRegion();

        /*
            FF-107: kâğıt ve yön artık PNG/SVG'de DEVRE DIŞI değil, HİÇ
            ÇİZİLMİYOR. Ölçülen sözleşme aynı: bu iki alan yalnız PDF'in
            özelliğidir ve PDF seçilene kadar bir soru sormazlar.
        */
        expect(screen.queryByLabelText(/paper size/i)).toBeNull();
        expect(screen.queryByLabelText(/orientation/i)).toBeNull();
    });

    it('SVG seçiliyken de kâğıt ve yön çizilmez', async () => {
        const user = userEvent.setup();
        renderRegion();

        await user.selectOptions(screen.getByLabelText(/output format/i), 'svg');

        expect(screen.queryByLabelText(/paper size/i)).toBeNull();
        expect(screen.queryByLabelText(/orientation/i)).toBeNull();
    });

    it('enables Paper size and Orientation, still defaulting to A4/Portrait with all 8 sizes and both orientations, once PDF is selected', async () => {
        const user = userEvent.setup();
        renderRegion();

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        expect(paperSize).toBeEnabled();
        expect(orientation).toBeEnabled();
        expect(paperSize.value).toBe('A4');
        expect(orientation.value.toLowerCase()).toBe('portrait');

        const paperSizeOptions = within(paperSize).getAllByRole('option');
        expect(paperSizeOptions.map((option) => option.textContent)).toEqual([
            'A4',
            'B4',
            'A5',
            'B5',
            'A6',
            'B6',
            'A7',
            'B7',
        ]);

        const orientationOptions = within(orientation).getAllByRole('option');
        expect(orientationOptions.map((option) => option.textContent)).toEqual([
            'Portrait',
            'Landscape',
        ]);
    });

    it('allows selecting a nondefault Paper size and Orientation once PDF is selected, as a controlled select', async () => {
        const user = userEvent.setup();
        renderRegion();

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        await user.selectOptions(paperSize, 'B7');
        await user.selectOptions(orientation, 'Landscape');

        expect(paperSize.value).toBe('B7');
        expect(orientation.value.toLowerCase()).toBe('landscape');
    });

    it("PDF'den PNG'ye dönünce kâğıt ve yön yeniden ÇİZİLMEZ ve varsayılana döner", async () => {
        const user = userEvent.setup();
        renderRegion();

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');
        await user.selectOptions(screen.getByLabelText(/paper size/i), 'A6');

        await user.selectOptions(outputFormat, 'png');

        // Alanlar kalkar…
        expect(screen.queryByLabelText(/paper size/i)).toBeNull();
        expect(screen.queryByLabelText(/orientation/i)).toBeNull();

        // …ve tekrar PDF seçilince kullanıcının eski seçimi DEĞİL, güvenli
        // varsayılan gelir: A6 bir masa kartı için fazla küçüktür ve sessizce
        // hatırlanması, sahibin farkında olmadan onu basması demekti.
        await user.selectOptions(outputFormat, 'pdf');
        expect((screen.getByLabelText(/paper size/i) as HTMLSelectElement).value).toBe('A4');
        expect(
            (screen.getByLabelText(/orientation/i) as HTMLSelectElement).value.toLowerCase(),
        ).toBe('portrait');
    });
    it('performs zero fetch calls while switching output format and controlling paper size/orientation', async () => {
        const user = userEvent.setup();
        renderRegion();

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;
        await user.selectOptions(paperSize, 'B7');
        await user.selectOptions(orientation, 'Landscape');

        expect(fetchSpy).not.toHaveBeenCalled();
    });
});
