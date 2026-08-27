import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { t } from '../../../../i18n/workspace';

const PAPER_SIZES = ['A4', 'B4', 'A5', 'B5', 'A6', 'B6', 'A7', 'B7'] as const;

const LABEL_CLASSES = 'flex flex-col gap-1 text-meta font-medium text-fg-secondary';

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
            <legend className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.publication.qrExport.config.heading')}
            </legend>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.destinationType')}
                <Select disabled defaultValue="published">
                    <option value="published">
                        {t('workspace.publication.qrExport.config.destinationType.published')}
                    </option>
                </Select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.outputFormat')}
                <Select
                    value={outputFormat}
                    onChange={(event) =>
                        onOutputFormatChange?.(event.target.value as QrOutputFormat)
                    }
                >
                    <option value="png">{t('workspace.publication.qrExport.formats.png')}</option>
                    <option value="svg">{t('workspace.publication.qrExport.formats.svg')}</option>
                    <option value="pdf">{t('workspace.publication.qrExport.formats.pdf')}</option>
                </Select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.paperSize')}
                <Select
                    disabled={!isPdf}
                    value={paperSize}
                    onChange={(event) => onPaperSizeChange?.(event.target.value as QrPaperSize)}
                >
                    {PAPER_SIZES.map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </Select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.orientation')}
                <Select
                    disabled={!isPdf}
                    value={orientation}
                    onChange={(event) => onOrientationChange?.(event.target.value as QrOrientation)}
                >
                    <option value="Portrait">
                        {t('workspace.publication.qrExport.config.orientation.portrait')}
                    </option>
                    <option value="Landscape">
                        {t('workspace.publication.qrExport.config.orientation.landscape')}
                    </option>
                </Select>
            </label>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.config.bulk')}
                <TextInput
                    type="text"
                    disabled
                    defaultValue=""
                    placeholder={t('workspace.publication.qrExport.config.bulk.placeholder')}
                />
            </label>
        </fieldset>
    );
}

export default QrExportConfigForm;
