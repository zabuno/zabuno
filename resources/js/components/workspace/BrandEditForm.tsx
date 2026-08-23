import { useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { FormSection } from './forms/FormSection';
import { FormActions } from './forms/FormActions';
import { ReadOnlySummary, type ReadOnlySummaryItem } from './forms/ReadOnlySummary';

export type BrandProfile = {
    id: number;
    workspace_id: number;
    name: string;
    slug: string;
    locale: string;
    timezone: string;
    currency: string;
    description: string | null;
    contact_email: string | null;
    contact_phone: string | null;
};

type BrandEditFormProps = {
    workspaceId: number;
    brand: BrandProfile;
    onSaved: (brand: BrandProfile) => void;
};

export function BrandEditForm({ workspaceId, brand, onSaved }: BrandEditFormProps) {
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(brand.name);
    const [locale, setLocale] = useState(brand.locale);
    const [timezone, setTimezone] = useState(brand.timezone);
    const [currency, setCurrency] = useState(brand.currency);
    const [description, setDescription] = useState(brand.description ?? '');
    const [contactEmail, setContactEmail] = useState(brand.contact_email ?? '');
    const [contactPhone, setContactPhone] = useState(brand.contact_phone ?? '');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function startEdit() {
        setName(brand.name);
        setLocale(brand.locale);
        setTimezone(brand.timezone);
        setCurrency(brand.currency);
        setDescription(brand.description ?? '');
        setContactEmail(brand.contact_email ?? '');
        setContactPhone(brand.contact_phone ?? '');
        setError('');
        setEditing(true);
    }

    function cancelEdit() {
        setError('');
        setEditing(false);
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        setError('');
        setSaving(true);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand`,
                buildAuthRequestInit({
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name,
                        locale,
                        timezone,
                        currency,
                        description,
                        contact_email: contactEmail,
                        contact_phone: contactPhone,
                    }),
                }),
            );

            if (response.ok) {
                const updated = (await response.json()) as BrandProfile;
                onSaved(updated);
                setEditing(false);
                setSaving(false);

                return;
            }

            setError(t('workspace.brandLocations.edit.error.submit'));
        } catch {
            setError(t('workspace.brandLocations.edit.error.submit'));
        }

        setSaving(false);
    }

    if (!editing) {
        const summaryItems: ReadOnlySummaryItem[] = [
            { key: 'slug', label: t('workspace.brandLocations.brand.slug'), value: brand.slug },
            { key: 'locale', label: t('workspace.brandLocations.brand.locale'), value: brand.locale },
            { key: 'timezone', label: t('workspace.brandLocations.brand.timezone'), value: brand.timezone },
            { key: 'currency', label: t('workspace.brandLocations.brand.currency'), value: brand.currency },
        ];

        if (brand.description) {
            summaryItems.push({
                key: 'description',
                label: t('workspace.brandLocations.brand.description'),
                value: brand.description,
            });
        }

        if (brand.contact_email) {
            summaryItems.push({
                key: 'contact_email',
                label: t('workspace.brandLocations.brand.contactEmail'),
                value: brand.contact_email,
            });
        }

        if (brand.contact_phone) {
            summaryItems.push({
                key: 'contact_phone',
                label: t('workspace.brandLocations.brand.contactPhone'),
                value: brand.contact_phone,
            });
        }

        return (
            <div className="mb-6">
                <ReadOnlySummary
                    title={brand.name}
                    items={summaryItems}
                    actions={
                        <Button size="sm" onClick={startEdit}>
                            {t('workspace.brandLocations.edit.button')}
                        </Button>
                    }
                />
            </div>
        );
    }

    return (
        <div className="mb-6 flex flex-col gap-4">
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-5">
                <FormSection title={t('workspace.brandLocations.brand.section.identity')}>
                    <FormField
                        id="brand-edit-slug"
                        name="slug"
                        label={t('workspace.brandLocations.brand.slug')}
                        value={brand.slug}
                        disabled
                        readOnly
                    />
                    <FormField
                        id="brand-edit-name"
                        name="name"
                        label={t('workspace.brand.name')}
                        value={name}
                        onChange={setName}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.regional')}>
                    <FormField
                        id="brand-edit-locale"
                        name="locale"
                        label={t('workspace.brandLocations.brand.locale')}
                        value={locale}
                        onChange={setLocale}
                    />
                    <FormField
                        id="brand-edit-timezone"
                        name="timezone"
                        label={t('workspace.brandLocations.brand.timezone')}
                        value={timezone}
                        onChange={setTimezone}
                    />
                    <FormField
                        id="brand-edit-currency"
                        name="currency"
                        label={t('workspace.brandLocations.brand.currency')}
                        value={currency}
                        onChange={setCurrency}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.description')}>
                    <FormField
                        id="brand-edit-description"
                        name="description"
                        label={t('workspace.brandLocations.brand.description')}
                        value={description}
                        onChange={setDescription}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.contact')}>
                    <FormField
                        id="brand-edit-contact-email"
                        name="contact_email"
                        label={t('workspace.brandLocations.brand.contactEmail')}
                        value={contactEmail}
                        onChange={setContactEmail}
                    />
                    <FormField
                        id="brand-edit-contact-phone"
                        name="contact_phone"
                        label={t('workspace.brandLocations.brand.contactPhone')}
                        value={contactPhone}
                        onChange={setContactPhone}
                    />
                </FormSection>

                <FormActions
                    error={error}
                    saving={saving}
                    saveLabel={t('workspace.brandLocations.edit.save')}
                    cancelLabel={t('workspace.brandLocations.edit.cancel')}
                    onCancel={cancelEdit}
                />
            </form>
        </div>
    );
}

export default BrandEditForm;
