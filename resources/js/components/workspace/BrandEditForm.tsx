import { useEffect, useState, type FormEvent } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/workspace';
import { FormField } from './forms/FormField';
import { FormSection } from './forms/FormSection';
import { FormActions } from './forms/FormActions';
import { ReadOnlySummary, type ReadOnlySummaryItem } from './forms/ReadOnlySummary';
import { ErrorSummary, type ErrorSummaryEntry } from './forms/ErrorSummary';
import { SelectField } from '../catalog/forms/compound/SelectField';
import { classifyResponse, networkFailure, type RequestFailure } from '../../lib/requestFailure';

type Market = { code: string; name: string };
type Timezone = { id: string; label: string };
type Currency = { code: string; name: string; symbol: string };
type LocaleOption = { code: string; name: string };

type Reference = {
    markets: Market[];
    currencies: Currency[];
    timezones: Timezone[];
    locales: LocaleOption[];
    defaults: { timezone: string; currency: string } | null;
    suggestedCountry: string | null;
};

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

/** Hata özetinde ve odak sırasında kullanılan alan sırası — ekrandaki sıra. */
const FIELD_ORDER = [
    'name',
    'locale',
    'timezone',
    'currency',
    'description',
    'contact_email',
    'contact_phone',
] as const;

/** Hata özetindeki bağlantı metinleri — alanın ekrandaki adıyla aynı. */
const FIELD_LABELS: Record<(typeof FIELD_ORDER)[number], () => string> = {
    name: () => t('workspace.brand.name'),
    locale: () => t('workspace.brandLocations.brand.locale'),
    timezone: () => t('workspace.brandLocations.brand.timezone'),
    currency: () => t('workspace.brandLocations.brand.currency'),
    description: () => t('workspace.brandLocations.brand.description'),
    contact_email: () => t('workspace.brandLocations.brand.contactEmail'),
    contact_phone: () => t('workspace.brandLocations.brand.contactPhone'),
};

/**
 * Mevcut değeri seçenek listesine EKLER — yoksa.
 *
 * Bir `<select>`, değeri seçeneklerinde bulamazsa sessizce İLK seçeneğe
 * atlar. Kullanıcı hiçbir şey yapmadan formu kaydettiğinde markasının dili
 * ya da para birimi değişmiş olur ve bunun ekranda hiçbir belirtisi olmaz.
 *
 * Bu, uzak bir ihtimal değil: sunucunun listesi ICU sürümüne göre değişir ve
 * eski bir kayıt artık sunulmayan bir değer taşıyabilir.
 */
function withCurrentValue<T>(
    options: T[],
    current: string,
    codeOf: (option: T) => string,
    make: (value: string) => T,
): T[] {
    if (current === '' || options.some((option) => codeOf(option) === current)) {
        return options;
    }

    return [make(current), ...options];
}

