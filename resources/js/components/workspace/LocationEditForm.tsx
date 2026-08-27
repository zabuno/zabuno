import { useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { FormSection } from './forms/FormSection';
import { FormActions } from './forms/FormActions';
import { ReadOnlySummary, type ReadOnlySummaryItem } from './forms/ReadOnlySummary';

export type LocationProfile = {
    id: number;
    workspace_id: number;
    brand_id: number;
    display_name: string;
    country_code: string;
    city: string;
    address_line1: string;
    address_line2: string | null;
    postal_code: string | null;
};

type LocationEditFormProps = {
    workspaceId: number;
    location: LocationProfile;
    onSaved: (location: LocationProfile) => void;
};

export function LocationEditForm({ workspaceId, location, onSaved }: LocationEditFormProps) {
    const [editing, setEditing] = useState(false);
    const [displayName, setDisplayName] = useState(location.display_name);
    const [countryCode, setCountryCode] = useState(location.country_code);
    const [city, setCity] = useState(location.city);
    const [addressLine1, setAddressLine1] = useState(location.address_line1);
    const [addressLine2, setAddressLine2] = useState(location.address_line2 ?? '');
    const [postalCode, setPostalCode] = useState(location.postal_code ?? '');
    const [error, setError] = useState('');
    /**
     * Sunucunun 422 gövdesindeki alan hataları. Daha önce okunmadan
     * atılıyordu: her doğrulama hatası tek bir cümleye düşüyor, kullanıcı
     * neyi düzelteceğini bilmediği için aynı veriyi tekrar gönderiyordu.
     */
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    function startEdit() {
        setDisplayName(location.display_name);
        setCountryCode(location.country_code);
        setCity(location.city);
        setAddressLine1(location.address_line1);
        setAddressLine2(location.address_line2 ?? '');
        setPostalCode(location.postal_code ?? '');
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
        setFieldErrors({});
        setSaving(true);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand/locations/${location.id}`,
                buildAuthRequestInit({
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        display_name: displayName,
                        country_code: countryCode,
                        city,
                        address_line1: addressLine1,
                        address_line2: addressLine2,
                        postal_code: postalCode,
                    }),
                }),
            );

            if (response.ok) {
                const updated = (await response.json()) as LocationProfile;
                onSaved(updated);
                setEditing(false);
                setSaving(false);

                return;
            }

            // Sunucu neyin yanlış olduğunu SÖYLEDİ; gövdesi okunmadan
            // atılırsa kullanıcı neyi düzelteceğini bilemez.
            const failure = await readValidationFailure(
                response,
                t('workspace.brandLocations.locations.edit.error.submit'),
            );

            setFieldErrors(failure.fields);
            setError(failure.message ?? t('workspace.brandLocations.locations.edit.error.submit'));
            focusFirstInvalidField(failure.fields, [
                'display_name',
                'country_code',
                'city',
                'address_line1',
                'address_line2',
                'postal_code',
            ]);
        } catch {
            // Buraya yalnız istek kurulamadığında düşülür.
            setError(t('workspace.brandLocations.locations.edit.error.submit'));
        }

        setSaving(false);
    }

    if (!editing) {
        const summaryItems: ReadOnlySummaryItem[] = [
            {
                key: 'address_line1',
                label: t('workspace.location.addressLine1'),
                value: location.address_line1,
            },
        ];

        if (location.address_line2) {
            summaryItems.push({
                key: 'address_line2',
                label: t('workspace.location.addressLine2'),
                value: location.address_line2,
            });
        }

        summaryItems.push({
            key: 'country_code',
            label: t('workspace.brandLocations.locations.view.country'),
            value: location.country_code,
        });

        if (location.postal_code) {
            summaryItems.push({
                key: 'postal_code',
                label: t('workspace.brandLocations.locations.view.postalCode'),
                value: location.postal_code,
            });
        }

        return (
            <ReadOnlySummary
                title={location.display_name}
                items={summaryItems}
                actions={
                    <Button
                        size="sm"
                        onClick={startEdit}
                        aria-label={`Edit ${location.display_name}`}
                    >
                        {t('workspace.brandLocations.edit.button')}
                    </Button>
                }
            />
        );
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-5">
            <FormSection title={t('workspace.brandLocations.locations.section.identity')}>
                <FormField
                    id={`location-edit-display-name-${location.id}`}
                    name="display_name"
                    errorText={fieldErrors.display_name}
                    label={t('workspace.location.displayName')}
                    value={displayName}
                    onChange={setDisplayName}
                />
                <FormField
                    id={`location-edit-country-code-${location.id}`}
                    name="country_code"
                    errorText={fieldErrors.country_code}
                    label={t('workspace.brandLocations.locations.edit.countryCode')}
                    value={countryCode}
                    onChange={setCountryCode}
                />
                <FormField
                    id={`location-edit-city-${location.id}`}
                    name="city"
                    errorText={fieldErrors.city}
                    label={t('workspace.location.city')}
                    value={city}
                    onChange={setCity}
                />
            </FormSection>

            <FormSection title={t('workspace.brandLocations.locations.section.address')}>
                <FormField
                    id={`location-edit-address-line1-${location.id}`}
                    name="address_line1"
                    label={t('workspace.location.addressLine1')}
                    value={addressLine1}
                    onChange={setAddressLine1}
                />
                <FormField
                    id={`location-edit-address-line2-${location.id}`}
                    name="address_line2"
                    label={t('workspace.location.addressLine2')}
                    value={addressLine2}
                    onChange={setAddressLine2}
                />
                <FormField
                    id={`location-edit-postal-code-${location.id}`}
                    name="postal_code"
                    errorText={fieldErrors.postal_code}
                    label={t('workspace.location.postalCode')}
                    value={postalCode}
                    onChange={setPostalCode}
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
    );
}

export default LocationEditForm;
