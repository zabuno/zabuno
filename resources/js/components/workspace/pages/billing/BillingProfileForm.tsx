import { useState, type FormEvent } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { classifyResponse, networkFailure } from '../../../../lib/requestFailure';
import { focusFirstInvalidField, readValidationFailure } from '../../../../lib/validationErrors';
import { ErrorSummary, type ErrorSummaryEntry } from '../../forms/ErrorSummary';
import { FormActions } from '../../forms/FormActions';
import { FormField } from '../../forms/FormField';
import { FormSection } from '../../forms/FormSection';
import { messageForFailure } from '../../forms/failureMessage';
import type { BillingProfileData } from './CheckoutPanel';

const FIELD_ORDER = [
    'legal_name',
    'tax_number',
    'tax_office',
    'address',
    'city',
    'country',
    'email',
    'phone',
] as const;

type Field = (typeof FIELD_ORDER)[number];

const FIELD_LABELS: Record<Field, () => string> = {
    legal_name: () => t('workspace.billing.profile.field.legalName'),
    tax_number: () => t('workspace.billing.profile.field.taxNumber'),
    tax_office: () => t('workspace.billing.profile.field.taxOffice'),
    address: () => t('workspace.billing.profile.field.address'),
    city: () => t('workspace.billing.profile.field.city'),
    country: () => t('workspace.billing.profile.field.country'),
    email: () => t('workspace.billing.profile.field.email'),
    phone: () => t('workspace.billing.profile.field.phone'),
};

const EMPTY: BillingProfileData = {
    legal_name: '',
    tax_number: '',
    tax_office: '',
    address: '',
    city: '',
    country: '',
    email: '',
    phone: '',
};

function isBillingProfileData(value: unknown): value is BillingProfileData {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return FIELD_ORDER.every((field) => typeof candidate[field] === 'string');
}

type BillingProfileFormProps = {
    workspaceId: number;
    initial: BillingProfileData | null;
    onSaved: (profile: BillingProfileData) => void;
    onCancel: () => void;
};

/**
 * Fatura profili formu — sekiz alan, hepsi zorunlu, hepsi sunucuda doğrulanır.
 *
 * Alan hataları sunucunun 422 gövdesinden okunur ve alana BAĞLANIR;
 * "bir şeyler ters gitti" cümlesi yalnız sunucu hiçbir şey söylemediğinde.
 * Kart alanı yoktur: bu form kimin ödediğini söyler, neyle ödediğini değil.
 */
export function BillingProfileForm({
    workspaceId,
    initial,
    onSaved,
    onCancel,
}: BillingProfileFormProps) {
    const [values, setValues] = useState<BillingProfileData>(initial ?? EMPTY);
    const [fieldErrors, setFieldErrors] = useState<Partial<Record<Field, string>>>({});
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    const update = (field: Field) => (value: string) => {
        setValues((current) => ({ ...current, [field]: value }));
    };

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (saving) {
            return;
        }

        setSaving(true);
        setError('');
        setFieldErrors({});

        try {
            await bootstrapCsrfCookie();

            const init = buildAuthRequestInit({ method: 'PUT', body: JSON.stringify(values) });
            const headers = new Headers(init.headers);
            headers.set('Content-Type', 'application/json');

            const response = await fetch(`/api/workspaces/${workspaceId}/billing-profile`, {
                ...init,
                headers,
            });

            if (response.status === 422) {
                const failure = await readValidationFailure(
                    response,
                    t('workspace.billing.profile.saveError'),
                    'billing_profile',
                );
                setFieldErrors(failure.fields as Partial<Record<Field, string>>);
                setError(failure.message ?? t('workspace.billing.profile.saveError'));
                focusFirstInvalidField(failure.fields, FIELD_ORDER);
                return;
            }

            if (!response.ok) {
                setError(messageForFailure(classifyResponse(response)));
                return;
            }

            const body: unknown = await response.json();

            if (!isBillingProfileData(body)) {
                setError(t('workspace.billing.profile.saveError'));
                return;
            }

            onSaved(body);
        } catch {
            setError(messageForFailure(networkFailure()));
        } finally {
            setSaving(false);
        }
    };

    const summaryEntries: ErrorSummaryEntry[] = FIELD_ORDER.filter(
        (field) => fieldErrors[field] !== undefined,
    ).map((field) => ({
        fieldId: `billing-profile-${field.replace('_', '-')}`,
        label: FIELD_LABELS[field](),
        message: fieldErrors[field] ?? '',
    }));

    return (
        <form
            onSubmit={(event) => void handleSubmit(event)}
            noValidate
            className="flex flex-col gap-3"
        >
            <ErrorSummary
                title={t('workspace.form.error.summary.title')}
                entries={summaryEntries}
            />
            <FormSection title={t('workspace.billing.checkout.profile.heading')}>
                {FIELD_ORDER.map((field) => (
                    <FormField
                        key={field}
                        id={`billing-profile-${field.replace('_', '-')}`}
                        name={field}
                        label={FIELD_LABELS[field]()}
                        type={field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
                        value={values[field]}
                        onChange={update(field)}
                        disabled={saving}
                        errorText={fieldErrors[field]}
                        helpText={
                            field === 'country'
                                ? t('workspace.billing.profile.field.countryHelp')
                                : undefined
                        }
                    />
                ))}
            </FormSection>
            <FormActions
                error={error}
                saving={saving}
                saveLabel={t('workspace.billing.profile.save')}
                cancelLabel={t('workspace.billing.profile.cancel')}
                onCancel={onCancel}
            />
        </form>
    );
}

export default BillingProfileForm;
