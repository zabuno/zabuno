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

const FIELD_CLASSES =
    'w-full min-h-11 rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-gray-500 focus:ring-1 focus:ring-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

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
            <label htmlFor="manual-payment-plan" className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                {t('platform.subscriptions.form.plan.label')}
                <select
                    id="manual-payment-plan"
                    value={planCode}
                    onChange={(event) => setPlanCode(event.target.value)}
                    className={FIELD_CLASSES}
                >
                    {activePlans.map((plan) => (
                        <option key={plan.id} value={plan.code}>
                            {plan.name} ({plan.code})
                        </option>
                    ))}
                </select>
            </label>

            <label htmlFor="manual-payment-end-date" className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                {t('platform.subscriptions.form.endDate.label')}
                <input
                    id="manual-payment-end-date"
                    type="text"
                    value={endsAt}
                    onChange={(event) => onFieldChange('endsAt', event.target.value)}
                    className={FIELD_CLASSES}
                />
            </label>

            <label htmlFor="manual-payment-note" className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                {t('platform.subscriptions.form.paymentNote.label')}
                <input
                    id="manual-payment-note"
                    type="text"
                    value={paymentNote}
                    onChange={(event) => onFieldChange('paymentNote', event.target.value)}
                    className={FIELD_CLASSES}
                />
            </label>

            <label htmlFor="manual-payment-document-reference" className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                {t('platform.subscriptions.form.documentReference.label')}
                <input
                    id="manual-payment-document-reference"
                    type="text"
                    value={documentReference}
                    onChange={(event) => onFieldChange('documentReference', event.target.value)}
                    className={FIELD_CLASSES}
                />
            </label>

            <button
                type="submit"
                className="self-start rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-gray-900"
            >
                {t('platform.subscriptions.form.submit')}
            </button>
        </form>
    );
}

export default ManualPaymentForm;
