import { useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { FormSection } from './forms/FormSection';

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

type LocationOnboardingFormProps = {
    workspaceId: number;
    onCreated: (location: LocationProfile) => void;
};

export function LocationOnboardingForm({ workspaceId, onCreated }: LocationOnboardingFormProps) {
    const [displayName, setDisplayName] = useState('');
    const [countryCode, setCountryCode] = useState('');
    const [city, setCity] = useState('');
    const [addressLine1, setAddressLine1] = useState('');
    const [addressLine2, setAddressLine2] = useState('');
    const [postalCode, setPostalCode] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const trimmedDisplayName = displayName.trim();
        const trimmedCountryCode = countryCode.trim();
        const trimmedCity = city.trim();
        const trimmedAddressLine1 = addressLine1.trim();
        const trimmedAddressLine2 = addressLine2.trim();
        const trimmedPostalCode = postalCode.trim();

        if (
            trimmedDisplayName === '' ||
            trimmedCountryCode === '' ||
            trimmedCity === '' ||
            trimmedAddressLine1 === ''
        ) {
            setError(t('workspace.location.error.required'));

            return;
        }

        setError('');
        setSubmitting(true);

        const payload: Record<string, string> = {
            display_name: trimmedDisplayName,
            country_code: trimmedCountryCode,
            city: trimmedCity,
            address_line1: trimmedAddressLine1,
        };

        if (trimmedAddressLine2 !== '') {
            payload.address_line2 = trimmedAddressLine2;
        }

        if (trimmedPostalCode !== '') {
            payload.postal_code = trimmedPostalCode;
        }

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand/locations`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }),
            );

            if (response.ok) {
                const created = (await response.json()) as LocationProfile;
                onCreated(created);
                setSubmitting(false);

                return;
            }

            setError(t('workspace.location.error.submit'));
        } catch {
            setError(t('workspace.location.error.submit'));
        }

        setSubmitting(false);
    }

    return (
        <div
            style={{
                maxWidth: 'clamp(20rem, 60vw, 40rem)',
                margin: '0 auto',
                padding: 'clamp(1rem, 4vw, 2.5rem) 1rem',
            }}
        >
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-5">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                    {t('workspace.location.heading')}
                </h1>

                {error && (
                    <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                        {error}
                    </p>
                )}

                <FormSection title={t('workspace.brandLocations.locations.section.identity')}>
                    <FormField
                        id="location-display-name"
                        name="display_name"
                        label={t('workspace.location.displayName')}
                        value={displayName}
                        onChange={setDisplayName}
                    />
                    <FormField
                        id="location-country-code"
                        name="country_code"
                        label={t('workspace.location.countryCode')}
                        value={countryCode}
                        onChange={setCountryCode}
                    />
                    <FormField
                        id="location-city"
                        name="city"
                        label={t('workspace.location.city')}
                        value={city}
                        onChange={setCity}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.locations.section.address')}>
                    <FormField
                        id="location-address-line1"
                        name="address_line1"
                        label={t('workspace.location.addressLine1')}
                        value={addressLine1}
                        onChange={setAddressLine1}
                    />
                    <FormField
                        id="location-address-line2"
                        name="address_line2"
                        label={t('workspace.location.addressLine2')}
                        value={addressLine2}
                        onChange={setAddressLine2}
                    />
                    <FormField
                        id="location-postal-code"
                        name="postal_code"
                        label={t('workspace.location.postalCode')}
                        value={postalCode}
                        onChange={setPostalCode}
                    />
                </FormSection>

                <Button type="submit" disabled={submitting} className="w-full">
                    {t('workspace.location.submit')}
                </Button>
            </form>
        </div>
    );
}

export default LocationOnboardingForm;
