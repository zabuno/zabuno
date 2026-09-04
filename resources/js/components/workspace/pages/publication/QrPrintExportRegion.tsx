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
import type { QrCreateReasonKind } from './QrDestinationFieldsRegion';
import { QrPrintPreview } from './QrPrintPreview';
import { QrCardWizard } from './QrCardWizard';
import { isBrandColorPrintable } from '../../../../lib/qrContrast';

/** `App\\Domain\\QrDestination\\QrPrintSheet` ile aynı sayılar. */
const CARDS_PER_PAGE = 12;
const CARDS_PER_REQUEST = 48;

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
    /** Toplu sihirbazın kapalı olma sebebi (FF-108). */
    bulkUnavailableReason?: QrCreateReasonKind;
    onBulkCreated?: (qrCodes: QrCodeItem[]) => void;
    /** Plan kısıtı çıkışı: faturalama ekranı. */
    onUpgrade?: () => void;
    /** Markanın ana rengi — "markalı" tema bunu kullanır (FF-112). */
    brandPrimaryColor?: string | null;
    onEditBrand?: () => void;
};

/**
 * Bir kodun İNSAN ADI (FF-109).
 *
 * Seçici, seçeneklerin metni olarak 43 karakterlik token'ı yazıyordu: sahip
 * "hangisini basacağım" sorusunu o listeden yanıtlayamıyordu. Ad artık masa
 * adıdır; masaya bağlı olmayan kod "giriş kodu"dur.
 *
 * Birden fazla adsız kod varsa sıra numarası eklenir — iki özdeş seçenek,
 * hiç ad olmamasından daha kötüdür: kullanıcı yanlış olanı seçer ve bunu
 * ancak baskıdan sonra fark eder.
 */
function itemLabel(item: QrCodeItem, unnamedIndex: number, unnamedTotal: number): string {
    if (item.tableName) {
        return item.areaLabel ? `${item.tableName} · ${item.areaLabel}` : item.tableName;
    }

    const base = t('workspace.publication.qrDestination.item.entrance');

    return unnamedTotal > 1 ? `${base} ${String(unnamedIndex + 1)}` : base;
}

/**
 * Kesilip masalara dağıtılacak KART DESTESİ (`docs/104` Döngü 8).
 *
 * Tek kodun PDF'inden ayrı bir çıktıdır: o, A4'ün ortasında tek bir kare —
 * 40 masa için 40 ayrı sayfa, her biri %97 beyaz ve baskıdan sonra
 * birbirinden ayırt edilemez. Bu, sayfa başına on iki kart; her kartta
 * restoran adı, 40 mm karekod, masa adı ve kesme çizgisi.
 */
