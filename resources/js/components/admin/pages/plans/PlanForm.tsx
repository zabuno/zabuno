import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
import { Button } from '../../../catalog/forms/micro/Button';
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

        if (
            !validation.isValid ||
            validation.versionValue === null ||
            validation.sortOrderValue === null
        ) {
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
            <h2 className="text-sm font-semibold text-fg">{t('platform.plans.form.heading')}</h2>

            <div
                className="grid gap-3"
                style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 12rem), 1fr))' }}
            >
                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.name')}
                    <TextInput
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        type="text"
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.code')}
                    <TextInput
                        value={code}
                        onChange={(event) => setCode(event.target.value)}
                        type="text"
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.version')}
                    <TextInput
                        value={version}
                        onChange={(event) => setVersion(event.target.value)}
                        type="text"
                        inputMode="numeric"
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.amount')}
                    <TextInput
                        value={amount}
                        onChange={(event) => setAmount(event.target.value)}
                        type="text"
                        inputMode="numeric"
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.currency')}
                    <TextInput
                        value={currency}
                        onChange={(event) => setCurrency(event.target.value)}
                        type="text"
                    />
                </label>

                <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                    {t('platform.plans.form.sortOrder')}
                    <TextInput
                        value={sortOrder}
                        onChange={(event) => setSortOrder(event.target.value)}
                        type="text"
                        inputMode="numeric"
                    />
                </label>
            </div>

            <label className="flex flex-col gap-1 text-sm text-fg-secondary">
                {t('platform.plans.form.entitlements')}
                <Textarea
                    value={entitlements}
                    onChange={(event) => setEntitlements(event.target.value)}
                    rows={4}
                />
            </label>

            <Button
                type="submit"
                disabled={!validation.isValid || submitting}
                className="self-start"
            >
                {t('platform.plans.form.submit')}
            </Button>
        </form>
    );
}

export default PlanForm;
