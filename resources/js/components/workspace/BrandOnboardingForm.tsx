import { useEffect, useRef, useState, type FormEvent } from 'react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/workspace';
import { Button } from '../catalog/forms/micro/Button';
import { SelectField } from '../catalog/forms/compound/SelectField';
import { TextField } from '../catalog/forms/compound/TextField';

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

type Market = { code: string; name: string };
type Timezone = { id: string; label: string };
type Currency = { code: string; name: string; symbol: string };

type Reference = {
    markets: Market[];
    currencies: Currency[];
    timezones: Timezone[];
    defaults: { timezone: string; currency: string } | null;
    suggestedCountry: string | null;
};

type BrandOnboardingFormProps = {
    workspaceId: number;
    onCreated: (brand: BrandProfile) => void;
};

/**
 * Marka oluşturma.
 *
 * Önceki hâli dört serbest metin alanı soruyordu: ad, `timezone`,
 * `currency`, `locale`. Yani kullanıcıdan `Europe/Istanbul`, `TRY` ve
 * `tr_TR` yazmasını bekliyordu. Bunlar veritabanı sütunlarının adları ve
 * değerleridir; restoran sahibinin dili değil. Sahibi "istantul" yazdı,
 * sunucu haklı olarak reddetti, ekran "Please try again" dedi ve yolculuk
 * orada bitti.
 *
 * Kural: **kullanıcının bildiğini sor, çıkarılabileni çıkar, ertelenebileni
 * ertele.**
 *
 * - Bildiği: markasının adı ve hangi ülkede iş yaptığı.
 * - Çıkarılan: saat dilimi ve para birimi (ikisi de ülkeden güvenilir
 *   biçimde türer) — görünür kalır ve değiştirilebilir, gizlenmez.
 * - Ertelenen: menü içerik dili. Menü eklenene kadar bir anlamı yok ve
 *   ülkeye bakarak dil tahmin etmek yanlış sonuç veriyor.
 */
