import { useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

const FIELD_CLASSES =
    'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

const LABEL_CLASSES = 'flex flex-col gap-1 text-xs font-medium text-gray-700 dark:text-gray-300';

const ALERT_CLASSES = 'text-xs text-red-600 dark:text-red-400';

const SUBMIT_BUTTON_CLASSES =
    'self-start rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-300 disabled:text-gray-500 dark:border-blue-500 dark:bg-blue-500 dark:disabled:border-gray-600 dark:disabled:bg-gray-700 dark:disabled:text-gray-400';

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
    onCreated?: (qrCodes: QrCodeItem[]) => void;
};

const INITIAL_VALUES: Values = {
    areaSectionCount: '',
    tableCount: '',
    namingPrefix: '',
    namingSequenceStart: '',
    namingRange: '',
    seatCountPerTable: '',
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
            return values.namingSequenceStart.trim() === '' || isBoundedWholeNumber(values.namingSequenceStart, 0, 9999);
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

    return Array.isArray(candidate.areas) && Array.isArray(candidate.tables) && Array.isArray(candidate.qrCodes);
}

export function BulkQrWizardFields(props: BulkQrWizardFieldsProps) {
    const { workspaceId, locationId, menuId, hasCurrentPublication = false, onCreated } = props;

    const [values, setValues] = useState<Values>(INITIAL_VALUES);
    const [touched, setTouched] = useState(INITIAL_TOUCHED);
    const [submitting, setSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
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

    const allFieldsValid = (Object.keys(fieldValidity) as FieldKey[]).every((key) => fieldValidity[key]);
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
        if (!canSubmit || workspaceId === undefined || locationId === undefined || menuId === undefined) {
            return;
        }

        setHasAttempted(true);
        setSubmitting(true);
        setErrorMessage(null);
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
            if (namingSequenceStart !== '') payload.namingSequenceStart = Number(namingSequenceStart);

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand/locations/${locationId}/tables/bulk`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }),
            );

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
                    return qrCode ? { tableId: table.id, tableName: table.name, resolverUrl: qrCode.resolverUrl } : null;
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
            <legend className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.publication.qrExport.bulkWizard.heading')}
            </legend>

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.areaSectionCount')}
                <input
                    type="number"
                    min={1}
                    max={50}
                    required
                    value={values.areaSectionCount}
                    onChange={(event) => handleChange('areaSectionCount', event.target.value)}
                    onBlur={() => handleBlur('areaSectionCount')}
                    aria-invalid={touched.areaSectionCount && !fieldValidity.areaSectionCount}
                    aria-describedby={describedBy('areaSectionCount')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('areaSectionCount')}

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.tableCount')}
                <input
                    type="number"
                    min={1}
                    max={500}
                    required
                    value={values.tableCount}
                    onChange={(event) => handleChange('tableCount', event.target.value)}
                    onBlur={() => handleBlur('tableCount')}
                    aria-invalid={touched.tableCount && !fieldValidity.tableCount}
                    aria-describedby={describedBy('tableCount')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('tableCount')}

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.namingPrefix')}
                <input
                    type="text"
                    maxLength={10}
                    value={values.namingPrefix}
                    onChange={(event) => handleChange('namingPrefix', event.target.value)}
                    onBlur={() => handleBlur('namingPrefix')}
                    aria-invalid={touched.namingPrefix && !fieldValidity.namingPrefix}
                    aria-describedby={describedBy('namingPrefix')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('namingPrefix')}

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.namingSequenceStart')}
                <input
                    type="number"
                    min={0}
                    max={9999}
                    value={values.namingSequenceStart}
                    onChange={(event) => handleChange('namingSequenceStart', event.target.value)}
                    onBlur={() => handleBlur('namingSequenceStart')}
                    aria-invalid={touched.namingSequenceStart && !fieldValidity.namingSequenceStart}
                    aria-describedby={describedBy('namingSequenceStart')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('namingSequenceStart')}

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.namingRange')}
                <input
                    type="text"
                    value={values.namingRange}
                    onChange={(event) => handleChange('namingRange', event.target.value)}
                    onBlur={() => handleBlur('namingRange')}
                    aria-invalid={touched.namingRange && !fieldValidity.namingRange}
                    aria-describedby={describedBy('namingRange')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('namingRange')}

            <label className={LABEL_CLASSES}>
                {t('workspace.publication.qrExport.bulkWizard.seatCountPerTable')}
                <input
                    type="number"
                    min={1}
                    max={20}
                    required
                    value={values.seatCountPerTable}
                    onChange={(event) => handleChange('seatCountPerTable', event.target.value)}
                    onBlur={() => handleBlur('seatCountPerTable')}
                    aria-invalid={touched.seatCountPerTable && !fieldValidity.seatCountPerTable}
                    aria-describedby={describedBy('seatCountPerTable')}
                    className={FIELD_CLASSES}
                />
            </label>
            {renderError('seatCountPerTable')}

            {!hasAttempted ? (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    {t('workspace.publication.qrExport.bulkWizard.notice')}
                </p>
            ) : null}

            {showSummary ? (
                <p role="status" className="text-sm text-gray-700 dark:text-gray-300">
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

            {submitting ? (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.publication.qrExport.bulkWizard.loading')}
                </p>
            ) : null}

            {result !== null ? (
                <div className="flex flex-col gap-2">
                    <p role="status" className="text-sm text-gray-700 dark:text-gray-300">
                        {t('workspace.publication.qrExport.bulkWizard.success', {
                            areas: String(result.areasCount),
                            tables: String(result.tablesCount),
                            qrCodes: String(result.qrCodesCount),
                        })}
                    </p>
                    <ul className="flex flex-col gap-1">
                        {result.pairs.map((pair) => (
                            <li key={pair.tableId}>
                                <a
                                    href={pair.resolverUrl}
                                    className="break-all text-sm text-blue-700 underline dark:text-blue-400"
                                >
                                    {pair.tableName}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            <button
                type="button"
                onClick={handleSubmit}
                disabled={!canSubmit}
                className={SUBMIT_BUTTON_CLASSES}
            >
                {t('workspace.publication.qrExport.bulkWizard.createButton')}
            </button>
        </fieldset>
    );
}

export default BulkQrWizardFields;
