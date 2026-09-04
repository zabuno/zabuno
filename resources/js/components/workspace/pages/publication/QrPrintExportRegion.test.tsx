/*
 * NOT (2026-08-26): tema seçimi `radiogroup`'a taşındı. Altı butonda
 * `aria-pressed`, ekran okuyucuya "altı ayrı anahtar" der; oysa kullanıcı
 * altı seçenekten BİRİNİ seçer. `radio` rolü bunu doğru anlatır ve
 * "6 seçenekten 2." bilgisini taşır. Donan davranış (tek seçim, varsayılan
 * classic, URL'e yansıması) değişmedi; yalnız doğru rol kullanılıyor.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrPrintExportRegion } from './QrPrintExportRegion';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * BULK_QR_WIZARD_RED
 *
 * RED suite for the Bulk QR wizard described in the delivery contract.
 * QrPrintExportRegion today has no accessible "Bulk QR wizard" fieldset and
 * none of the six labeled inputs below — these assertions must fail against
 * current production. No production, i18n, Storybook, backend or Git edits
 * are made from this file.
 */

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

/*
    FF-85 (`docs/101` Y5): sıra değişti. Kebapçıya sorulan TEK soru masa
    sayısıdır ve ilk sırada durur; varsayılanı olan beş alan "ileri" başlığı
    altında, DOM'da aynı sırayla kalır (klavye ve ekran okuyucu için).
*/
const WIZARD_FIELD_LABELS = [
    /table count/i,
    /area\/section count/i,
    /seat count per table/i,
    /naming prefix/i,
    /naming sequence start/i,
    /naming range/i,
];

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

describe('QrPrintExportRegion bulk QR wizard (BULK_QR_WIZARD_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes an accessible Bulk QR wizard group with all six labeled fields present in DOM order', () => {
        render(<QrPrintExportRegion />);

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });
        const fields = WIZARD_FIELD_LABELS.map((labelPattern) =>
            within(wizard).getByLabelText(labelPattern),
        );

        const allInputs = Array.from(wizard.querySelectorAll('input'));
        fields.forEach((field, index) => {
            expect(allInputs[index]).toBe(field);
        });
    });

    it('performs zero fetch calls on mount', () => {
        render(<QrPrintExportRegion />);

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('renders the wizard group free of breakpoint or fixed-pixel sizing classes at 320x480', () => {
        render(<QrPrintExportRegion />);

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });
        const classLists = collectClassLists(wizard as HTMLElement);

        classLists.forEach((classList) => {
            expect(classList).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        });
    });
});

/**
 * QR_PNG_EXPORT_RED — S1-WP04b2
 *
 * QrPrintExportRegion is compound: QrDestinationRegion's real, server-
 * returned QR code items drive this nested region. QrPrintExportRegion
 * today takes no `items` prop, renders zero real PNG previews and zero real
 * Download PNG links against the exact workspace/id export.png endpoint —
 * so every assertion below fails against current production, not from a
 * syntax/bootstrap defect in this suite. The browser must load the img/
 * download URLs directly: this region performs no fetch, no blob
 * generation, and no export mutation of its own.
 */
