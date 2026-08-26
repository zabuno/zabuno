import { useState, type FormEvent } from 'react';
import { Label } from '../catalog/forms/micro/Label';
import { TextInput } from '../catalog/forms/micro/TextInput';
import { Button } from '../catalog/forms/micro/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<Record<'email' | 'password' | 'submit', string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

type LoginFormProps = {
    navigate?: (path: string) => void;
};

export function LoginForm({
    navigate = (path) => window.location.assign(path),
}: LoginFormProps = {}) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});

    function validate(): FieldErrors {
        const next: FieldErrors = {};

        if (!EMAIL_PATTERN.test(email)) {
            next.email = t('auth.login.error.email');
        }

        if (password === '') {
            next.password = t('auth.login.error.password');
        }

        return next;
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const nextErrors = validate();
        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                '/login',
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password }),
                }),
            );

            if (response.ok) {
                let destination = '/app';

                try {
                    const body: unknown = await response.json();
                    const redirect = (body as { redirect?: unknown } | null)?.redirect;

                    if (typeof redirect === 'string' && redirect !== '') {
                        destination = redirect;
                    }
                } catch {
                    // No JSON body; fall back to the default destination.
                }

                navigate(destination);

                return;
            }
        } catch {
            setErrors((current) => ({ ...current, submit: t('auth.login.error.submit') }));

            return;
        }

        setErrors((current) => ({ ...current, submit: t('auth.login.error.submit') }));
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-fg">{t('auth.login.heading')}</h1>

            {errors.submit && (
                <p role="alert" className="text-sm font-medium text-fg-danger">
                    {errors.submit}
                </p>
            )}

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="login-email">{t('auth.login.email')}</Label>
                </div>
                <TextInput
                    id="login-email"
                    name="email"
                    type="email"
                    className="w-full"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    aria-invalid={Boolean(errors.email)}
                    aria-describedby={errors.email ? 'login-email-error' : undefined}
                />
                {errors.email && (
                    <p id="login-email-error" role="alert" className="mt-1 text-sm text-fg-danger">
                        {errors.email}
                    </p>
                )}
            </div>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="login-password">{t('auth.login.password')}</Label>
                </div>
                <TextInput
                    id="login-password"
                    name="password"
                    type="password"
                    className="w-full"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    aria-invalid={Boolean(errors.password)}
                    aria-describedby={errors.password ? 'login-password-error' : undefined}
                />
                {errors.password && (
                    <p
                        id="login-password-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.password}
                    </p>
                )}
            </div>

            <Button type="submit" className="w-full">
                {t('auth.login.submit')}
            </Button>

            <a href="/forgot-password" className="text-sm text-fg-secondary hover:underline ">
                {t('auth.login.forgot_password')}
            </a>
        </form>
    );
}

export default LoginForm;
