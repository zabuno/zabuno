import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';
import { Button } from '../../../catalog/forms/micro/Button';
import { TextLink } from '../../../catalog/navigation/micro/TextLink';
import type { QrCreateReasonKind } from './QrDestinationFieldsRegion';

/*
    ETİKET VE HATA GÖVDE ÖLÇEĞİNDEDİR.

    `--text-meta` bu sistemde zaman damgası, sayaç ve birim eki içindir; etiket,
    buton metni ve hata mesajı `--text-body` taşır (`app.css` §text-meta).
    Bugün ikisi de 1rem'e bağlı, yani ekranda fark görünmüyor — ama meta ölçeği
    ikincil bilgi için yarın küçüldüğünde "Enter a whole number between 1 and
    500." uyarısı da onunla küçülür ve kullanıcı hatasını düzeltemez.
*/
const LABEL_CLASSES = 'flex flex-col gap-1 text-body font-medium text-fg-secondary';

const ALERT_CLASSES = 'text-body text-fg-danger';

type FieldKey =
    | 'areaSectionCount'
    | 'tableCount'
    | 'namingPrefix'
    | 'namingSequenceStart'
    | 'namingRange'
    | 'seatCountPerTable';

type Values = Record<FieldKey, string>;

type BulkQrCodeItem = QrCodeItem & { tableId: number };

type WizardResultPair = {
    tableId: number;
    tableName: string;
    resolverUrl: string;
};

type WizardResult = {
    areasCount: number;
    tablesCount: number;
    qrCodesCount: number;
    pairs: WizardResultPair[];
};

type BulkQrWizardFieldsProps = {
    workspaceId?: number;
    locationId?: number;
    menuId?: number;
    hasCurrentPublication?: boolean;
    /** Düğme neden kapalı — "önce yayınlayın" her zaman doğru değil (FF-108). */
    unavailableReason?: QrCreateReasonKind;
    onCreated?: (qrCodes: QrCodeItem[]) => void;
    /** Plan bu yeteneği içermiyorsa çıkış yolu: faturalama ekranı. */
    onUpgrade?: () => void;
};

/*
    TEK SORU: "kaç masa?" (`docs/101` Y5, Faz 3).

    Öncesinde altı alanın altısı da boş geliyordu ve hepsi zorunluydu:
    kebapçı karekod bastırmak için bölge sayısı, masa başına koltuk, ad öneki,
    sıra başlangıcı ve aralık girmek zorundaydı. Beşinin de makul bir
    varsayılanı var; varsayılanı olan bir alan kullanıcıya sorulmaz, "ileri"
    başlığı altında DEĞİŞTİRİLEBİLİR durur (`docs/47` Kural 4).
*/
const INITIAL_VALUES: Values = {
    areaSectionCount: '1',
    tableCount: '',
    namingPrefix: '',
    namingSequenceStart: '',
    namingRange: '',
    seatCountPerTable: '4',
};

const INITIAL_TOUCHED: Record<FieldKey, boolean> = {
    areaSectionCount: false,
    tableCount: false,
    namingPrefix: false,
    namingSequenceStart: false,
    namingRange: false,
    seatCountPerTable: false,
};

const FIELD_ERROR_KEYS: Record<FieldKey, Parameters<typeof t>[0]> = {
    areaSectionCount: 'workspace.publication.qrExport.bulkWizard.areaSectionCount.error',
    tableCount: 'workspace.publication.qrExport.bulkWizard.tableCount.error',
    namingPrefix: 'workspace.publication.qrExport.bulkWizard.namingPrefix.error',
    namingSequenceStart: 'workspace.publication.qrExport.bulkWizard.namingSequenceStart.error',
    namingRange: 'workspace.publication.qrExport.bulkWizard.namingRange.error',
    seatCountPerTable: 'workspace.publication.qrExport.bulkWizard.seatCountPerTable.error',
};

const REASON_KEYS: Record<QrCreateReasonKind, Parameters<typeof t>[0]> = {
    notPublished: 'workspace.publication.qrExport.bulkWizard.needsPublication',
    loading: 'workspace.publication.qrDestination.fields.checking',
    unknown: 'workspace.publication.qrDestination.statusUnknown',
};

function parseWholeNumber(raw: string): number | null {
    if (!/^-?\d+$/.test(raw)) return null;
    return Number(raw);
}

