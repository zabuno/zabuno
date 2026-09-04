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
import { Select } from '../../../catalog/forms/micro/Select';
import { SegmentedControl } from '../../../catalog/forms/compound/SegmentedControl';
import { ActionLink } from '../../../catalog/navigation/micro/ActionLink';

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

const LABEL_CLASSES = 'flex flex-col gap-1 text-meta font-medium text-fg-secondary';

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
    const [previewFailed, setPreviewFailed] = useState(false);

    function handleOutputFormatChange(format: QrOutputFormat) {
        // Biçim değişince yeni bir görsel istenir; eski hata devredilmez.
        setPreviewFailed(false);
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
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.publication.qrExport.region')}
            </h3>

            {selected === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.qrExport.noActive')}
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    {activeItems.length > 1 ? (
                        <label className={LABEL_CLASSES}>
                            {t('workspace.publication.qrExport.selector')}
                            <Select
                                aria-label={t('workspace.publication.qrExport.selector')}
                                value={selected.id}
                                onChange={(event) => setSelectedId(Number(event.target.value))}
                            >
                                {activeItems.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.token}
                                    </option>
                                ))}
                            </Select>
                        </label>
                    ) : null}

                    {/*
                        AYARLAR ÖNİZLEMENİN ÜSTÜNDE.

                        Önceki dizilimde biçim/kâğıt/yön, kendi ürettikleri
                        önizlemenin ve indirme bağlantısının ALTINDA duruyordu:
                        sebep sonucun altındaydı ve kullanıcı kontrolü hiç
                        bulmuyordu. Yazdırma deneyiminin kuralı, ayarların
                        önizlemeye BAĞLI olmasıdır (Microsoft UX Guide).
                    */}
                    <QrExportConfigForm
                        outputFormat={outputFormat}
                        onOutputFormatChange={handleOutputFormatChange}
                        paperSize={paperSize}
                        onPaperSizeChange={setPaperSize}
                        orientation={orientation}
                        onOrientationChange={setOrientation}
                    />

                    {/*
                        TEMA, ÖNİZLEMENİN YANINDA.

                        Önceden tema seçici, kontrol ettiği görselden iki
                        bölüm uzakta — toplu sihirbazın ALTINDA — duruyordu.
                        Kâğıt boyu ile tema, "bu ne basacak" sorusunun iki
                        yarısıdır; ikisini ayırmak, kullanıcının değiştirdiği
                        şeyin sonucunu görmemesi demekti.
                    */}
                    <span className="flex flex-col gap-[var(--space-2)]">
                        <h4 className="text-body font-semibold text-fg">
                            {t('workspace.publication.qrExport.themes.heading')}
                        </h4>
                        <SegmentedControl
                            label={t('workspace.publication.qrExport.themes.heading')}
                            value={theme}
                            options={THEME_ORDER.map((key) => ({
                                value: key,
                                label: t(THEME_LABEL_KEYS[key]),
                            }))}
                            onChange={setTheme}
                        />
                    </span>

                    {/*
                        QR BİR TESLİMATTIR, hata ayıklama artığı değil.

                        Önceden çıplak bir `<img>` kartın üstünde yüzüyordu.
                        Beyaz plaka hem görsel bir çerçeve hem de İŞLEVSEL bir
                        gerekliliktir: karekodun taranabilmesi için etrafında
                        açık renkli sessiz bölge şarttır (ISO/IEC 18004: 4
                        modül). Koyu temada saydam bir kod taranamazdı.
                    */}
                    {isPdf ? null : (
                        <span className="flex w-fit rounded-[var(--radius-lg)] border border-border bg-white p-[var(--space-4)]">
                            {previewFailed ? (
                                /*
                                    TESLİMATIN DA BİR DURUMU OLMALI.

                                    Görsel üretilemediğinde tarayıcının kırık
                                    resim simgesi kalıyordu: sayfadaki her
                                    şeyin bir hâli varken, sahibin buraya
                                    gelme sebebi olan şeyin yoktu.
                                */
                                <span
                                    role="status"
                                    className="flex h-[13.75rem] w-[13.75rem] items-center justify-center p-[var(--space-4)] text-center text-meta text-fg-danger"
                                >
                                    {t('workspace.publication.qrExport.preview.failed')}
                                </span>
                            ) : (
                                <img
                                    key={exportUrl(selected, previewFormat, false, theme)}
                                    src={exportUrl(selected, previewFormat, false, theme)}
                                    alt={t('workspace.publication.qrExport.previewAlt')}
                                    width={220}
                                    height={220}
                                    onError={() => setPreviewFailed(true)}
                                    className="h-[13.75rem] w-[13.75rem]"
                                />
                            )}
                        </span>
                    )}

                    {/*
                        İNDİRME sayfanın BİRİNCİL eylemidir: sahip buraya
                        yayınlamak için değil, BASMAK için gelir. Önceden
                        gövde metniyle aynı ağırlıkta bir bağlantıydı ve
                        marka vurgusu, yılda bir kez kullanılan toplu
                        sihirbaza harcanmıştı.
                    */}
                    <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                        <ActionLink
                            href={
                                isPdf
                                    ? pdfExportUrl(selected, paperSize, orientation, theme)
                                    : exportUrl(selected, previewFormat, true, theme)
                            }
                        >
                            {t('workspace.publication.qrExport.downloadButton')}{' '}
                            {t(
                                isPdf
                                    ? 'workspace.publication.qrExport.formats.pdf'
                                    : previewFormat === 'svg'
                                      ? 'workspace.publication.qrExport.formats.svg'
                                      : 'workspace.publication.qrExport.formats.png',
                            )}
                        </ActionLink>

                        {isPdf ? (
                            <ActionLink
                                variant="secondary"
                                href={pdfExportUrl(selected, paperSize, orientation, theme, false)}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('workspace.publication.qrExport.printButton')}
                            </ActionLink>
                        ) : null}
                    </span>
                </div>
            )}

            <BulkQrWizardFields
                workspaceId={workspaceId}
                locationId={locationId}
                menuId={menuId}
                hasCurrentPublication={hasCurrentPublication}
                onCreated={onBulkCreated}
            />
        </div>
    );
}

export default QrPrintExportRegion;
