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

            setError(t('workspace.brand.error.submit'));
        } catch {
            setError(t('workspace.brand.error.submit'));
        }

        setSubmitting(false);
    }

    return (
        <div className="mx-auto max-w-2xl px-4 py-10">
            <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                    {t('workspace.brand.heading')}
                </h1>

                {error && (
                    <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
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
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                    />
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
                        value={timezone}
                        onChange={(event) => setTimezone(event.target.value)}
                    />
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
                        value={currency}
                        onChange={(event) => setCurrency(event.target.value)}
                    />
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
                        value={locale}
                        onChange={(event) => setLocale(event.target.value)}
                    />
                </div>

                <Button type="submit" disabled={submitting} className="w-full">
                    {t('workspace.brand.submit')}
                </Button>
            </form>
        </div>
    );
}

export default BrandOnboardingForm;