export function BrandOnboardingForm({ workspaceId, onCreated }: BrandOnboardingFormProps) {
    const [name, setName] = useState('');
    const [country, setCountry] = useState('');
    const [timezone, setTimezone] = useState('');
    const [currency, setCurrency] = useState('');
    const [reference, setReference] = useState<Reference | null>(null);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const nameRef = useRef<HTMLInputElement>(null);

    // Tarayıcı kendi saat dilimini biliyor; ülkeyi ondan ÖNERİYORUZ.
    // Öneri seçim değildir — kullanıcı listeden değiştirebilir.
    useEffect(() => {
        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        void loadReference({ timezone: browserTimezone });
        // Yalnız ilk açılışta.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function loadReference(query: { timezone?: string; country?: string }): Promise<void> {
        const params = new URLSearchParams();

        if (query.country) params.set('country', query.country);
        if (query.timezone) params.set('timezone', query.timezone);

        try {
            const response = await fetch(
                `/api/reference/markets?${params.toString()}`,
                buildAuthRequestInit({ method: 'GET' }),
            );

            if (!response.ok) return;

            const data = (await response.json()) as Reference;
            setReference(data);

            const resolved = query.country ?? data.suggestedCountry ?? '';
            if (resolved) setCountry(resolved);
            if (data.defaults) {
                setTimezone(data.defaults.timezone);
                setCurrency(data.defaults.currency);
            }
        } catch {
            // Referans verisi gelmezse form yine çalışır: alanlar boş kalır
            // ve sunucu doğrulaması son söz olur. Ekran boş kalmaz.
        }
    }

    function handleCountryChange(next: string): void {
        setCountry(next);
        setFieldErrors({});
        void loadReference({ country: next });
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const trimmedName = name.trim();

        if (trimmedName === '') {
            setFieldErrors({ name: t('workspace.brand.error.name.required') });
            nameRef.current?.focus();

            return;
        }

        if (country === '' || timezone === '' || currency === '') {
            setFieldErrors({ country: t('workspace.brand.error.market.required') });

            return;
        }

        setError('');
        setFieldErrors({});
        setSubmitting(true);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: trimmedName, timezone, currency }),
                }),
            );

            if (response.ok) {
                const created = (await response.json()) as BrandProfile;
                onCreated(created);
                setSubmitting(false);

                return;
            }

            await reportFailure(response);
        } catch {
            setError(t('workspace.brand.error.network'));
        }

        setSubmitting(false);
    }

    async function reportFailure(response: Response): Promise<void> {
        try {
            const body = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            const entries = Object.entries(body.errors ?? {});

            if (entries.length > 0) {
                setFieldErrors(
                    Object.fromEntries(
                        entries.map(([field, messages]) => [field, messages[0] ?? '']),
                    ),
                );
                setError(body.message ?? t('workspace.brand.error.submit'));

                // Odak ilk hatalı alana taşınır; aksi hâlde uzun bir formda
                // hatanın nerede olduğunu aramak kullanıcının işi olur.
                if (entries.some(([field]) => field === 'name')) {
                    nameRef.current?.focus();
                }

                return;
            }

            setError(body.message ?? t('workspace.brand.error.submit'));
        } catch {
            setError(t('workspace.brand.error.submit'));
        }
    }

    const selectedTimezone = reference?.timezones.find((zone) => zone.id === timezone);
    const selectedCurrency = reference?.currencies.find((item) => item.code === currency);

    return (
        <div className="mx-auto w-full max-w-xl px-4 py-10">
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-title font-semibold text-fg">
                        {t('workspace.brand.heading')}
                    </h1>
                    {/* Başlık tek başına "şimdi ne oluşturuyorum, sonra
                        değiştirebilir miyim" sorularını yanıtsız bırakıyordu. */}
                    <p className="text-body text-fg-secondary">{t('workspace.brand.intro')}</p>
                </div>

                {error && (
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {error}
                    </p>
                )}

                <TextField
                    ref={nameRef}
                    id="brand-name"
                    name="name"
                    type="text"
                    label={t('workspace.brand.name')}
                    helpText={t('workspace.brand.name.help')}
                    errorText={fieldErrors.name}
                    required
                    autoFocus
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />

                <SelectField
                    id="brand-country"
                    name="country"
                    label={t('workspace.brand.market')}
                    helpText={t('workspace.brand.market.help')}
                    errorText={fieldErrors.country}
                    required
                    value={country}
                    onChange={(event) => handleCountryChange(event.target.value)}
                >
                    <option value="">{t('workspace.brand.market.placeholder')}</option>
                    {(reference?.markets ?? []).map((market) => (
                        <option key={market.code} value={market.code}>
                            {market.name}
                        </option>
                    ))}
                </SelectField>

                {/* Bölgesel ayarlar TÜRETİLİR ama GİZLENMEZ: kullanıcı ne
                    seçildiğini görür ve isterse değiştirir. Kapalı başlaması,
                    marka adının asıl karar olduğunu söyler. */}
                <details className="rounded-md border border-border p-4">
                    <summary className="cursor-pointer text-body text-fg">
                        {t('workspace.brand.regional')}
                        {selectedTimezone && selectedCurrency ? (
                            <span className="ms-2 text-meta text-fg-secondary">
                                {selectedCurrency.name} · {selectedTimezone.label}
                            </span>
                        ) : null}
                    </summary>

                    <div className="mt-4 flex flex-col gap-4">
                        <SelectField
                            id="brand-timezone"
                            name="timezone"
                            label={t('workspace.brand.timezone')}
                            helpText={t('workspace.brand.timezone.help')}
                            errorText={fieldErrors.timezone}
                            value={timezone}
                            onChange={(event) => setTimezone(event.target.value)}
                        >
                            {(reference?.timezones ?? []).map((zone) => (
                                <option key={zone.id} value={zone.id}>
                                    {zone.label}
                                </option>
                            ))}
                        </SelectField>

                        <SelectField
                            id="brand-currency"
                            name="currency"
                            label={t('workspace.brand.currency')}
                            helpText={t('workspace.brand.currency.help')}
                            errorText={fieldErrors.currency}
                            value={currency}
                            onChange={(event) => setCurrency(event.target.value)}
                        >
                            {(reference?.currencies ?? []).map((item) => (
                                <option key={item.code} value={item.code}>
                                    {item.name} — {item.code}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                </details>

                <Button
                    type="submit"
                    disabled={submitting}
                    loading={submitting}
                    loadingText={t('workspace.brand.submitting')}
                >
                    {t('workspace.brand.submit')}
                </Button>
            </form>
        </div>
    );
}
