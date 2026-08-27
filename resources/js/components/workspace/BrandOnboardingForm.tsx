import { useState, type FormEvent } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/workspace';

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

type BrandOnboardingFormProps = {
    workspaceId: number;
    onCreated: (brand: BrandProfile) => void;
};

export function BrandOnboardingForm({ workspaceId, onCreated }: BrandOnboardingFormProps) {
    const [name, setName] = useState('');
    const [timezone, setTimezone] = useState('');
    const [currency, setCurrency] = useState('');
    const [locale, setLocale] = useState('');
    const [error, setError] = useState('');
    /**
     * Sunucunun 422 gövdesindeki alan bazlı hatalar.
     *
     * Bunlar daha önce okunmadan atılıyordu: her doğrulama hatası tek bir
     * "Please try again" cümlesine düşüyordu. Kullanıcı neyi düzelteceğini
     * bilmediği için aynı veriyi tekrar gönderiyor ve aynı cevabı alıyordu —
     * çıkışı olmayan bir döngü. Sunucu zaten "The timezone must be a valid
     * IANA timezone identifier" diyordu; söylediği yere taşınıyor.
     */
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const trimmedName = name.trim();
        const trimmedTimezone = timezone.trim();
        const trimmedCurrency = currency.trim();
        const trimmedLocale = locale.trim();

        if (trimmedName === '' || trimmedTimezone === '' || trimmedCurrency === '') {
            setError(t('workspace.brand.error.required'));

            return;
        }

        setError('');
        setFieldErrors({});
        setSubmitting(true);

        const payload: Record<string, string> = {
            name: trimmedName,
            timezone: trimmedTimezone,
            currency: trimmedCurrency,
        };

        if (trimmedLocale !== '') {
            payload.locale = trimmedLocale.split(/[-_]/)[0].toLowerCase();
        }

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
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
            setError(t('workspace.brand.error.submit'));
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
                // Özet, alan hatalarının yerini tutmaz; ekran okuyucunun
                // gönderim sonrası bir şey duyabilmesi için var.
                setError(body.message ?? t('workspace.brand.error.submit'));

                return;
            }

            setError(body.message ?? t('workspace.brand.error.submit'));
        } catch {
            // Gövde JSON değilse söylenecek özel bir şey yok.
            setError(t('workspace.brand.error.submit'));
        }
    }

    return (
        <div className="mx-auto max-w-2xl px-4 py-10">
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
                <h1 className="text-xl font-semibold text-fg">{t('workspace.brand.heading')}</h1>

                {error && (
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {error}
                    </p>
                )}

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor="brand-name">{t('workspace.brand.name')}</Label>
                    </div>
                    <TextInput
                        id="brand-name"
                        name="name"
                        type="text"
                        className="w-full"
                        color={fieldErrors.name ? 'failure' : undefined}
                        aria-invalid={fieldErrors.name ? true : undefined}
                        aria-describedby={fieldErrors.name ? 'brand-name-error' : undefined}
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                    />
                    {fieldErrors.name && (
                        <p id="brand-name-error" className="mt-1 text-meta text-fg-danger">
                            {fieldErrors.name}
                        </p>
                    )}
                </div>

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor="brand-timezone">{t('workspace.brand.timezone')}</Label>
                    </div>
                    <TextInput
                        id="brand-timezone"
                        name="timezone"
                        type="text"
                        className="w-full"
                        color={fieldErrors.timezone ? 'failure' : undefined}
                        aria-invalid={fieldErrors.timezone ? true : undefined}
                        aria-describedby={fieldErrors.timezone ? 'brand-timezone-error' : undefined}
                        value={timezone}
                        onChange={(event) => setTimezone(event.target.value)}
                    />
                    {fieldErrors.timezone && (
                        <p id="brand-timezone-error" className="mt-1 text-meta text-fg-danger">
                            {fieldErrors.timezone}
                        </p>
                    )}
                </div>

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor="brand-currency">{t('workspace.brand.currency')}</Label>
                    </div>
                    <TextInput
                        id="brand-currency"
                        name="currency"
                        type="text"
                        className="w-full"
                        color={fieldErrors.currency ? 'failure' : undefined}
                        aria-invalid={fieldErrors.currency ? true : undefined}
                        aria-describedby={fieldErrors.currency ? 'brand-currency-error' : undefined}
                        value={currency}
                        onChange={(event) => setCurrency(event.target.value)}
                    />
                    {fieldErrors.currency && (
                        <p id="brand-currency-error" className="mt-1 text-meta text-fg-danger">
                            {fieldErrors.currency}
                        </p>
                    )}
                </div>

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor="brand-locale">{t('workspace.brand.locale')}</Label>
                    </div>
                    <TextInput
                        id="brand-locale"
                        name="locale"
                        type="text"
                        className="w-full"
                        color={fieldErrors.locale ? 'failure' : undefined}
                        aria-invalid={fieldErrors.locale ? true : undefined}
                        aria-describedby={fieldErrors.locale ? 'brand-locale-error' : undefined}
                        value={locale}
                        onChange={(event) => setLocale(event.target.value)}
                    />
                    {fieldErrors.locale && (
                        <p id="brand-locale-error" className="mt-1 text-meta text-fg-danger">
                            {fieldErrors.locale}
                        </p>
                    )}
                </div>

                <Button type="submit" disabled={submitting} className="w-full">
                    {t('workspace.brand.submit')}
                </Button>
            </form>
        </div>
    );
}

export default BrandOnboardingForm;
