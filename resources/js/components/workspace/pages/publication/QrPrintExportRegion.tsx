import { useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { BulkQrWizardFields } from './BulkQrWizardFields';
import {
    QrExportConfigForm,
    type QrOrientation,
    type QrOutputFormat,
    type QrPaperSize,
} from './QrExportConfigForm';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

const THEME_ORDER = ['classic', 'minimal', 'bold', 'rounded', 'branded', 'highContrast'] as const;

type QrThemeKey = (typeof THEME_ORDER)[number];

const THEME_LABEL_KEYS: Record<QrThemeKey, Parameters<typeof t>[0]> = {
    classic: 'workspace.publication.qrExport.themes.classic',
    minimal: 'workspace.publication.qrExport.themes.minimal',
    bold: 'workspace.publication.qrExport.themes.bold',
    rounded: 'workspace.publication.qrExport.themes.rounded',
    branded: 'workspace.publication.qrExport.themes.branded',
    highContrast: 'workspace.publication.qrExport.themes.highContrast',
};

const DISABLED_BUTTON_CLASSES =
    'self-start rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-400 dark:border-gray-600 dark:text-gray-500';

const THEME_BUTTON_CLASSES =
    'self-start rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800';

const THEME_BUTTON_SELECTED_CLASSES =
    'self-start rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-medium text-white dark:border-blue-500 dark:bg-blue-500';

const FIELD_CLASSES =
    'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

const LABEL_CLASSES = 'flex flex-col gap-1 text-xs font-medium text-gray-700 dark:text-gray-300';

type QrPrintExportRegionProps = {
    items?: QrCodeItem[];
    workspaceId?: number;
    locationId?: number;
    menuId?: number;
    hasCurrentPublication?: boolean;
    onBulkCreated?: (qrCodes: QrCodeItem[]) => void;
};

function exportUrl(
    item: QrCodeItem,
    format: QrOutputFormat,
    download: boolean,
    theme: QrThemeKey,
): string {
    const base = `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.${format}`;

    const params = new URLSearchParams();
    if (theme !== 'classic') params.set('theme', theme);
    if (download) params.set('download', '1');

    const query = params.toString();

    return query ? `${base}?${query}` : base;
}

function pdfExportUrl(
    item: QrCodeItem,
    paperSize: QrPaperSize,
    orientation: QrOrientation,
    theme: QrThemeKey,
    download: boolean = true,
): string {
    const base = `/api/workspaces/${item.workspaceId}/qr-codes/${item.id}/export.pdf`;

    const params = new URLSearchParams();
    params.set('paperSize', paperSize);
    params.set('orientation', orientation.toLowerCase());
    if (theme !== 'classic') params.set('theme', theme);
    if (download) params.set('download', '1');

    return `${base}?${params.toString()}`;
}

/**
 * PNG and SVG preview/download, and PDF/paper size/orientation download, are
 * all direct img/anchor endpoints against the real export.png/export.svg/
 * export.pdf routes — never a fetch-driven blob generated client-side. The
 * bulk wizard performs a real CSRF-bootstrapped POST against the bulk table/
 * QR endpoint when workspaceId/locationId/menuId and hasCurrentPublication
 * are threaded in from the caller.
 */
export function QrPrintExportRegion({
    items = [],
    workspaceId,
    locationId,
    menuId,
    hasCurrentPublication,
    onBulkCreated,
}: QrPrintExportRegionProps) {
    const activeItems = items.filter((item) => item.state === 'active');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [outputFormat, setOutputFormat] = useState<QrOutputFormat>('png');
    const [paperSize, setPaperSize] = useState<QrPaperSize>('A4');
    const [orientation, setOrientation] = useState<QrOrientation>('Portrait');
    const [theme, setTheme] = useState<QrThemeKey>('classic');
    const selected = activeItems.find((item) => item.id === selectedId) ?? activeItems[0] ?? null;
    const isPdf = outputFormat === 'pdf';
    const previewFormat: 'png' | 'svg' = outputFormat === 'svg' ? 'svg' : 'png';

    function handleOutputFormatChange(format: QrOutputFormat) {
        setOutputFormat(format);
        if (format !== 'pdf') {
            setPaperSize('A4');
            setOrientation('Portrait');
        }
    }

    return (
        <div
            role="region"
            aria-label={t('workspace.publication.qrExport.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.publication.qrExport.region')}
            </h3>

            {selected === null ? (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.publication.qrExport.noActive')}
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    {activeItems.length > 1 ? (
                        <label className={LABEL_CLASSES}>
                            {t('workspace.publication.qrExport.selector')}
                            <select
                                aria-label={t('workspace.publication.qrExport.selector')}
                                value={selected.id}
                                onChange={(event) => setSelectedId(Number(event.target.value))}
                                className={FIELD_CLASSES}
                            >
                                {activeItems.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.token}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : null}

                    {isPdf ? null : (
                        <img
                            src={exportUrl(selected, previewFormat, false, theme)}
                            alt={t('workspace.publication.qrExport.previewAlt')}
                            className="h-auto w-full max-w-xs"
                        />
                    )}

                    <a
                        href={
                            isPdf
                                ? pdfExportUrl(selected, paperSize, orientation, theme)
                                : exportUrl(selected, previewFormat, true, theme)
                        }
                        className="self-start text-sm text-blue-700 underline dark:text-blue-400"
                    >
                        {t('workspace.publication.qrExport.downloadButton')}{' '}
                        {t(
                            isPdf
                                ? 'workspace.publication.qrExport.formats.pdf'
                                : previewFormat === 'svg'
                                  ? 'workspace.publication.qrExport.formats.svg'
                                  : 'workspace.publication.qrExport.formats.png',
                        )}
                    </a>

                    {isPdf ? (
                        <a
                            href={pdfExportUrl(selected, paperSize, orientation, theme, false)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="self-start text-sm text-blue-700 underline dark:text-blue-400"
                        >
                            {t('workspace.publication.qrExport.printButton')}
                        </a>
                    ) : null}
                </div>
            )}

            <QrExportConfigForm
                outputFormat={outputFormat}
                onOutputFormatChange={handleOutputFormatChange}
                paperSize={paperSize}
                onPaperSizeChange={setPaperSize}
                orientation={orientation}
                onOrientationChange={setOrientation}
            />

            <BulkQrWizardFields
                workspaceId={workspaceId}
                locationId={locationId}
                menuId={menuId}
                hasCurrentPublication={hasCurrentPublication}
                onCreated={onBulkCreated}
            />

            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.publication.qrExport.themes.heading')}
            </p>
            <div className="flex flex-wrap gap-2">
                {THEME_ORDER.map((key) => (
                    <button
                        key={key}
                        type="button"
                        aria-pressed={theme === key}
                        onClick={() => setTheme(key)}
                        className={
                            theme === key ? THEME_BUTTON_SELECTED_CLASSES : THEME_BUTTON_CLASSES
                        }
                    >
                        {t(THEME_LABEL_KEYS[key])}
                    </button>
                ))}
            </div>

            <div className="flex flex-wrap gap-2">
                <button type="button" disabled className={DISABLED_BUTTON_CLASSES}>
                    {t('workspace.publication.qrExport.exportButton')}
                </button>
            </div>
        </div>
    );
}

export default QrPrintExportRegion;
