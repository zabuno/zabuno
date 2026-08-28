import { useEffect, useRef, useState, type FormEvent } from 'react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { classifyResponse, networkFailure } from '../../lib/requestFailure';
import { messageForFailure } from './forms/failureMessage';
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

    /**
     * Referans veriyi ÇEKER — hiçbir durum güncellemez.
     *
     * G/Ç ile durum güncellemesi bilerek ayrıldı. Birleşikken effect
     * içinden çağrılması, React'in "senkron setState" uyarısını tetikliyordu
     * ve uyarı haksız değildi: çağrının içeride bir await'ten sonra durum
     * yazdığını dışarıdan görmek mümkün değil. Ayrıldıklarında sınır
     * görünür hâle geliyor.
     */
    async function fetchReference(query: {
        timezone?: string;
        country?: string;
    }): Promise<Reference | null> {
        const params = new URLSearchParams();

        if (query.country) params.set('country', query.country);
        if (query.timezone) params.set('timezone', query.timezone);

        try {
            const response = await fetch(
                `/api/reference/markets?${params.toString()}`,
                buildAuthRequestInit({ method: 'GET' }),
            );

            if (!response.ok) return null;

            return (await response.json()) as Reference;
        } catch {
            // Referans verisi gelmezse form yine çalışır: alanlar boş kalır
            // ve sunucu doğrulaması son söz olur. Ekran boş kalmaz.
            return null;
        }
    }

    /** Gelen veriyi ekrana yazar. */
    function applyReference(data: Reference, chosenCountry?: string): void {
        setReference(data);

        const resolved = chosenCountry ?? data.suggestedCountry ?? '';
        if (resolved) setCountry(resolved);
        if (data.defaults) {
            setTimezone(data.defaults.timezone);
            setCurrency(data.defaults.currency);
        }
    }

    // Tarayıcı kendi saat dilimini biliyor; ülkeyi ondan ÖNERİYORUZ.
    // Öneri seçim değildir — liste hemen yanında duruyor.
    useEffect(() => {
        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        let cancelled = false;

        void fetchReference({ timezone: browserTimezone }).then((data) => {
            // Bileşen sökülmüşse yazma: sökülmüş bir ağaca durum yazmak
            // sessiz bir sızıntıdır.
            if (cancelled || data === null) return;

            applyReference(data);
        });

        return () => {
            cancelled = true;
        };
        // Yalnız ilk açılışta.
    }, []);

    function handleCountryChange(next: string): void {
        setCountry(next);
        setFieldErrors({});

        void fetchReference({ country: next }).then((data) => {
            if (data !== null) applyReference(data, next);
        });
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const trimmedName = name.trim();

        // Bütün alanlar AYNI ANDA doğrulanır (`docs/47` Kural 5).
        //
        // Öncesi sıralıydı: önce ad kontrol ediliyor, hata varsa geri
        // dönülüyordu. Kullanıcı adı düzeltip yeniden gönderiyor ve ANCAK O
        // ZAMAN pazar hatasını görüyordu. İki hatalı alan, iki tur.
        const errors: Record<string, string> = {};

        if (trimmedName === '') {
            errors.name = t('workspace.brand.error.name.required');
        }

        if (country === '' || timezone === '' || currency === '') {
            errors.country = t('workspace.brand.error.market.required');
        }

        if (Object.keys(errors).length > 0) {
            setFieldErrors(errors);

            if (errors.name) {
                nameRef.current?.focus();
            } else {
                focusFirstInvalidField(errors, ['country']);
            }

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
            setError(messageForFailure(networkFailure()));
        }

        setSubmitting(false);
    }

    async function reportFailure(response: Response): Promise<void> {
        /*
            Arıza SINIFLANDIRILIR — `docs/67`.

            Bu form gövdeyi kendi elleriyle ayrıştırıyordu ve yalnız iki hâl
            tanıyordu: alan hatası, ya da genel mesaj. Yetki eksikliği,
            çakışma ve sunucu hatası hepsi aynı cümleye düşüyordu — üçünün
            çıkış yolu farklı olduğu hâlde.
        */
        const failure = classifyResponse(response);

        if (failure.kind !== 'validation') {
            setFieldErrors({});
            setError(messageForFailure(failure));

            return;
        }

        const validation = await readValidationFailure(response, t('workspace.brand.error.submit'));

        setFieldErrors(validation.fields);
        setError(validation.message ?? t('workspace.brand.error.submit'));

        // Odak ilk hatalı alana taşınır; aksi hâlde uzun bir formda hatanın
        // nerede olduğunu aramak kullanıcının işi olur.
        if (validation.fields.name) {
            nameRef.current?.focus();
        } else {
            focusFirstInvalidField(validation.fields, ['name', 'country', 'timezone', 'currency']);
        }
    }

    const selectedTimezone = reference?.timezones.find((zone) => zone.id === timezone);
    const selectedCurrency = reference?.currencies.find((item) => item.code === currency);

    return (
        <div className="mx-auto w-full max-w-form px-4 py-10">
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