describe('QrPrintExportRegion real PNG preview and download (QR_PNG_EXPORT_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    function activeItem(id: number, workspaceId = 71): QrCodeItem {
        return {
            id,
            workspaceId,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };
    }

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows an honest state with no image and no download link when there is no active QR code item', () => {
        render(<QrPrintExportRegion items={[]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        expect(within(region).queryByRole('img')).toBeNull();
        expect(within(region).queryByRole('link', { name: /download png/i })).toBeNull();
        expect(
            within(region).getByText(/no active qr|none active|no qr code/i),
        ).toBeInTheDocument();
    });

    it('treats a list containing only disabled items as having no active QR code', () => {
        const disabled = { ...activeItem(900), state: 'disabled' };
        render(<QrPrintExportRegion items={[disabled]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        expect(within(region).queryByRole('img')).toBeNull();
        expect(within(region).queryByRole('link', { name: /download png/i })).toBeNull();
    });

    it('renders a real PNG preview image and a Download PNG link against the exact workspace/id export endpoint for a single active item', () => {
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png`,
        );

        const link = within(region).getByRole('link', { name: /download png/i });
        expect(link).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );
    });

    it('exposes an accessible selector defaulting to the first active item when multiple active QR codes exist', () => {
        const first = activeItem(900);
        const second = activeItem(901);
        render(<QrPrintExportRegion items={[first, second]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });

        const selector = within(region).getByRole('combobox', { name: /qr code/i });
        expect(selector).toBeInTheDocument();

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${first.workspaceId}/qr-codes/${first.id}/export.png`,
        );
    });

    it('excludes disabled items from the selector and still previews the sole active item', () => {
        const active = activeItem(900);
        const disabled = { ...activeItem(901), state: 'disabled' };
        render(<QrPrintExportRegion items={[disabled, active]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${active.workspaceId}/qr-codes/${active.id}/export.png`,
        );
    });

    it('performs zero fetch calls and no blob/export mutation of its own — the browser loads the img/download URLs directly', () => {
        render(<QrPrintExportRegion items={[activeItem(900)]} />);

        expect(fetchSpy).not.toHaveBeenCalled();
    });
});

/**
 * QR_SVG_EXPORT_RED — S1-WP04b3
 *
 * SVG is now a real backend capability (GET .../export.svg). Selecting SVG
 * in the Output format select must retarget the existing preview img/download
 * link to the real export.svg endpoint for the currently selected active QR
 * code item, and switching back to PNG must restore the export.png endpoint
 * — all without any client-side fetch/blob/export mutation. Today the
 * Output format select is disconnected from the preview/download URLs (they
 * are always export.png regardless of the select's value), so these
 * assertions fail against current production.
 */
describe('QrPrintExportRegion output format switching to real SVG export (QR_SVG_EXPORT_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    function activeItem(id: number, workspaceId = 71): QrCodeItem {
        return {
            id,
            workspaceId,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };
    }

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('retargets the preview src and download link to export.svg (and ?download=1) with an accessible Download SVG name after selecting SVG', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'svg');

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.svg`,
        );

        const svgDownloadLink = within(region).getByRole('link', { name: /download svg/i });
        expect(svgDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.svg?download=1`,
        );

        expect(
            within(region).queryByRole('link', { name: /download png/i }),
        ).not.toBeInTheDocument();
    });

    it('restores the export.png preview/download link after switching back from SVG to PNG', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'svg');
        await user.selectOptions(outputFormatSelect, 'png');

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png`,
        );

        const pngDownloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(pngDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );

        expect(
            within(region).queryByRole('link', { name: /download svg/i }),
        ).not.toBeInTheDocument();
    });

    it('performs zero fetch calls and no blob/export mutation of its own when switching output format', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'svg');

        expect(fetchSpy).not.toHaveBeenCalled();
    });
});

/**
 * QR_PDF_EXPORT_RED — S1-WP01A
 *
 * PDF is now a real backend capability (GET .../export.pdf), but unlike PNG
 * and SVG, a PDF document is not an image the browser can render inline —
 * so selecting PDF in the Output format select must remove the existing img
 * preview entirely and expose only a direct Download PDF link against the
 * real export.pdf?download=1 endpoint for the currently selected active QR
 * code item, with no fetch/blob generated client-side. Switching back to
 * PNG must restore the PNG preview img and its Download PNG link. Today the
 * Output format select is disconnected from the preview/download URLs (they
 * are always export.png regardless of the select's value) and PDF selection
 * has no effect at all, so these assertions fail against current
 * production.
 *
 * Extended for S1-WP04b5 (real ISO 216 paper size + orientation PDF export,
 * frozen MASTER contract): the Download PDF link's href must carry the
 * currently selected paperSize/orientation as query parameters alongside
 * download=1, defaulting to A4/portrait, and update live as those controls
 * change — still with no fetch/blob. Switching away from PDF must drop the
 * paperSize/orientation query entirely from the PNG/SVG links. Today the
 * Download PDF link carries no paperSize/orientation query at all, so these
 * assertions fail against current production.
 */
