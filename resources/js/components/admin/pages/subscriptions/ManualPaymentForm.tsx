import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { useState, type FormEvent } from 'react';

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

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const plan = activePlans.find((candidate) => candidate.code === planCode);
        if (!plan || endsAt.trim().length === 0) {
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
        <form onSubmit={handleSubmit} className="flex flex-col gap-3" style={{ maxWidth: '100%' }}>
            <label
                htmlFor="manual-payment-plan"
                className="flex flex-col gap-1 text-body text-fg-secondary"
            >
                {t('platform.subscriptions.form.plan.label')}
                <Select
                    id="manual-payment-plan"
                    value={planCode}
                    onChange={(event) => setPlanCode(event.target.value)}
                >
                    {activePlans.map((plan) => (
                        <option key={plan.id} value={plan.code}>
                            {plan.name} ({plan.code})
                        </option>
                    ))}
                </Select>
            </label>

            <label
                htmlFor="manual-payment-end-date"
                className="flex flex-col gap-1 text-body text-fg-secondary"
            >
                {t('platform.subscriptions.form.endDate.label')}
                <TextInput
                    id="manual-payment-end-date"
                    type="text"
                    value={endsAt}
                    onChange={(event) => onFieldChange('endsAt', event.target.value)}
                />
            </label>

            <label
                htmlFor="manual-payment-note"
                className="flex flex-col gap-1 text-body text-fg-secondary"
            >
                {t('platform.subscriptions.form.paymentNote.label')}
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
                {t('platform.subscriptions.form.documentReference.label')}
                <TextInput
                    id="manual-payment-document-reference"
                    type="text"
                    value={documentReference}
                    onChange={(event) => onFieldChange('documentReference', event.target.value)}
                />
            </label>

            <button
                type="submit"
                className="self-start rounded-md bg-action px-3 py-2 text-body font-medium text-white disabled:opacity-50  dark:text-action-fg"
            >
                {t('platform.subscriptions.form.submit')}
            </button>
        </form>
    );
}

export default ManualPaymentForm;
