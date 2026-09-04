import { Select } from '../../../catalog/forms/micro/Select';
import { t } from '../../../../i18n/workspace';

const PAPER_SIZES = ['A4', 'B4', 'A5', 'B5', 'A6', 'B6', 'A7', 'B7'] as const;

/*
    ETİKET GÖVDE ÖLÇEĞİNDEDİR — `--text-meta` zaman damgası ve sayaç içindir,
    kullanıcının cevaplayacağı soru için değil (`app.css` §text-meta).
*/
const LABEL_CLASSES = 'flex flex-col gap-1 text-body font-medium text-fg-secondary';

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
            {/*
                BAŞLIK gerçek bir başlık gibi yazılır. Büyük harfli `text-meta`
                bu sistemde ölçüm etiketi ve tablo başlığı içindir
                (`docs/102` §1); bölüm başlığı olarak kullanılması, bir kartın
                içinde dört ayrı başlık dili doğuruyordu.
            */}
            <legend className="text-body font-bold text-fg">
                {t('workspace.publication.qrExport.config.heading')}
            </legend>

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

            {/*
                Kâğıt ve yön YALNIZ PDF'te ÇİZİLİR — devre dışı çizilmez.

                Devre dışı bir kontrol ekranda yer kaplar, okunur, üzerine
                tıklanır ve hiçbir şey yapmaz; kullanıcı onu "bozuk" diye
                öğrenir. Görünmeyen bir kontrol ise soru sormaz.
            */}
            {isPdf ? (
                <>
                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.config.paperSize')}
                        <Select
                            value={paperSize}
                            onChange={(event) =>
                                onPaperSizeChange?.(event.target.value as QrPaperSize)
                            }
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
                            value={orientation}
                            onChange={(event) =>
                                onOrientationChange?.(event.target.value as QrOrientation)
                            }
                        >
                            <option value="Portrait">
                                {t('workspace.publication.qrExport.config.orientation.portrait')}
                            </option>
                            <option value="Landscape">
                                {t('workspace.publication.qrExport.config.orientation.landscape')}
                            </option>
                        </Select>
                    </label>
                </>
            ) : null}
        </fieldset>
    );
}

export default QrExportConfigForm;