describe('QrPrintExportRegion output format switching to real PDF export (QR_PDF_EXPORT_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    function activeItem(id: number, workspaceId = 71): QrCodeItem {
        return {
            id,
            workspaceId,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };
    }

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('removes the img preview and shows only a direct Download PDF link against export.pdf?paperSize=A4&orientation=portrait&download=1 after selecting PDF', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');

        /*
            İDDİA HAM KODUN ÖNİZLEMESİNE AİT (FF-120).

            Bölgede artık ikinci bir görsel var: masaya konacak KARTIN
            önizlemesi. "Bölgede hiç resim yok" iddiası o kart geldiği anda
            yanlış olur ve ölçmek istediği şeyi ölçmez. Ölçülen sözleşme
            değişmedi: PDF seçildiğinde HAM KODUN önizlemesi kalkar, çünkü bir
            PDF `<img>` ile gösterilemez.
        */
        expect(within(region).queryByAltText(/qr code preview|karekod önizlemesi/i)).toBeNull();

        const pdfDownloadLink = within(region).getByRole('link', { name: /download pdf/i });
        expect(pdfDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=A4&orientation=portrait&download=1`,
        );

        expect(
            within(region).queryByRole('link', { name: /download png/i }),
        ).not.toBeInTheDocument();
        expect(
            within(region).queryByRole('link', { name: /download svg/i }),
        ).not.toBeInTheDocument();
    });

    it('restores the PNG preview img and Download PNG link after switching back from PDF to PNG', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');
        await user.selectOptions(outputFormatSelect, 'png');

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png`,
        );

        const pngDownloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(pngDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );

        expect(
            within(region).queryByRole('link', { name: /download pdf/i }),
        ).not.toBeInTheDocument();
    });

    it('performs zero fetch calls and no blob/export mutation of its own when switching to PDF — the browser loads the download URL directly', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('defaults the Download PDF link href to paperSize=A4&orientation=portrait&download=1 after selecting PDF', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');

        const pdfDownloadLink = within(region).getByRole('link', { name: /download pdf/i });
        expect(pdfDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=A4&orientation=portrait&download=1`,
        );
    });

    it('updates the Download PDF link href to the exact selected nondefault paperSize/orientation combination', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');

        const paperSizeSelect = within(region).getByLabelText(/paper size/i);
        const orientationSelect = within(region).getByLabelText(/orientation/i);

        await user.selectOptions(paperSizeSelect, 'B7');
        await user.selectOptions(orientationSelect, 'Landscape');

        const pdfDownloadLink = within(region).getByRole('link', { name: /download pdf/i });
        expect(pdfDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=B7&orientation=landscape&download=1`,
        );
    });

    it('drops paperSize/orientation query from the PNG and SVG links after switching away from PDF', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);

        await user.selectOptions(outputFormatSelect, 'pdf');

        const paperSizeSelect = within(region).getByLabelText(/paper size/i);
        const orientationSelect = within(region).getByLabelText(/orientation/i);
        await user.selectOptions(paperSizeSelect, 'B7');
        await user.selectOptions(orientationSelect, 'Landscape');

        await user.selectOptions(outputFormatSelect, 'png');

        const pngDownloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(pngDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );

        await user.selectOptions(outputFormatSelect, 'svg');

        const svgDownloadLink = within(region).getByRole('link', { name: /download svg/i });
        expect(svgDownloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.svg?download=1`,
        );
    });
});

/**
 * S1-WP04b6 RED — six stateless basic QR theme selection (frozen MASTER
 * contract handed to this writer). Today QrPrintExportRegion renders six
 * disabled, inert theme buttons that never touch any export URL, so every
 * assertion below fails RED against current production.
 */
describe('QrPrintExportRegion theme selection (QR_THEME_SELECTION_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    function activeItem(id: number, workspaceId = 71): QrCodeItem {
        return {
            id,
            workspaceId,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };
    }

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('keeps classic pressed by default with no theme query on the PNG preview/download URLs', () => {
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const classicButton = within(region).getByRole('radio', { name: /^classic theme$/i });
        expect(classicButton).toHaveAttribute('aria-checked', 'true');

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png`,
        );

        const downloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(downloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );
    });

    it('adds ?theme=X to the PNG preview and ?theme=X&download=1 to the PNG download link after selecting a nonclassic theme', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const boldButton = within(region).getByRole('radio', { name: /^bold theme$/i });
        await user.click(boldButton);

        expect(boldButton).toHaveAttribute('aria-checked', 'true');
        expect(within(region).getByRole('radio', { name: /^classic theme$/i })).toHaveAttribute(
            'aria-checked',
            'false',
        );

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?theme=bold`,
        );

        const downloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(downloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?theme=bold&download=1`,
        );

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('adds ?theme=X to the SVG preview and download links after selecting a nonclassic theme while SVG is the output format', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'svg');

        const roundedButton = within(region).getByRole('radio', { name: /^rounded theme$/i });
        await user.click(roundedButton);

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.svg?theme=rounded`,
        );

        const downloadLink = within(region).getByRole('link', { name: /download svg/i });
        expect(downloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.svg?theme=rounded&download=1`,
        );

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('carries the exact frozen PDF download query order ?paperSize=A4&orientation=portrait&theme=X&download=1', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        const brandedButton = within(region).getByRole('radio', { name: /^branded theme$/i });
        await user.click(brandedButton);

        const downloadLink = within(region).getByRole('link', { name: /download pdf/i });
        expect(downloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=A4&orientation=portrait&theme=branded&download=1`,
        );

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('removes the theme query from PNG preview/download after selecting classic again', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const highContrastButton = within(region).getByRole('radio', {
            name: /^high contrast theme$/i,
        });
        await user.click(highContrastButton);

        const classicButton = within(region).getByRole('radio', { name: /^classic theme$/i });
        await user.click(classicButton);

        expect(classicButton).toHaveAttribute('aria-checked', 'true');
        expect(highContrastButton).toHaveAttribute('aria-checked', 'false');

        const img = within(region).getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute(
            'src',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png`,
        );

        const downloadLink = within(region).getByRole('link', { name: /download png/i });
        expect(downloadLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.png?download=1`,
        );
    });
});

/**
 * S1-WP04b7 RED — browser/OS print affordance (frozen MASTER contract
 * handed to this writer). PDF is the only format a browser can hand to its
 * native print dialog; PNG/SVG are image endpoints with no print target.
 * Today QrPrintExportRegion exposes no link with the accessible exact name
 * "Print" in any state, so every assertion below fails RED against current
 * production. This affordance is a plain link the browser/OS opens and
 * prints on its own — no window.print(), no programmatic click, no fetch,
 * no blob.
 */
describe('QrPrintExportRegion browser/OS print affordance (QR_OS_PRINT_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let windowPrintSpy: ReturnType<typeof vi.fn>;

    function activeItem(id: number, workspaceId = 71): QrCodeItem {
        return {
            id,
            workspaceId,
            locationId: 923,
            menuId: 42,
            token: 'a'.repeat(43),
            resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };
    }

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        windowPrintSpy = vi.fn();
        vi.stubGlobal('print', windowPrintSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exposes no link named Print while the default PNG output format is selected', () => {
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        expect(within(region).queryByRole('link', { name: /^print$/i })).not.toBeInTheDocument();
    });

    it('exposes no link named Print while SVG output format is selected', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'svg');

        expect(within(region).queryByRole('link', { name: /^print$/i })).not.toBeInTheDocument();
    });

    it('exposes exactly one plain Print link to export.pdf with default paperSize/orientation, no download query, target _blank and rel containing noopener after selecting PDF', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        const printLinks = within(region).getAllByRole('link', { name: /^print$/i });
        expect(printLinks).toHaveLength(1);

        const printLink = printLinks[0];
        expect(printLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=A4&orientation=portrait`,
        );
        expect(printLink.getAttribute('href')).not.toMatch(/download/);
        expect(printLink).toHaveAttribute('target', '_blank');
        expect(printLink.getAttribute('rel') ?? '').toMatch(/\bnoopener\b/);
    });

    it('updates the Print link href to the exact ordered nondefault paperSize/orientation/theme with no download query', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        const paperSizeSelect = within(region).getByLabelText(/paper size/i);
        const orientationSelect = within(region).getByLabelText(/orientation/i);
        await user.selectOptions(paperSizeSelect, 'B7');
        await user.selectOptions(orientationSelect, 'Landscape');

        const highContrastButton = within(region).getByRole('radio', {
            name: /^high contrast theme$/i,
        });
        await user.click(highContrastButton);

        const printLink = within(region).getByRole('link', { name: /^print$/i });
        expect(printLink).toHaveAttribute(
            'href',
            `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf?paperSize=B7&orientation=landscape&theme=highContrast`,
        );
        expect(printLink.getAttribute('href')).not.toMatch(/download/);
    });

    it('removes the Print link after switching back from PDF to PNG', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        expect(within(region).getByRole('link', { name: /^print$/i })).toBeInTheDocument();

        await user.selectOptions(outputFormatSelect, 'png');

        expect(within(region).queryByRole('link', { name: /^print$/i })).not.toBeInTheDocument();
    });

    it('performs zero fetch calls, no window.print(), and no programmatic click/print when selecting PDF and adjusting paper size/orientation/theme', async () => {
        const user = userEvent.setup();
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        const paperSizeSelect = within(region).getByLabelText(/paper size/i);
        const orientationSelect = within(region).getByLabelText(/orientation/i);
        await user.selectOptions(paperSizeSelect, 'B7');
        await user.selectOptions(orientationSelect, 'Landscape');

        const printLink = within(region).getByRole('link', { name: /^print$/i });
        const clickSpy = vi.spyOn(printLink, 'click');

        expect(fetchSpy).not.toHaveBeenCalled();
        expect(windowPrintSpy).not.toHaveBeenCalled();
        expect(clickSpy).not.toHaveBeenCalled();
    });

    it('renders the Print link free of breakpoint or fixed-pixel sizing classes at 320x480', async () => {
        const user = userEvent.setup();
        setViewport(320, 480);
        const item = activeItem(900);
        render(<QrPrintExportRegion items={[item]} />);

        const region = screen.getByRole('region', { name: /qr print export/i });
        const outputFormatSelect = within(region).getByLabelText(/output format/i);
        await user.selectOptions(outputFormatSelect, 'pdf');

        const printLink = within(region).getByRole('link', { name: /^print$/i });
        const classLists = collectClassLists(printLink as HTMLElement);

        classLists.forEach((classList) => {
            expect(classList).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        });
    });
});
