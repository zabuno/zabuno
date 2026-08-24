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
 * aria-pressed="true" at a time, classic by default. Today the buttons are
 * unconditionally disabled and none carries aria-pressed, so this assertion
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
        render(<QrPrintExportRegion />);

        expect(screen.getByRole('group', { name: /qr export configuration/i })).toBeInTheDocument();
    });

    it('fixes the Destination type to the single, non-selectable Published option (no Menu category choice)', () => {
        render(<QrPrintExportRegion />);

        const destination = screen.getByLabelText(/destination type/i) as HTMLSelectElement;
        expect(destination).toBeDisabled();

        const options = within(destination).getAllByRole('option');
        expect(options.map((option) => option.textContent)).toEqual(['Published']);
        expect(destination.value).toBe(options[0].getAttribute('value') ?? options[0].textContent);
    });

    it('renders an enabled Output format select with PNG, SVG and PDF all selectable and none disabled', () => {
        render(<QrPrintExportRegion />);

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

    it('renders a disabled Paper size select with exactly A4, B4, A5, B5, A6, B6, A7, B7 in order', () => {
        render(<QrPrintExportRegion />);

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        expect(paperSize).toBeDisabled();

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

    it('renders a disabled Orientation select with exactly Portrait, Landscape', () => {
        render(<QrPrintExportRegion />);

        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;
        expect(orientation).toBeDisabled();

        const options = within(orientation).getAllByRole('option');
        expect(options.map((option) => option.textContent)).toEqual(['Portrait', 'Landscape']);
    });

    it('renders a disabled empty Bulk range or count input with no default value', () => {
        render(<QrPrintExportRegion />);

        const bulk = screen.getByLabelText(/bulk range or count/i) as HTMLInputElement;
        expect(bulk).toBeDisabled();
        expect(bulk.value).toBe('');
    });

    it('renders the six theme buttons visible and enabled, with classic pressed by default, and no second theme select', () => {
        render(<QrPrintExportRegion />);

        const themeButtons = screen.getAllByRole('button', { name: /theme$/i });
        expect(themeButtons).toHaveLength(6);
        themeButtons.forEach((button) => {
            expect(button).toBeVisible();
            expect(button).toBeEnabled();
        });

        const pressed = themeButtons.filter(
            (button) => button.getAttribute('aria-pressed') === 'true',
        );
        expect(pressed).toHaveLength(1);
        expect(pressed[0]).toHaveAccessibleName(/^classic theme$/i);

        expect(screen.queryByLabelText(/theme/i, { selector: 'select' })).not.toBeInTheDocument();
    });

    it('keeps the generic Export trigger disabled — PNG delivery is a direct img/download link, not a fetch-driven export button', () => {
        render(<QrPrintExportRegion />);

        expect(screen.getByRole('button', { name: /^export$/i })).toBeDisabled();
    });

    it('performs zero fetch calls on mount', () => {
        render(<QrPrintExportRegion />);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('renders a configuration subtree free of images, urls, tokens, ids, versions, artifacts, numeric defaults, fixed-pixel or breakpoint classes', () => {
        render(<QrPrintExportRegion />);

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

    it('keeps Paper size and Orientation disabled, defaulting to A4/Portrait, while Output format is PNG', () => {
        render(<QrPrintExportRegion />);

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        expect(paperSize).toBeDisabled();
        expect(orientation).toBeDisabled();
        expect(paperSize.value).toBe('A4');
        expect(orientation.value.toLowerCase()).toBe('portrait');
    });

    it('keeps Paper size and Orientation disabled while Output format is SVG', async () => {
        const user = userEvent.setup();
        render(<QrPrintExportRegion />);

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'svg');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        expect(paperSize).toBeDisabled();
        expect(orientation).toBeDisabled();
    });

    it('enables Paper size and Orientation, still defaulting to A4/Portrait with all 8 sizes and both orientations, once PDF is selected', async () => {
        const user = userEvent.setup();
        render(<QrPrintExportRegion />);

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
        render(<QrPrintExportRegion />);

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        await user.selectOptions(paperSize, 'B7');
        await user.selectOptions(orientation, 'Landscape');

        expect(paperSize.value).toBe('B7');
        expect(orientation.value.toLowerCase()).toBe('landscape');
    });

    it('disables Paper size and Orientation again, resetting them to A4/Portrait, after switching back from PDF to PNG', async () => {
        const user = userEvent.setup();
        render(<QrPrintExportRegion />);

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;

        await user.selectOptions(paperSize, 'B7');
        await user.selectOptions(orientation, 'Landscape');

        await user.selectOptions(outputFormat, 'png');

        expect(paperSize).toBeDisabled();
        expect(orientation).toBeDisabled();
        expect(paperSize.value).toBe('A4');
        expect(orientation.value.toLowerCase()).toBe('portrait');
    });

    it('performs zero fetch calls while switching output format and controlling paper size/orientation', async () => {
        const user = userEvent.setup();
        render(<QrPrintExportRegion />);

        const outputFormat = screen.getByLabelText(/output format/i);
        await user.selectOptions(outputFormat, 'pdf');

        const paperSize = screen.getByLabelText(/paper size/i) as HTMLSelectElement;
        const orientation = screen.getByLabelText(/orientation/i) as HTMLSelectElement;
        await user.selectOptions(paperSize, 'B7');
        await user.selectOptions(orientation, 'Landscape');

        expect(fetchSpy).not.toHaveBeenCalled();
    });
});
