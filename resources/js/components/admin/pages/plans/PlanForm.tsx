import { useMemo, useState, type FormEvent } from 'react';

import { t } from '../../../../i18n/platform';

export type PlanCreatePayload = {
    name: string;
    code: string;
    version: number;
    entitlements: string[];
    amount_minor: number | null;
    currency: string | null;
    sort_order: number;
};

type PlanFormProps = {
    onSubmit: (payload: PlanCreatePayload) => void;
    submitting: boolean;
};

const CURRENCY_PATTERN = /^[A-Z]{3}$/;

const FIELD_CLASSES =
    'w-full min-h-11 rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-500 focus:ring-1 focus:ring-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

const TEXTAREA_CLASSES =
    'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-500 focus:ring-1 focus:ring-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

function parseWholeNumber(value: string): number | null {
    const trimmed = value.trim();
    if (trimmed === '' || !/^\d+$/.test(trimmed)) {
        return null;
    }

    return Number(trimmed);
}

function parseEntitlements(value: string): string[] {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
}

export function PlanForm({ onSubmit, submitting }: PlanFormProps) {
    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [version, setVersion] = useState('');
    const [amount, setAmount] = useState('');
    const [currency, setCurrency] = useState('');
    const [entitlements, setEntitlements] = useState('');
    const [sortOrder, setSortOrder] = useState('');

    const validation = useMemo(() => {
        const trimmedName = name.trim();
        const trimmedCode = code.trim();
        const trimmedCurrency = currency.trim();
        const trimmedAmount = amount.trim();

        const versionValue = parseWholeNumber(version);
        const sortOrderValue = parseWholeNumber(sortOrder);

        const amountEmpty = trimmedAmount === '';
        const currencyEmpty = trimmedCurrency === '';
        const amountValue = amountEmpty ? null : parseWholeNumber(trimmedAmount);
        const currencyValid = currencyEmpty || CURRENCY_PATTERN.test(trimmedCurrency);
        const amountValid = amountEmpty || amountValue !== null;
        const paired = amountEmpty === currencyEmpty;

        const isValid =
            trimmedName.length > 0 &&
            trimmedCode.length > 0 &&
            versionValue !== null &&
            sortOrderValue !== null &&
            paired &&
            amountValid &&
            currencyValid;

        return {
            isValid,
            trimmedName,
            trimmedCode,
            versionValue,
            sortOrderValue,
            amountValue: amountEmpty ? null : amountValue,
            currency: currencyEmpty ? null : trimmedCurrency,
        };
    }, [name, code, version, amount, currency, sortOrder]);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!validation.isValid || validation.versionValue === null || validation.sortOrderValue === null) {
            return;
        }

        onSubmit({
            name: validation.trimmedName,
            code: validation.trimmedCode,
            version: validation.versionValue,
            entitlements: parseEntitlements(entitlements),
            amount_minor: validation.amountValue,
            currency: validation.currency,
            sort_order: validation.sortOrderValue,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-3" style={{ maxWidth: '100%' }}>
            <h2 className="text-sm font-semibold text-gray-900 dark:text-white">{t('platform.plans.form.heading')}</h2>

            <div className="grid gap-3" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 12rem), 1fr))' }}>
                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.name')}
                    <input value={name} onChange={(event) => setName(event.target.value)} type="text" className={FIELD_CLASSES} />
                </label>

                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.code')}
                    <input value={code} onChange={(event) => setCode(event.target.value)} type="text" className={FIELD_CLASSES} />
                </label>

                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.version')}
                    <input
                        value={version}
                        onChange={(event) => setVersion(event.target.value)}
                        type="text"
                        inputMode="numeric"
                        className={FIELD_CLASSES}
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.amount')}
                    <input
                        value={amount}
                        onChange={(event) => setAmount(event.target.value)}
                        type="text"
                        inputMode="numeric"
                        className={FIELD_CLASSES}
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.currency')}
                    <input value={currency} onChange={(event) => setCurrency(event.target.value)} type="text" className={FIELD_CLASSES} />
                </label>

                <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('platform.plans.form.sortOrder')}
                    <input
                        value={sortOrder}
                        onChange={(event) => setSortOrder(event.target.value)}
                        type="text"
                        inputMode="numeric"
                        className={FIELD_CLASSES}
                    />
                </label>
            </div>

            <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                {t('platform.plans.form.entitlements')}
                <textarea
                    value={entitlements}
                    onChange={(event) => setEntitlements(event.target.value)}
                    rows={4}
                    className={TEXTAREA_CLASSES}
                />
            </label>

            <button
                type="submit"
                disabled={!validation.isValid || submitting}
                className="self-start rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-gray-900"
            >
                {t('platform.plans.form.submit')}
            </button>
        </form>
    );
}

export default PlanForm;
