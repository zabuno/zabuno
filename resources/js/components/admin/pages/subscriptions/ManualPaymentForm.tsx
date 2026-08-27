import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { Button } from '../../../catalog/forms/micro/Button';
import { useState, type FormEvent } from 'react';

import { FieldError } from '../../../catalog/menu/micro/FieldError';
import { focusFirstInvalidField } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/platform';
import type { Plan } from '../plans/PlanList';

export type ManualPaymentFormValues = {
    planId: number;
    endsAt: string;
    paymentNote: string;
    documentReference: string;
};

type ManualPaymentFormProps = {
    activePlans: Plan[];
    endsAt: string;
    paymentNote: string;
    documentReference: string;
    onFieldChange: (field: 'endsAt' | 'paymentNote' | 'documentReference', value: string) => void;
    onSubmit: (values: ManualPaymentFormValues) => void;
};

/**
 * Manual-payment fields: Plan (server is_active plans only), end date,
 * payment note, document reference. Intrinsically fluid full-width fields,
 * >= 44px tall, no responsive breakpoint classes. Submission only opens the
 * parent's confirmation dialog — it never fetches itself.
 */
export function ManualPaymentForm({
    activePlans,
    endsAt,
    paymentNote,
    documentReference,
    onFieldChange,
    onSubmit,
}: ManualPaymentFormProps) {
    const [planCode, setPlanCode] = useState(activePlans[0]?.code ?? '');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const plan = activePlans.find((candidate) => candidate.code === planCode);

        // Öncesi burada sessizce `return` ediyordu: kullanıcı "Record manual
        // payment" düğmesine basıyor ve HİÇBİR ŞEY olmuyordu. Ne hata, ne
        // onay, ne de bir ipucu. Para hareketi kaydeden bir formda bu, en
        // kötü kusur biçimidir — kullanıcı kaydın gittiğini sanabilir.
        const errors: Record<string, string> = {};

        if (!plan) {
            errors['manual-payment-plan'] = t('platform.subscriptions.form.plan.error.required');
        }

        if (endsAt.trim() === '') {
            errors['manual-payment-end-date'] = t(
                'platform.subscriptions.form.endDate.error.required',
            );
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0 || !plan) {
            focusFirstInvalidField(errors, ['manual-payment-plan', 'manual-payment-end-date']);

            return;
        }

        onSubmit({
            planId: plan.id,
            endsAt,
            paymentNote,
            documentReference,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="flex max-w-content flex-col gap-3" noValidate>
            <div className="flex flex-col gap-1">
                <label
                    htmlFor="manual-payment-plan"
                    className="flex flex-col gap-1 text-body text-fg-secondary"
                >
                    {t('platform.subscriptions.form.plan.label')}
                    <Select
                        id="manual-payment-plan"
                        name="manual-payment-plan"
                        value={planCode}
                        onChange={(event) => setPlanCode(event.target.value)}
                        aria-invalid={
                            fieldErrors['manual-payment-plan'] === undefined ? undefined : true
                        }
                    >
                        {activePlans.map((plan) => (
                            <option key={plan.id} value={plan.code}>
                                {plan.name} ({plan.code})
                            </option>
                        ))}
                    </Select>
                </label>
                {fieldErrors['manual-payment-plan'] ? (
                    <FieldError message={fieldErrors['manual-payment-plan']} />
                ) : null}
            </div>

            <div className="flex flex-col gap-1">
                <label
                    htmlFor="manual-payment-end-date"
                    className="flex flex-col gap-1 text-body text-fg-secondary"
                >
                    {t('platform.subscriptions.form.endDate.label')}
                    <TextInput
                        id="manual-payment-end-date"
                        name="manual-payment-end-date"
                        type="text"
                        value={endsAt}
                        onChange={(event) => onFieldChange('endsAt', event.target.value)}
                        aria-invalid={
                            fieldErrors['manual-payment-end-date'] === undefined ? undefined : true
                        }
                    />
                </label>
                {fieldErrors['manual-payment-end-date'] ? (
                    <FieldError message={fieldErrors['manual-payment-end-date']} />
                ) : null}
            </div>

            {/*
                Not ve belge referansı İSTEĞE BAĞLIDIR ve etiket bunu söyler.
                Öncesinde dört alan birbirinin aynıydı; kullanıcı hangisini
                doldurmak zorunda olduğunu ancak deneyerek öğrenebilirdi —
                ve deneme sessizce başarısız oluyordu.
            */}
            <label
                htmlFor="manual-payment-note"
                className="flex flex-col gap-1 text-body text-fg-secondary"
            >
                {t('platform.subscriptions.form.optional', {
                    label: t('platform.subscriptions.form.paymentNote.label'),
                })}
                <TextInput
                    id="manual-payment-note"
                    type="text"
                    value={paymentNote}
                    onChange={(event) => onFieldChange('paymentNote', event.target.value)}
                />
            </label>

            <label
                htmlFor="manual-payment-document-reference"
                className="flex flex-col gap-1 text-body text-fg-secondary"
            >
                {t('platform.subscriptions.form.optional', {
                    label: t('platform.subscriptions.form.documentReference.label'),
                })}
                <TextInput
                    id="manual-payment-document-reference"
                    type="text"
                    value={documentReference}
                    onChange={(event) => onFieldChange('documentReference', event.target.value)}
                />
            </label>

            {/* Depo primitifi: buton stili tek yerde yaşar. */}
            <Button type="submit" className="self-start">
                {t('platform.subscriptions.form.submit')}
            </Button>
        </form>
    );
}

export default ManualPaymentForm;
