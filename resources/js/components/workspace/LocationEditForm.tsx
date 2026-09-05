import { useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { classifyResponse, networkFailure } from '../../lib/requestFailure';
import { messageForFailure } from './forms/failureMessage';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { FormSection } from './forms/FormSection';
import { FormActions } from './forms/FormActions';
import { ReadOnlySummary, type ReadOnlySummaryItem } from './forms/ReadOnlySummary';
import { RegionalFields } from './location/RegionalFields';
import { OpeningHoursFields } from './location/OpeningHoursFields';
import {
    draftFromEntries,
    entriesFromDraft,
    type OpeningHoursDraft,
    type OpeningHoursEntry,
} from './location/openingHours';

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
    /**
     * Şubenin haftalık çalışma saatleri (`docs/109` §6.4).
     *
     * OPSİYONEL tutulur çünkü bu kaydı üreten yollar tek değil: liste ve
     * profil uçları alanı taşır, elle kurulan hikâye/test kayıtları
     * taşımayabilir. Yokluğu ile boş dizi AYNI anlama gelir — "saat
     * girilmemiş" — ve kart o satırı hiç çizmez.
     */
    opening_hours?: OpeningHoursEntry[];
};

type LocationEditFormProps = {
    workspaceId: number;
    location: LocationProfile;
    onSaved: (location: LocationProfile) => void;
};

/** Alanların ekrandaki sırası; "ilk hatalı alan" bununla belirlenir. */
const FIELD_ORDER = [
    'display_name',
    'country_code',
    'timezone',
    'city',
    'address_line1',
    'address_line2',
    'postal_code',
    'opening_hours',
] as const;

export function LocationEditForm({ workspaceId, location, onSaved }: LocationEditFormProps) {
    const [editing, setEditing] = useState(false);
    const [displayName, setDisplayName] = useState(location.display_name);
    const [countryCode, setCountryCode] = useState(location.country_code);
    const [timezone, setTimezone] = useState(location.timezone);
    const [city, setCity] = useState(location.city);
    const [addressLine1, setAddressLine1] = useState(location.address_line1);
    const [addressLine2, setAddressLine2] = useState(location.address_line2 ?? '');
    const [postalCode, setPostalCode] = useState(location.postal_code ?? '');
    /**
     * Çalışma saatleri taslağı. `null` "bu şubenin saati girilmemiş"
     * demektir ve yedi boş satır DEĞİLDİR: saati olmayan şube normaldir,
     * form onu bir eksiklik gibi göstermez.
     */
    const [hoursDraft, setHoursDraft] = useState<OpeningHoursDraft[] | null>(() =>
        draftFromEntries(location.opening_hours),
    );
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
        setTimezone(location.timezone);
        setCity(location.city);
        setAddressLine1(location.address_line1);
        setAddressLine2(location.address_line2 ?? '');
        setPostalCode(location.postal_code ?? '');
        setHoursDraft(draftFromEntries(location.opening_hours));
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

        /*
            ÇALIŞMA SAATİ alanı yalnız SÖYLENECEK BİR ŞEY VARSA gönderilir.

            Sunucunun sözleşmesi net: alan yoksa dokunma, boş dizi varsa sil
            (`UpdateLocation`). Saati hiç olmayan ve sahibinin de hiç
            dokunmadığı bir şubede her kaydette boş dizi yollamak, hiçbir şey
            söylemek istemediğimiz hâlde her seferinde "sil" demek olurdu.
            Zararı bugün görünmez çünkü silinecek bir şey yok — ama yarın
            saatleri başka bir yüzeyden girilen bir şube, adresini düzelten
            bu formla sessizce saatlerini kaybederdi.
        */
        const hadSavedHours = (location.opening_hours?.length ?? 0) > 0;
        const openingHours =
            hoursDraft !== null
                ? entriesFromDraft(hoursDraft)
                : hadSavedHours
                  ? // Sahip kayıtlı saatleri KAPATTI: bu bir silme isteğidir,
                    // "artık söylemiyorum" da bir karardır.
                    []
                  : undefined;

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
                        timezone,
                        city,
                        address_line1: addressLine1,
                        address_line2: addressLine2,
                        postal_code: postalCode,
                        /*
                            `undefined` alanı gövdeden TAMAMEN düşürür
                            (`JSON.stringify` onu yazmaz) — sunucu da
                            "dokunma" der. Boş dizi ise açık bir silme
                            isteğidir; ikisi bilerek ayrı.
                        */
                        opening_hours: openingHours,
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

            /*
                Arıza SINIFLANDIRILIR — `docs/67`.

                Önceden her başarısızlık aynı cümleye düşüyordu. Yetki yoksa
                tekrar denemek hiçbir zaman işe yaramaz; çakışma varsa veriyi
                değiştirmek gerekir; bağlantı koptuysa girilenler duruyordur.
                Üçüne "tekrar deneyin" demek, ikisine yanlış tavsiye vermektir.
            */
            const failure = classifyResponse(response);

            if (failure.kind === 'validation') {
                const validation = await readValidationFailure(
                    response,
                    t('workspace.brandLocations.locations.edit.error.submit'),
                );

                setFieldErrors(validation.fields);
                setError(
                    validation.message ?? t('workspace.brandLocations.locations.edit.error.submit'),
                );
                focusFirstInvalidField(validation.fields, FIELD_ORDER);
            } else {
                // Alan hatası yok: eski alan hataları TEMİZLENİR, yoksa
                // düzeltilmiş bir alan hâlâ kırmızı görünür.
                setFieldErrors({});
                setError(messageForFailure(failure));
            }
        } catch {
            // Buraya yalnız istek hiç KURULAMADIĞINDA düşülür.
            setFieldErrors({});
            setError(messageForFailure(networkFailure()));
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
            key: 'timezone',
            label: t('workspace.location.timezone'),
            value: location.timezone,
        });

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
                <RegionalFields
                    idPrefix={`location-edit-${String(location.id)}`}
                    countryCode={countryCode}
                    timezone={timezone}
                    onCountryChange={setCountryCode}
                    onTimezoneChange={setTimezone}
                    countryError={fieldErrors.country_code}
                    timezoneError={fieldErrors.timezone}
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
                    label={t('workspace.location.optional', {
                        label: t('workspace.location.addressLine2'),
                    })}
                    value={addressLine2}
                    onChange={setAddressLine2}
                />
                <FormField
                    id={`location-edit-postal-code-${location.id}`}
                    name="postal_code"
                    errorText={fieldErrors.postal_code}
                    label={t('workspace.location.optional', {
                        label: t('workspace.location.postalCode'),
                    })}
                    value={postalCode}
                    onChange={setPostalCode}
                />
            </FormSection>

            <FormSection title={t('workspace.brandLocations.locations.section.hours')}>
                <OpeningHoursFields
                    idPrefix={`location-edit-${String(location.id)}`}
                    draft={hoursDraft}
                    onChange={setHoursDraft}
                    errorText={fieldErrors.opening_hours}
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
