import { t } from '../../../../i18n/workspace';

const PAPER_SIZES = ['A4', 'B4', 'A5', 'B5', 'A6', 'B6', 'A7', 'B7'] as const;

const FIELD_CLASSES =
    'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:disabled:text-gray-500';

const LABEL_CLASSES = 'flex flex-col gap-1 text-xs font-medium text-gray-700 dark:text-gray-300';

export type QrOutputFormat = 'png' | 'svg' | 'pdf';
export type QrPaperSize = (typeof PAPER_SIZES)[number];
export type QrOrientation = 'Portrait' | 'Landscape';

type QrExportConfigFormProps = {
    outputFormat?: QrOutputFormat;
    onOutputFormatChange?: (format: QrOutputFormat) => void;
    paperSize?: QrPaperSize;
    onPaperSizeChange?: (paperSize: QrPaperSize) => void;
    orientation?: QrOrientation;
    onOrientationChange?: (orientation: QrOrientation) => void;
};

export function QrExportConfigForm({
    outputFormat = 'png',
    onOutputFormatChange,
    paperSize = 'A4',
    onPaperSizeChange,
    orientation = 'Portrait',
    onOrientationChange,
}: QrExportConfigFormProps) {
    const isPdf = outputFormat === 'pdf';
    return (
        <fieldset
            className="flex flex-col gap-3"
            aria-label={t('workspace.publication.qrExport.config.heading')}
        >
            <legend className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.publication.qrExport.config.heading')}
            </legend>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.destinationType')}
                <select disabled defaultValue="published" className={FIELD_CLASSES}>
                    <option value="published">
                        {t('workspace.publication.qrExport.config.destinationType.published')}
                    </option>
                </select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.outputFormat')}
                <select
                    value={outputFormat}
                    onChange={(event) =>
                        onOutputFormatChange?.(event.target.value as QrOutputFormat)
                    }
                    className={FIELD_CLASSES}
                >
                    <option value="png">{t('workspace.publication.qrExport.formats.png')}</option>
                    <option value="svg">{t('workspace.publication.qrExport.formats.svg')}</option>
                    <option value="pdf">{t('workspace.publication.qrExport.formats.pdf')}</option>
                </select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.paperSize')}
                <select
                    disabled={!isPdf}
                    value={paperSize}
                    onChange={(event) => onPaperSizeChange?.(event.target.value as QrPaperSize)}
                    className={FIELD_CLASSES}
                >
                    {PAPER_SIZES.map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.orientation')}
                <select
                    disabled={!isPdf}
                    value={orientation}
                    onChange={(event) => onOrientationChange?.(event.target.value as QrOrientation)}
                    className={FIELD_CLASSES}
                >
                    <option value="Portrait">
                        {t('workspace.publication.qrExport.config.orientation.portrait')}
                    </option>
                    <option value="Landscape">
                        {t('workspace.publication.qrExport.config.orientation.landscape')}
                    </option>
                </select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.bulk')}
                <input
                    type="text"
                    disabled
                    defaultValue=""
                    placeholder={t('workspace.publication.qrExport.config.bulk.placeholder')}
                    className={FIELD_CLASSES}
                />
            </label>
        </fieldset>
    );
}

export default QrExportConfigForm;
