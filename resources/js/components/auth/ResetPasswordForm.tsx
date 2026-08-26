import { useState, type FormEvent } from 'react';
import { Label, TextInput } from 'flowbite-react';
import { Button } from '../catalog/forms/micro/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<Record<'password' | 'password_confirmation' | 'submit', string>>;

type ResetPasswordFormProps = {
    token: string;
    email: string;
    navigate?: (path: string) => void;
};

export function ResetPasswordForm({
    token,
    email,
    navigate = (path) => window.location.assign(path),
}: ResetPasswordFormProps) {
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});

    function validate(): FieldErrors {
        const next: FieldErrors = {};

        if (password === '') {
            next.password = t('auth.reset_password.error.password');
        }

        if (passwordConfirmation === '') {
            next.password_confirmation = t('auth.reset_password.error.password_confirmation');
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
                '/reset-password',
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token,
                        email,
                        password,
                        password_confirmation: passwordConfirmation,
                    }),
                }),
            );

            if (response.ok) {
                navigate('/login');

                return;
            }
        } catch {
            setErrors((current) => ({ ...current, submit: t('auth.reset_password.error.submit') }));

            return;
        }

        setErrors((current) => ({ ...current, submit: t('auth.reset_password.error.submit') }));
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-fg">{t('auth.reset_password.heading')}</h1>

            {errors.submit && (
                <p role="alert" className="text-sm font-medium text-fg-danger">
                    {errors.submit}
                </p>
            )}

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="reset-password-password">
                        {t('auth.reset_password.password')}
                    </Label>
                </div>
                <TextInput
                    id="reset-password-password"
                    name="password"
                    type="password"
                    className="w-full"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    aria-invalid={Boolean(errors.password)}
                    aria-describedby={errors.password ? 'reset-password-password-error' : undefined}
                />
                {errors.password && (
                    <p
                        id="reset-password-password-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.password}
                    </p>
                )}
            </div>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="reset-password-password-confirmation">
                        {t('auth.reset_password.password_confirmation')}
                    </Label>
                </div>
                <TextInput
                    id="reset-password-password-confirmation"
                    name="password_confirmation"
                    type="password"
                    className="w-full"
                    value={passwordConfirmation}
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                    aria-invalid={Boolean(errors.password_confirmation)}
                    aria-describedby={
                        errors.password_confirmation
                            ? 'reset-password-password-confirmation-error'
                            : undefined
                    }
                />
                {errors.password_confirmation && (
                    <p
                        id="reset-password-password-confirmation-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.password_confirmation}
                    </p>
                )}
            </div>

            <Button type="submit" className="w-full">
                {t('auth.reset_password.submit')}
            </Button>
        </form>
    );
}

export default ResetPasswordForm;