function printSheetUrl(
    workspaceId: number,
    locationId: number,
    theme: QrThemeKey,
    chunk: number,
): string {
    const params = new URLSearchParams();
    if (theme !== 'classic') params.set('theme', theme);
    if (chunk > 1) params.set('chunk', String(chunk));

    const query = params.toString();
    const base = `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/qr-codes/print.pdf`;

    return query ? `${base}?${query}` : base;
}

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
    bulkUnavailableReason,
    onBulkCreated,
    onUpgrade,
    brandPrimaryColor = null,
    onEditBrand,
}: QrPrintExportRegionProps) {
    const activeItems = items.filter((item) => item.state === 'active');
    const unnamedItems = activeItems.filter((item) => !item.tableName);
    const sheetCount = activeItems.length;
    /*
        Sunucu tek istekte en fazla 48 kart basar (her kart ayrı bir PNG
        üretir; 500 masalık bir istek kullanıcıya hiçbir şey vermeden zaman
        aşımına uğrardı). Sayı istemcide de biliniyor ki ekran "3 parçadan
        1." diyebilsin — sessizce kırpılmış bir PDF vermek yerine.
    */
    const chunkCount = Math.max(1, Math.ceil(sheetCount / CARDS_PER_REQUEST));
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

    const singleCodeSection = (
        <>
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
                                        {itemLabel(
                                            item,
                                            unnamedItems.indexOf(item),
                                            unnamedItems.length,
                                        )}
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
                        BURASI KODUN KENDİSİNİN RENGİ — kartın teması DEĞİL
                        (FF-120). İkisi bir zamanlar aynı kelimeyle
                        adlandırılıyordu ("Themes") ve sahibin sorduğu şey
                        hiçbir zaman bu değildi: o, masaya konacak kartın
                        görünümünü soruyordu. Kart artık kendi sihirbazında;
                        burada kalan şey, ham dosyayı indirenler için kodun
                        palet seçeneği.
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
                        {/*
                                TEMA BİR ZEVK MESELESİ DEĞİLDİR (FF-112).

                                Altı tema adı, hiçbir açıklama olmadan duruyordu.
                                Oysa buradaki tek gerçek kısıt taranabilirliktir:
                                okunmayan bir karekod, masadaki ölü kâğıttır ve
                                bunu ilk fark eden kişi telefonunu kartın üstünde
                                sallayan misafirdir. Ürün, sunduğu her temanın
                                taranabilir olduğunu SÖYLER — çünkü söylemezse
                                sahip "acaba bu renk okunur mu?" diye
                                düşünmediğinden değil, düşündüğü için tedirgin
                                olur ve en güvenli görüneni seçer.
                            */}
                        <p className="text-meta text-fg-muted">
                            {t('workspace.publication.qrExport.themes.scannability')}
                        </p>
                        {theme === 'branded' && !isBrandColorPrintable(brandPrimaryColor) ? (
                            /*
                                    MARKA RENGİ KULLANILAMIYORSA SÖYLENİR.
                                    Sunucu bu durumda sessizce klasiğe düşer;
                                    sessizlik, sahibin "markalı"yı seçip siyah bir
                                    kod indirmesi ve bunu bir hata sanması demek
                                    olurdu.
                                */
                            <p
                                role="status"
                                className="flex flex-col items-start gap-[var(--space-1)] text-meta text-fg-secondary"
                            >
                                {t(
                                    brandPrimaryColor === null
                                        ? 'workspace.publication.qrExport.themes.brandMissing'
                                        : 'workspace.publication.qrExport.themes.brandTooPale',
                                )}
                                {onEditBrand ? (
                                    <button
                                        type="button"
                                        onClick={onEditBrand}
                                        className="min-h-[var(--density-hit-area-min)] text-meta text-fg-link underline underline-offset-2"
                                    >
                                        {t('workspace.publication.qrExport.themes.editBrand')}
                                    </button>
                                ) : null}
                            </p>
                        ) : null}
                    </span>

                    {/*
                            QR BİR TESLİMATTIR, hata ayıklama artığı değil.

                            Önceden çıplak bir `<img>` kartın üstünde yüzüyordu.
                            Beyaz plaka hem görsel bir çerçeve hem de İŞLEVSEL bir
                            gerekliliktir: karekodun taranabilmesi için etrafında
                            açık renkli sessiz bölge şarttır (ISO/IEC 18004: 4
                            modül). Koyu temada saydam bir kod taranamazdı.
                        */}
                    {/*
                            PDF'İN DE BİR ÖNİZLEMESİ VAR (FF-113, Döngü 9).

                            Kâğıt ve yön seçicileri, kontrol ettikleri sonucu
                            hiçbir yerde göstermiyordu: sahip "A6 yatay" seçiyor
                            ve ne olacağını ancak yazıcıdan kâğıt çıkınca
                            öğreniyordu. PNG/SVG'nin gerçek bir görüntüsü var;
                            PDF'in şeması var — ve şema, asıl bilgiyi taşır:
                            milimetre.
                        */}
                    {isPdf ? (
                        <QrPrintPreview paperSize={paperSize} orientation={orientation} />
                    ) : null}

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
        </>
    );

    return (
        <div
            role="region"
            aria-label={t('workspace.publication.qrExport.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.publication.qrExport.region')}
            </h3>

            {/*
                MASADAKİ KART BİRİNCİL İŞTİR (FF-120, sahibin talebi).

                Sahip buraya "dosya biçimi seçmeye" gelmez; masaya koyacağı
                kartı basmaya gelir. Eski dizilimde en üstte biçim/kâğıt/yön
                ve karekodun piksel renkleri vardı — hiçbiri onun sorduğu soru
                değildi.
            */}
            {selected === null ? null : <QrCardWizard item={selected} />}

            {/*
                DESTE, TEK KARTTAN ÖNCE GELİR.

                Sahibin buraya gelme sebebi çoğunlukla "masalara koyacak
                kartları basmak"tır; tek bir kodu indirmek istisnadır. Deste
                ancak birden fazla etkin kod varken belirir — tek kodlu bir
                kafeye "12'li sayfa" önermek, olmayan bir işi önermektir.
            */}
            {sheetCount > 1 && workspaceId !== undefined && locationId !== undefined ? (
                <div className="flex flex-col gap-[var(--space-2)] border-t border-border pt-[var(--space-3)]">
                    <h4 className="text-body font-semibold text-fg">
                        {t('workspace.publication.qrExport.sheet.heading')}
                    </h4>
                    <p className="text-body text-fg-secondary">
                        {t('workspace.publication.qrExport.sheet.explanation', {
                            codes: String(sheetCount),
                            pages: String(Math.ceil(sheetCount / CARDS_PER_PAGE)),
                        })}
                    </p>
                    <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                        {Array.from({ length: chunkCount }, (_unused, index) => (
                            <ActionLink
                                key={index}
                                variant={index === 0 ? 'primary' : 'secondary'}
                                href={printSheetUrl(workspaceId, locationId, theme, index + 1)}
                            >
                                {chunkCount === 1
                                    ? t('workspace.publication.qrExport.sheet.download')
                                    : t('workspace.publication.qrExport.sheet.downloadPart', {
                                          part: String(index + 1),
                                          total: String(chunkCount),
                                      })}
                            </ActionLink>
                        ))}
                    </span>
                </div>
            ) : null}

            {/*
                TEK KOD, İKİNCİL BİR İŞTİR (FF-114).

                Bu ekran bir ÜRETEÇ gibi kuruluydu: en üstte biçim, kâğıt, yön
                ve tek bir kodun önizlemesi; sahibin asıl işi — masalara
                dağıtılacak kartları basmak — en alttaydı. Oysa restoran
                sahibi buraya "QR ayarı yapmaya" gelmez: kırk masası, bir
                mukavvası ve bir yazıcısı vardır.

                Deste varken tek kod bölümü KAPALI başlar. `<details>` içeriği
                DOM'da kalır — klavye, ekran okuyucu ve form doğrulaması
                etkilenmez; yalnız ilk bakışta görünmez.
            */}
            {sheetCount > 1 ? (
                <details className="rounded-[var(--radius-md)] border border-border p-[var(--space-3)]">
                    <summary className="cursor-pointer text-body font-medium text-fg-secondary">
                        {t('workspace.publication.qrExport.raw.heading')}
                    </summary>
                    <div className="flex flex-col gap-[var(--space-3)] pt-[var(--space-3)]">
                        {singleCodeSection}
                    </div>
                </details>
            ) : (
                singleCodeSection
            )}

            <BulkQrWizardFields
                workspaceId={workspaceId}
                locationId={locationId}
                menuId={menuId}
                hasCurrentPublication={hasCurrentPublication}
                unavailableReason={bulkUnavailableReason}
                onCreated={onBulkCreated}
                onUpgrade={onUpgrade}
            />
        </div>
    );
}

export default QrPrintExportRegion;
