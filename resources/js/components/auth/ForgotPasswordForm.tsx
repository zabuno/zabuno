import { useState, type FormEvent } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<Record<'email' | 'submit', string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function ForgotPasswordForm() {
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});
    const [sent, setSent] = useState(false);

    function validate(): FieldErrors {
        const next: FieldErrors = {};

        if (!EMAIL_PATTERN.test(email)) {
            next.email = t('auth.forgot_password.error.email');
        }

        return next;
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const nextErrors = validate();
        setErrors(nextErrors);
        setSent(false);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                '/forgot-password',
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email }),
                }),
            );

            if (response.ok) {
                setSent(true);

                return;
            }
        } catch {
            setErrors((current) => ({
                ...current,
                submit: t('auth.forgot_password.error.submit'),
            }));

            return;
        }

        setErrors((current) => ({ ...current, submit: t('auth.forgot_password.error.submit') }));
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                {t('auth.forgot_password.heading')}
            </h1>

            {errors.submit && (
                <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                    {errors.submit}
                </p>
            )}

            {sent && (
                <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                    {t('auth.forgot_password.status.sent')}
                </p>
            )}

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="forgot-password-email">{t('auth.forgot_password.email')}</Label>
                </div>
                <TextInput
                    id="forgot-password-email"
                    name="email"
                    type="email"
                    className="w-full"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    aria-invalid={Boolean(errors.email)}
                    aria-describedby={errors.email ? 'forgot-password-email-error' : undefined}
                />
                {errors.email && (
                    <p
                        id="forgot-password-email-error"
                        role="alert"
                        className="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {errors.email}
                    </p>
                )}
            </div>

            <Button type="submit" className="w-full">
                {t('auth.forgot_password.submit')}
            </Button>
        </form>
    );
}

export default ForgotPasswordForm;
