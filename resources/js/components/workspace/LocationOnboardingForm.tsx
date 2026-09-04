import { useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { RegionalFields } from './location/RegionalFields';
import { classifyResponse, networkFailure } from '../../lib/requestFailure';
import { messageForFailure } from './forms/failureMessage';
import { FormSection } from './forms/FormSection';

export type LocationProfile = {
    id: number;
    workspace_id: number;
    brand_id: number;
    display_name: string;
    country_code: string;
    timezone: string;
    city: string;
    address_line1: string;
    address_line2: string | null;
    postal_code: string | null;
};

type LocationOnboardingFormProps = {
    workspaceId: number;
    onCreated: (location: LocationProfile) => void;
};

const SUBMIT_FIELD_ORDER = [
    'display_name',
    'country_code',
    'timezone',
    'city',
    'address_line1',
    'address_line2',
    'postal_code',
] as const;

export function LocationOnboardingForm({ workspaceId, onCreated }: LocationOnboardingFormProps) {
    const [displayName, setDisplayName] = useState('');
    const [countryCode, setCountryCode] = useState('');
    const [timezone, setTimezone] = useState('');
    const [city, setCity] = useState('');
    const [addressLine1, setAddressLine1] = useState('');
    const [addressLine2, setAddressLine2] = useState('');
    const [postalCode, setPostalCode] = useState('');
    const [error, setError] = useState('');
    /**
     * Sunucunun 422 gövdesindeki alan hataları. Bu form kritik yolun
     * üstünde: konum kurulamazsa menü açılmaz. Neyin yanlış olduğunu
     * söylemeyen bir hata, yolculuğu orada bitirir.
     */
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const trimmedDisplayName = displayName.trim();
        const trimmedCountryCode = countryCode.trim();
        const trimmedCity = city.trim();
        const trimmedAddressLine1 = addressLine1.trim();
        const trimmedAddressLine2 = addressLine2.trim();
        const trimmedPostalCode = postalCode.trim();

        // Dört zorunlu alan AYNI ANDA doğrulanır ve hata KENDİ alanının
        // yanında görünür (`docs/47` Kural 5).
        //
        // Öncesi formun tepesinde tek bir cümle gösteriyordu — "Display
        // name, country, city, and address line 1 are required." — ve odağı
        // hiç taşımıyordu. Kullanıcı hangisini boş bıraktığını aramak
        // zorundaydı.
        const clientErrors: Record<string, string> = {};

        if (trimmedDisplayName === '') {
            clientErrors.display_name = t('workspace.location.displayName.error.required');
        }

        if (trimmedCountryCode === '') {
            clientErrors.country_code = t('workspace.location.countryCode.error.required');
        }

        if (trimmedCity === '') {
            clientErrors.city = t('workspace.location.city.error.required');
        }

        if (trimmedAddressLine1 === '') {
            clientErrors.address_line1 = t('workspace.location.addressLine1.error.required');
        }

        if (Object.keys(clientErrors).length > 0) {
            setError('');
            setFieldErrors(clientErrors);
            focusFirstInvalidField(clientErrors, [
                'display_name',
                'country_code',
                'city',
                'address_line1',
            ]);

            return;
        }

        setError('');
        setFieldErrors({});
        setSubmitting(true);

        const payload: Record<string, string> = {
            display_name: trimmedDisplayName,
            country_code: trimmedCountryCode,
            /*
                Boş gönderilirse sunucu markanınkini devralır — şube saat
                dilimsiz kalmaz (docs/62).
            */
            ...(timezone !== '' ? { timezone } : {}),
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

            // Arıza SINIFLANDIRILIR (`docs/67`): "tekrar deneyin" yalnız bir
            // durumda doğru tavsiyedir.
            const failure = classifyResponse(response);

            if (failure.kind === 'validation') {
                const validation = await readValidationFailure(
                    response,
                    t('workspace.location.error.submit'),
                );

                setFieldErrors(validation.fields);
                setError(validation.message ?? t('workspace.location.error.submit'));
                focusFirstInvalidField(validation.fields, SUBMIT_FIELD_ORDER);
            } else {
                setFieldErrors({});
                setError(messageForFailure(failure));
            }
        } catch {
            setFieldErrors({});
            setError(messageForFailure(networkFailure()));
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
                <h1 className="text-section font-semibold text-fg">
                    {t('workspace.location.heading')}
                </h1>

                {error && (
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {error}
                    </p>
                )}

                <FormSection title={t('workspace.brandLocations.locations.section.identity')}>
                    <FormField
                        id="location-display-name"
                        name="display_name"
                        errorText={fieldErrors.display_name}
                        label={t('workspace.location.displayName')}
                        value={displayName}
                        onChange={setDisplayName}
                    />
                    <RegionalFields
                        idPrefix="location"
                        countryCode={countryCode}
                        timezone={timezone}
                        onCountryChange={setCountryCode}
                        onTimezoneChange={setTimezone}
                        countryError={fieldErrors.country_code}
                        timezoneError={fieldErrors.timezone}
                    />
                    <FormField
                        id="location-city"
                        name="city"
                        errorText={fieldErrors.city}
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
                        label={t('workspace.location.optional', {
                            label: t('workspace.location.addressLine2'),
                        })}
                        value={addressLine2}
                        onChange={setAddressLine2}
                    />
                    <FormField
                        id="location-postal-code"
                        name="postal_code"
                        errorText={fieldErrors.postal_code}
                        label={t('workspace.location.optional', {
                            label: t('workspace.location.postalCode'),
                        })}
                        value={postalCode}
                        onChange={setPostalCode}
                    />
                </FormSection>

                <Button
                    type="submit"
                    disabled={submitting}
                    className="w-full max-w-[24ch] self-start"
                >
                    {t('workspace.location.submit')}
                </Button>
            </form>
        </div>
    );
}

export default LocationOnboardingForm;