function isBoundedWholeNumber(raw: string, min: number, max: number): boolean {
    const parsed = parseWholeNumber(raw);
    return parsed !== null && parsed >= min && parsed <= max;
}

function isValid(key: FieldKey, values: Values): boolean {
    switch (key) {
        case 'areaSectionCount':
            return isBoundedWholeNumber(values.areaSectionCount, 1, 50);
        case 'tableCount':
            return isBoundedWholeNumber(values.tableCount, 1, 500);
        case 'seatCountPerTable':
            return isBoundedWholeNumber(values.seatCountPerTable, 1, 20);
        case 'namingPrefix':
            return values.namingPrefix.trim() === '' || values.namingPrefix.trim().length <= 10;
        case 'namingSequenceStart':
            return (
                values.namingSequenceStart.trim() === '' ||
                isBoundedWholeNumber(values.namingSequenceStart, 0, 9999)
            );
        case 'namingRange': {
            const trimmed = values.namingRange.trim();
            if (trimmed === '') return true;
            const match = /^(\d{1,4})-(\d{1,4})$/.exec(trimmed);
            if (!match) return false;
            return Number(match[1]) <= Number(match[2]);
        }
        default:
            return true;
    }
}

function isCrossFieldValid(values: Values): boolean {
    const tableCount = parseWholeNumber(values.tableCount);
    const rangeTrimmed = values.namingRange.trim();
    const sequenceTrimmed = values.namingSequenceStart.trim();

    if (rangeTrimmed !== '') {
        const match = /^(\d{1,4})-(\d{1,4})$/.exec(rangeTrimmed);
        if (!match) return false;

        const start = Number(match[1]);
        const end = Number(match[2]);
        if (start > end) return false;

        if (tableCount !== null && end - start + 1 !== tableCount) return false;

        if (sequenceTrimmed !== '') {
            const sequence = parseWholeNumber(sequenceTrimmed);
            if (sequence === null || sequence !== start) return false;
        }

        return true;
    }

    if (sequenceTrimmed !== '') {
        const sequence = parseWholeNumber(sequenceTrimmed);
        if (sequence === null) return false;

        if (tableCount !== null && sequence + tableCount - 1 > 9999) return false;
    }

    return true;
}

function isBulkResponseBody(
    body: unknown,
): body is { areas: unknown[]; tables: { id: number; name: string }[]; qrCodes: BulkQrCodeItem[] } {
    if (typeof body !== 'object' || body === null) return false;

    const candidate = body as Record<string, unknown>;

    return (
        Array.isArray(candidate.areas) &&
        Array.isArray(candidate.tables) &&
        Array.isArray(candidate.qrCodes)
    );
}