function messageForFailure(failure: RequestFailure): string {
    switch (failure.kind) {
        case 'permission':
            return t('workspace.form.error.permission');
        case 'conflict':
            return t('workspace.form.error.conflict');
        case 'notFound':
            return t('workspace.form.error.notFound');
        case 'network':
            return t('workspace.form.error.network');
        case 'server':
        default:
            // Kimlik varsa gösterilir; YOKSA uydurulmaz. Destek ekibinin
            // arayamayacağı bir kod, hiç kod olmamasından kötüdür.
            return failure.correlationId !== null
                ? t('workspace.form.error.serverWithId', { id: failure.correlationId })
                : t('workspace.form.error.server');
    }
}

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
    /**
     * Sunucunun 422 gövdesindeki alan hataları. Daha önce okunmadan
     * atılıyordu: her doğrulama hatası tek bir cümleye düşüyor, kullanıcı
     * neyi düzelteceğini bilmediği için aynı veriyi tekrar gönderiyordu.
     */
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    /**
     * Seçilebilir değerler SUNUCUDAN gelir.
     *
     * Önceden dil, saat dilimi ve para birimi serbest metin kutularıydı.
     * Kullanıcıdan `Europe/Istanbul`, `TRY` ve `tr` yazması bekleniyordu —
     * bunlar kullanıcı dili değil, geliştirici kodudur. Sunucu haklı olarak
     * `ISTANBUL` yazan birini reddediyordu ve kullanıcı ne yazması
     * gerektiğini hiçbir yerden öğrenemiyordu.
     *
     * Liste burada sabit yazılmaz: sunucunun KABUL ETTİĞİ değerlerle ekranda
     * SUNULAN değerler tek kaynaktan gelmezse, bir gün ayrışırlar.
     */
    const [reference, setReference] = useState<Reference | null>(null);
    const [country, setCountry] = useState<string>('');

    /**
     * Düzenlemeye girildiğinde seçenekleri getir.
     *
     * Ülke markada saklanmıyor; ama saat diliminden TÜRETİLEBİLİR ve uç nokta
     * bunu zaten yapıyor. Kullanıcıya "hangi ülkedesin" diye yeniden sormak,
     * sistemin çıkarabileceği bir şeyi sormak olurdu.
     */
    useEffect(() => {
        if (!editing) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const params = new URLSearchParams();

                if (country !== '') {
                    params.set('country', country);
                } else if (brand.timezone !== '') {
                    params.set('timezone', brand.timezone);
                }

                const response = await fetch(`/api/reference/markets?${params.toString()}`, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled || !response.ok) {
                    return;
                }

                const data = (await response.json()) as Reference;

                setReference(data);

                if (country === '' && data.suggestedCountry !== null) {
                    setCountry(data.suggestedCountry);
                }
            } catch {
                // Seçenekler gelmezse form yine ÇALIŞIR: mevcut değerler
                // korunur ve kaydedilebilir. Referans listesi bir kolaylıktır,
                // bir ön koşul değil.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [editing, country, brand.timezone]);

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
        setFieldErrors({});
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

            /*
                Arıza SINIFLANDIRILIR.

                Önceden her başarısızlık aynı cümleye düşüyordu ve o cümle
                "tekrar deneyin" diyordu. Bu tavsiye yalnız sunucu hatasında
                doğrudur: yetki yoksa tekrar denemek hiçbir zaman işe yaramaz,
                çakışma varsa veriyi değiştirmek gerekir. Yanlış tavsiye,
                kullanıcıyı aynı yolu tekrar denemeye ve sonunda vazgeçmeye
                götürür.
            */
            const classified = classifyResponse(response);

            if (classified.kind === 'validation') {
                const validation = await readValidationFailure(
                    response,
                    t('workspace.brandLocations.edit.error.submit'),
                );

                setFieldErrors(validation.fields);
                setError(validation.message ?? '');
                focusFirstInvalidField(validation.fields, FIELD_ORDER);
            } else {
                setError(messageForFailure(classified));
            }
        } catch {
            // Buraya yalnız istek hiç kurulamadığında düşülür.
            const classified = networkFailure();

            setError(messageForFailure(classified));
        }

        setSaving(false);
    }

    if (!editing) {
        const summaryItems: ReadOnlySummaryItem[] = [
            { key: 'slug', label: t('workspace.brandLocations.brand.slug'), value: brand.slug },
            {
                key: 'locale',
                label: t('workspace.brandLocations.brand.locale'),
                value: brand.locale,
            },
            {
                key: 'timezone',
                label: t('workspace.brandLocations.brand.timezone'),
                value: brand.timezone,
            },
            {
                key: 'currency',
                label: t('workspace.brandLocations.brand.currency'),
                value: brand.currency,
            },
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

    /*
        Özet, alan hatalarından TÜRETİLİR — ayrı bir liste tutulmaz.

        İki liste olsaydı biri diğerini unutabilirdi: özet bir hatayı
        gösterirken alan altında hiçbir şey olmayabilir, ya da tersi. Aynı
        kaynaktan türetilmiş olması bu ihtimali ortadan kaldırır.
    */
    const summaryEntries: ErrorSummaryEntry[] = FIELD_ORDER.filter(
        (field) => fieldErrors[field] !== undefined,
    ).map((field) => ({
        fieldId: `brand-edit-${field.replace('_', '-')}`,
        label: FIELD_LABELS[field](),
        message: fieldErrors[field],
    }));

    return (
        <div className="mb-6 flex flex-col gap-4">
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-5">
                <ErrorSummary
                    title={t('workspace.form.error.summary.title')}
                    entries={summaryEntries}
                />
                <FormSection title={t('workspace.brandLocations.brand.section.identity')}>
                    {/*
                        "Slug" bir kullanıcı sözcüğü değildir. Kullanıcının
                        gördüğü şey `olga-restaurant-6x4f08` ve etiketi
                        "Slug"tı — ne olduğu, neyi etkilediği ve neden
                        değiştirilemediği hiçbir yerde yazmıyordu. Alan
                        gerçekten okunur-yazılamazdır (herkese açık adreste
                        geçer), ama bunun SÖYLENMESİ gerekir.
                    */}
                    <FormField
                        id="brand-edit-slug"
                        name="slug"
                        errorText={fieldErrors.slug}
                        label={t('workspace.brand.webAddress.label')}
                        helpText={t('workspace.brand.webAddress.help')}
                        value={brand.slug}
                        disabled
                        readOnly
                    />
                    <FormField
                        id="brand-edit-name"
                        name="name"
                        errorText={fieldErrors.name}
                        label={t('workspace.brand.name')}
                        value={name}
                        onChange={setName}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.regional')}>
                    {/*
                        Pazar seçimi, saat dilimi listesini DARALTIR.

                        ABD'de 29 saat dilimi var; hepsini tek listede sunmak
                        seçimi kolaylaştırmaz, zorlaştırır. Kullanıcının bildiği
                        şey hangi ülkede iş yaptığıdır; gerisi ondan türetilir.
                    */}
                    <SelectField
                        id="brand-edit-country"
                        name="country"
                        label={t('workspace.brand.market.label')}
                        helpText={t('workspace.brand.market.help')}
                        value={country}
                        onChange={(event) => {
                            setCountry(event.target.value);
                        }}
                    >
                        <option value="">{t('workspace.brand.market.placeholder')}</option>
                        {(reference?.markets ?? []).map((market) => (
                            <option key={market.code} value={market.code}>
                                {market.name}
                            </option>
                        ))}
                    </SelectField>

                    <SelectField
                        id="brand-edit-locale"
                        name="locale"
                        errorText={fieldErrors.locale}
                        label={t('workspace.brandLocations.brand.locale')}
                        helpText={t('workspace.brand.locale.help')}
                        value={locale}
                        onChange={(event) => setLocale(event.target.value)}
                    >
                        {/*
                            Mevcut değer listede yoksa yine de gösterilir:
                            aksi hâlde seçici sessizce başka bir dile atlar ve
                            kullanıcı kaydettiğinde farkında olmadan menüsünün
                            dilini değiştirmiş olur.
                        */}
                        {withCurrentValue(
                            reference?.locales ?? [],
                            locale,
                            (option) => option.code,
                            (value) => ({ code: value, name: value }),
                        ).map((option) => (
                            <option key={option.code} value={option.code}>
                                {option.name}
                            </option>
                        ))}
                    </SelectField>

                    <SelectField
                        id="brand-edit-timezone"
                        name="timezone"
                        errorText={fieldErrors.timezone}
                        label={t('workspace.brandLocations.brand.timezone')}
                        helpText={t('workspace.brand.timezone.help')}
                        value={timezone}
                        onChange={(event) => setTimezone(event.target.value)}
                    >
                        {withCurrentValue(
                            reference?.timezones ?? [],
                            timezone,
                            (zone) => zone.id,
                            (value) => ({ id: value, label: value }),
                        ).map((zone) => (
                            <option key={zone.id} value={zone.id}>
                                {zone.label}
                            </option>
                        ))}
                    </SelectField>

                    <SelectField
                        id="brand-edit-currency"
                        name="currency"
                        errorText={fieldErrors.currency}
                        label={t('workspace.brandLocations.brand.currency')}
                        helpText={t('workspace.brand.currency.help')}
                        value={currency}
                        onChange={(event) => setCurrency(event.target.value)}
                    >
                        {withCurrentValue(
                            reference?.currencies ?? [],
                            currency,
                            (option) => option.code,
                            (value) => ({ code: value, name: value, symbol: '' }),
                        ).map((option) => (
                            <option key={option.code} value={option.code}>
                                {option.symbol !== ''
                                    ? `${option.name} — ${option.code} — ${option.symbol}`
                                    : option.code}
                            </option>
                        ))}
                    </SelectField>
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.description')}>
                    <FormField
                        id="brand-edit-description"
                        name="description"
                        errorText={fieldErrors.description}
                        label={t('workspace.location.optional', {
                            label: t('workspace.brandLocations.brand.description'),
                        })}
                        value={description}
                        onChange={setDescription}
                    />
                </FormSection>

                <FormSection title={t('workspace.brandLocations.brand.section.contact')}>
                    <FormField
                        id="brand-edit-contact-email"
                        name="contact_email"
                        errorText={fieldErrors.contact_email}
                        label={t('workspace.location.optional', {
                            label: t('workspace.brandLocations.brand.contactEmail'),
                        })}
                        value={contactEmail}
                        onChange={setContactEmail}
                    />
                    <FormField
                        id="brand-edit-contact-phone"
                        name="contact_phone"
                        errorText={fieldErrors.contact_phone}
                        label={t('workspace.location.optional', {
                            label: t('workspace.brandLocations.brand.contactPhone'),
                        })}
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
