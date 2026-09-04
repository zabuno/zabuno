import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
import { Button } from '../../../catalog/forms/micro/Button';
import { useState, type FormEvent } from 'react';

import { FieldError } from '../../../catalog/menu/micro/FieldError';
import { focusFirstInvalidField } from '../../../../lib/validationErrors';
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

    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    /**
     * Bütün alanları AYNI ANDA doğrular (`docs/47` Kural 5).
     *
     * Öncesi şöyleydi: geçerlilik sessizce hesaplanıyor ve "Create plan"
     * düğmesi hiçbir açıklama vermeden devre dışı bırakılıyordu. Kullanıcı
     * hangi alanın eksik olduğunu göremiyor, düğmeyi nasıl etkinleştireceğini
     * bilemiyordu — `docs/44`'ün devre dışı kontrol standardının tam
     * karşıtı.
     *
     * @return alan adı => hata mesajı (boşsa form geçerlidir)
     */
    function validate(): Record<string, string> {
        const errors: Record<string, string> = {};

        if (name.trim() === '') {
            errors.name = t('platform.plans.form.name.error.required');
        }

        if (code.trim() === '') {
            errors.code = t('platform.plans.form.code.error.required');
        }

        if (parseWholeNumber(version) === null) {
            errors.version = t('platform.plans.form.version.error.required');
        }

        if (parseWholeNumber(sortOrder) === null) {
            errors.sort_order = t('platform.plans.form.sortOrder.error.required');
        }

        const trimmedAmount = amount.trim();
        const trimmedCurrency = currency.trim();
        const amountEmpty = trimmedAmount === '';
        const currencyEmpty = trimmedCurrency === '';

        if (!amountEmpty && parseWholeNumber(trimmedAmount) === null) {
            errors.amount = t('platform.plans.form.amount.error.invalid');
        }

        if (!currencyEmpty && !CURRENCY_PATTERN.test(trimmedCurrency)) {
            errors.currency = t('platform.plans.form.currency.error.invalid');
        }

        // Tutar ve para birimi birlikte anlamlıdır. Hata İKİ alana da
        // yazılır: kullanıcı hangisini doldurduğunu zaten biliyor, eksik
        // olanı arıyor.
        if (amountEmpty !== currencyEmpty) {
            const message = t('platform.plans.form.pair.error');
            errors.amount = errors.amount ?? message;
            errors.currency = errors.currency ?? message;
        }

        return errors;
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const errors = validate();
        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            focusFirstInvalidField(errors, [
                'name',
                'code',
                'version',
                'amount',
                'currency',
                'sort_order',
            ]);

            return;
        }

        onSubmit({
            name: name.trim(),
            code: code.trim(),
            version: parseWholeNumber(version) as number,
            entitlements: parseEntitlements(entitlements),
            amount_minor: amount.trim() === '' ? null : (parseWholeNumber(amount) as number),
            currency: currency.trim() === '' ? null : currency.trim(),
            sort_order: parseWholeNumber(sortOrder) as number,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="flex max-w-content flex-col gap-3" noValidate>
            <h2 className="text-body font-bold text-fg">{t('platform.plans.form.heading')}</h2>

            <div
                className="grid gap-3"
                style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 12rem), 1fr))' }}
            >
                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.name')}
                        <TextInput
                            name="name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            type="text"
                            aria-invalid={fieldErrors.name === undefined ? undefined : true}
                            aria-describedby={fieldErrors.name ? `plan-name-error` : undefined}
                        />
                    </label>
                    {fieldErrors.name ? (
                        <span id={`plan-name-error`}>
                            <FieldError message={fieldErrors.name} />
                        </span>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.code')}
                        <TextInput
                            name="code"
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            type="text"
                            aria-invalid={fieldErrors.code === undefined ? undefined : true}
                            aria-describedby={fieldErrors.code ? `plan-code-error` : undefined}
                        />
                    </label>
                    {fieldErrors.code ? (
                        <span id={`plan-code-error`}>
                            <FieldError message={fieldErrors.code} />
                        </span>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.version')}
                        <TextInput
                            name="version"
                            value={version}
                            onChange={(event) => setVersion(event.target.value)}
                            type="text"
                            inputMode="numeric"
                            aria-invalid={fieldErrors.version === undefined ? undefined : true}
                            aria-describedby={
                                fieldErrors.version ? `plan-version-error` : undefined
                            }
                        />
                    </label>
                    {fieldErrors.version ? (
                        <span id={`plan-version-error`}>
                            <FieldError message={fieldErrors.version} />
                        </span>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.optional', {
                            label: t('platform.plans.form.amount'),
                        })}
                        <TextInput
                            name="amount"
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
                            type="text"
                            inputMode="numeric"
                            aria-invalid={fieldErrors.amount === undefined ? undefined : true}
                            aria-describedby={fieldErrors.amount ? `plan-amount-error` : undefined}
                        />
                    </label>
                    {fieldErrors.amount ? (
                        <span id={`plan-amount-error`}>
                            <FieldError message={fieldErrors.amount} />
                        </span>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.optional', {
                            label: t('platform.plans.form.currency'),
                        })}
                        <TextInput
                            name="currency"
                            value={currency}
                            onChange={(event) => setCurrency(event.target.value)}
                            type="text"
                            aria-invalid={fieldErrors.currency === undefined ? undefined : true}
                            aria-describedby={
                                fieldErrors.currency ? `plan-currency-error` : undefined
                            }
                        />
                    </label>
                    {fieldErrors.currency ? (
                        <span id={`plan-currency-error`}>
                            <FieldError message={fieldErrors.currency} />
                        </span>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1">
                    {/*
                        Hata mesajı `<label>`in DIŞINDA durur. İçinde
                        olsaydı alanın erişilebilir adına karışırdı: ekran
                        okuyucu "Code Enter a plan code." derdi. Bağlantı
                        `aria-describedby` ile kurulur — ad ayrı, açıklama
                        ayrı.
                    */}
                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
                        {t('platform.plans.form.sortOrder')}
                        <TextInput
                            name="sort_order"
                            value={sortOrder}
                            onChange={(event) => setSortOrder(event.target.value)}
                            type="text"
                            inputMode="numeric"
                            aria-invalid={fieldErrors.sort_order === undefined ? undefined : true}
                            aria-describedby={
                                fieldErrors.sort_order ? `plan-sort_order-error` : undefined
                            }
                        />
                    </label>
                    {fieldErrors.sort_order ? (
                        <span id={`plan-sort_order-error`}>
                            <FieldError message={fieldErrors.sort_order} />
                        </span>
                    ) : null}
                </div>
            </div>

            <label className="flex flex-col gap-1 text-body text-fg-secondary">
                {t('platform.plans.form.optional', {
                    label: t('platform.plans.form.entitlements'),
                })}
                <Textarea
                    value={entitlements}
                    onChange={(event) => setEntitlements(event.target.value)}
                    rows={4}
                />
            </label>

            {/*
                Düğme artık "geçersiz" diye devre dışı DEĞİL.
                Devre dışı bir düğme, kullanıcıya neyin eksik olduğunu
                söylemez; tıklanabilir bir düğme hatayı gösterir ve odağı
                oraya taşır (`docs/47` Kural 5, `docs/44` devre dışı
                standardı). Yalnız gönderim sürerken kapalıdır — o gerçek
                bir sebeptir.
            */}
            <Button type="submit" disabled={submitting} className="self-start">
                {t('platform.plans.form.submit')}
            </Button>
        </form>
    );
}

export default PlanForm;