export function BulkQrWizardFields(props: BulkQrWizardFieldsProps) {
    const {
        workspaceId,
        locationId,
        menuId,
        hasCurrentPublication = false,
        unavailableReason = 'notPublished',
        onCreated,
        onUpgrade,
    } = props;

    const [values, setValues] = useState<Values>(INITIAL_VALUES);
    const [touched, setTouched] = useState(INITIAL_TOUCHED);
    const [submitting, setSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    /*
        PLAN KISITI, HATA DEĞİLDİR (FF-108).

        Sunucu bu uç için bilerek 402 + `entitlement` döndürüyor; istemci ise
        201 olmayan HER cevabı "Oluşturulamadı. Tekrar deneyin." diye
        gösteriyordu. Tekrar denemek hiçbir zaman işe yaramaz: kullanıcı
        yetkisiz değil, planı bu yeteneği içermiyor. Çıkış yolu farklıdır —
        biri tekrar deneme, diğeri plan yükseltmesidir (`AnalyticsPage`
        aynı ayrımı zaten yapıyor).
    */
    const [planRestricted, setPlanRestricted] = useState(false);
    const [result, setResult] = useState<WizardResult | null>(null);
    const [hasAttempted, setHasAttempted] = useState(false);

    function handleChange(key: FieldKey, value: string) {
        setValues((current) => ({ ...current, [key]: value }));
        setErrorMessage(null);
        setResult(null);
    }

    function handleBlur(key: FieldKey) {
        setTouched((current) => ({ ...current, [key]: true }));
    }

    const fieldValidity: Record<FieldKey, boolean> = {
        areaSectionCount: isValid('areaSectionCount', values),
        tableCount: isValid('tableCount', values),
        namingPrefix: isValid('namingPrefix', values),
        namingSequenceStart: isValid('namingSequenceStart', values),
        namingRange: isValid('namingRange', values),
        seatCountPerTable: isValid('seatCountPerTable', values),
    };

    const requiredValuesPresent =
        values.areaSectionCount.trim() !== '' &&
        values.tableCount.trim() !== '' &&
        values.seatCountPerTable.trim() !== '';

    const allFieldsValid = (Object.keys(fieldValidity) as FieldKey[]).every(
        (key) => fieldValidity[key],
    );
    const crossFieldValid = isCrossFieldValid(values);
    const allValid = allFieldsValid && crossFieldValid;

    const showSummary = requiredValuesPresent && allValid;

    const crossFieldInputsPresent =
        values.namingRange.trim() !== '' || values.namingSequenceStart.trim() !== '';
    const showCrossFieldAlert =
        fieldValidity.tableCount &&
        fieldValidity.namingRange &&
        fieldValidity.namingSequenceStart &&
        crossFieldInputsPresent &&
        !crossFieldValid;

    const canSubmit =
        allValid &&
        hasCurrentPublication === true &&
        workspaceId !== undefined &&
        locationId !== undefined &&
        menuId !== undefined &&
        !submitting;

    function errorId(key: FieldKey): string {
        return `bulk-qr-wizard-${key}-error`;
    }

    function describedBy(key: FieldKey): string | undefined {
        return touched[key] && !fieldValidity[key] ? errorId(key) : undefined;
    }

    function renderError(key: FieldKey) {
        if (!touched[key] || fieldValidity[key]) return null;
        return (
            <span id={errorId(key)} role="alert" className={ALERT_CLASSES}>
                {t(FIELD_ERROR_KEYS[key])}
            </span>
        );
    }

    async function handleSubmit() {
        if (
            !canSubmit ||
            workspaceId === undefined ||
            locationId === undefined ||
            menuId === undefined
        ) {
            return;
        }

        setHasAttempted(true);
        setSubmitting(true);
        setErrorMessage(null);
        setPlanRestricted(false);
        setResult(null);

        try {
            await bootstrapCsrfCookie();

            const payload: Record<string, unknown> = {
                menuId,
                areaSectionCount: Number(values.areaSectionCount),
                tableCount: Number(values.tableCount),
                seatCountPerTable: Number(values.seatCountPerTable),
            };

            const namingPrefix = values.namingPrefix.trim();
            if (namingPrefix !== '') payload.namingPrefix = namingPrefix;

            const namingRange = values.namingRange.trim();
            if (namingRange !== '') payload.namingRange = namingRange;

            const namingSequenceStart = values.namingSequenceStart.trim();
            if (namingSequenceStart !== '')
                payload.namingSequenceStart = Number(namingSequenceStart);

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand/locations/${locationId}/tables/bulk`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }),
            );

            if (response.status === 402) {
                setPlanRestricted(true);
                return;
            }

            if (response.status !== 201) {
                setErrorMessage(t('workspace.publication.qrExport.bulkWizard.createError'));
                return;
            }

            const body = (await response.json()) as unknown;
            if (!isBulkResponseBody(body)) {
                setErrorMessage(t('workspace.publication.qrExport.bulkWizard.createError'));
                return;
            }

            const pairs: WizardResultPair[] = body.tables
                .map((table) => {
                    const qrCode = body.qrCodes.find((item) => item.tableId === table.id);
                    return qrCode
                        ? {
                              tableId: table.id,
                              tableName: table.name,
                              resolverUrl: qrCode.resolverUrl,
                          }
                        : null;
                })
                .filter((pair): pair is WizardResultPair => pair !== null);

            setResult({
                areasCount: body.areas.length,
                tablesCount: body.tables.length,
                qrCodesCount: body.qrCodes.length,
                pairs,
            });
            onCreated?.(body.qrCodes);
        } catch {
            setErrorMessage(t('workspace.publication.qrExport.bulkWizard.createError'));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <fieldset
            className="flex flex-col gap-3"
            aria-label={t('workspace.publication.qrExport.bulkWizard.heading')}
        >
            {/*
                Bölüm başlığı GERÇEK bir başlık gibi yazılır. Büyük harfli
                `text-meta` bu sistemde ölçüm etiketi ve tablo başlığı için
                ayrılmıştır (`docs/102` §1); bölüm başlığı olarak kullanılması
                tek kartın içinde dört ayrı başlık dili doğuruyordu.
            */}
            <legend className="text-body font-bold text-fg">
                {t('workspace.publication.qrExport.bulkWizard.heading')}
            </legend>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.tableCount')}
                <TextInput
                    type="number"
                    min={1}
                    max={500}
                    required
                    value={values.tableCount}
                    onChange={(event) => handleChange('tableCount', event.target.value)}
                    onBlur={() => handleBlur('tableCount')}
                    aria-invalid={touched.tableCount && !fieldValidity.tableCount}
                    aria-describedby={describedBy('tableCount')}
                />
            </label>
            {renderError('tableCount')}

            {/*
                İLERİ AYARLAR — varsayılanı olan her şey burada.
                `<details>` içeriği DOM'da kalır: klavye, ekran okuyucu ve
                form doğrulaması etkilenmez; yalnız ilk bakışta görünmez.
            */}
            <details className="rounded-[var(--radius-md)] border border-border p-[var(--space-3)]">
                <summary className="cursor-pointer text-body font-medium text-fg-secondary">
                    {t('workspace.publication.qrExport.bulkWizard.advanced')}
                </summary>
                <div className="flex flex-col gap-[var(--space-3)] pt-[var(--space-3)]">
                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.bulkWizard.areaSectionCount')}
                        <TextInput
                            type="number"
                            min={1}
                            max={50}
                            required
                            value={values.areaSectionCount}
                            onChange={(event) =>
                                handleChange('areaSectionCount', event.target.value)
                            }
                            onBlur={() => handleBlur('areaSectionCount')}
                            aria-invalid={
                                touched.areaSectionCount && !fieldValidity.areaSectionCount
                            }
                            aria-describedby={describedBy('areaSectionCount')}
                        />
                    </label>
                    {renderError('areaSectionCount')}

                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.bulkWizard.seatCountPerTable')}
                        <TextInput
                            type="number"
                            min={1}
                            max={20}
                            required
                            value={values.seatCountPerTable}
                            onChange={(event) =>
                                handleChange('seatCountPerTable', event.target.value)
                            }
                            onBlur={() => handleBlur('seatCountPerTable')}
                            aria-invalid={
                                touched.seatCountPerTable && !fieldValidity.seatCountPerTable
                            }
                            aria-describedby={describedBy('seatCountPerTable')}
                        />
                    </label>
                    {renderError('seatCountPerTable')}

                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.bulkWizard.namingPrefix')}
                        <TextInput
                            type="text"
                            maxLength={10}
                            value={values.namingPrefix}
                            onChange={(event) => handleChange('namingPrefix', event.target.value)}
                            onBlur={() => handleBlur('namingPrefix')}
                            aria-invalid={touched.namingPrefix && !fieldValidity.namingPrefix}
                            aria-describedby={describedBy('namingPrefix')}
                        />
                    </label>
                    {renderError('namingPrefix')}

                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.bulkWizard.namingSequenceStart')}
                        <TextInput
                            type="number"
                            min={0}
                            max={9999}
                            value={values.namingSequenceStart}
                            onChange={(event) =>
                                handleChange('namingSequenceStart', event.target.value)
                            }
                            onBlur={() => handleBlur('namingSequenceStart')}
                            aria-invalid={
                                touched.namingSequenceStart && !fieldValidity.namingSequenceStart
                            }
                            aria-describedby={describedBy('namingSequenceStart')}
                        />
                    </label>
                    {renderError('namingSequenceStart')}

                    <label className={LABEL_CLASSES}>
                        {t('workspace.publication.qrExport.bulkWizard.namingRange')}
                        <TextInput
                            type="text"
                            value={values.namingRange}
                            onChange={(event) => handleChange('namingRange', event.target.value)}
                            onBlur={() => handleBlur('namingRange')}
                            aria-invalid={touched.namingRange && !fieldValidity.namingRange}
                            aria-describedby={describedBy('namingRange')}
                        />
                    </label>
                    {renderError('namingRange')}
                </div>
            </details>

            {!hasAttempted ? (
                <p className="text-meta text-fg-muted">
                    {t('workspace.publication.qrExport.bulkWizard.notice')}
                </p>
            ) : null}

            {showSummary ? (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.publication.qrExport.bulkWizard.summary', {
                        tables: values.tableCount,
                        areas: values.areaSectionCount,
                        seats: String(Number(values.tableCount) * Number(values.seatCountPerTable)),
                    })}
                </p>
            ) : null}

            {showCrossFieldAlert ? (
                <p role="alert" className={ALERT_CLASSES}>
                    {t('workspace.publication.qrExport.bulkWizard.crossFieldError')}
                </p>
            ) : null}

            {errorMessage !== null ? (
                <p role="alert" className={ALERT_CLASSES}>
                    {errorMessage}
                </p>
            ) : null}

            {planRestricted ? (
                <div className="flex flex-col items-start gap-[var(--space-2)]">
                    {/*
                        `role="status"`, `alert` değil: ortada bozulmuş bir şey
                        yok, yalnız bu yetenek planın dışında.
                    */}
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.publication.qrExport.bulkWizard.planRestricted')}
                    </p>
                    {onUpgrade ? (
                        <Button type="button" color="light" onClick={onUpgrade}>
                            {t('workspace.publication.qrExport.bulkWizard.planRestricted.action')}
                        </Button>
                    ) : null}
                </div>
            ) : null}

            {submitting ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.publication.qrExport.bulkWizard.loading')}
                </p>
            ) : null}

            {result !== null ? (
                <div className="flex flex-col gap-2">
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.publication.qrExport.bulkWizard.success', {
                            areas: String(result.areasCount),
                            tables: String(result.tablesCount),
                            qrCodes: String(result.qrCodesCount),
                        })}
                    </p>
                    {/*
                        ÜRETİLEN KODLAR BİR LİSTEDİR (FF-131, kanonik teslim
                        paketinin düzeni).

                        Kırk masa yaratan sahip burada kırk bağlantı görür ve
                        "Masa 13 nerede" diye TARAR. Aralarında boşluk bırakılmış
                        ayraçsız kırk bağlantı bir liste değil bir yığındır: göz
                        her satırda yeniden hizalanır ve sahip aradığı masayı
                        bulmak için hepsini yukarıdan aşağı okumak zorunda kalır.

                        Ritim yoğunluk jetonlarına bağlıdır; ayraç ÜSTTEDİR,
                        böylece son satır için susturulacak bir istisna kalmaz.
                    */}
                    <ul className="flex flex-col">
                        {result.pairs.map((pair) => (
                            <li
                                key={pair.tableId}
                                className="flex min-h-[var(--density-row-height)] flex-wrap items-center border-t border-border px-[var(--density-padding-inline)] py-[var(--space-1)] first:border-t-0"
                            >
                                <TextLink href={pair.resolverUrl} className="break-all text-body">
                                    {pair.tableName}
                                </TextLink>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {/*
                İKİNCİL AĞIRLIK (FF-107).

                Bu düğme marka rengini taşıyordu ve sayfanın en yüksek sesli
                kontrolüydü — oysa toplu masa kodu üretimi bir restoranın
                ömründe bir ya da iki kez yaptığı iştir. Sayfanın birincil
                eylemi İNDİRMEDİR: sahip buraya yayınlamak için değil, basmak
                için gelir. Marka vurgusu tek eylem için ayrılmıştır
                (`docs/101` A1).
            */}
            <Button
                type="button"
                color="light"
                onClick={handleSubmit}
                disabled={!canSubmit}
                className="self-start"
            >
                {t('workspace.publication.qrExport.bulkWizard.createButton')}
            </Button>

            {/*
                KAPALI DÜĞMENİN SEBEBİ SÖYLENİR (FF-108). Düğme, yayın
                bilgisi gelmediği ya da sunucuya ulaşılamadığı için de
                kapanabiliyordu ve ekranda hiçbir açıklama yoktu: kullanıcı
                onu bozuk sanıyordu. Alan doğrulaması yüzünden kapalıysa
                sebep zaten alanların altında yazılı — burada tekrarlanmaz.
            */}
            {!hasCurrentPublication ? (
                <p role="status" className="text-meta text-fg-muted">
                    {t(REASON_KEYS[unavailableReason])}
                </p>
            ) : null}
        </fieldset>
    );
}

export default BulkQrWizardFields;
