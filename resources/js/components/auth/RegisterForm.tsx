import { useState, type FormEvent } from 'react';
import { Label } from '../catalog/forms/micro/Label';
import { TextInput } from '../catalog/forms/micro/TextInput';
import { Button } from '../catalog/forms/micro/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<Record<'name' | 'email' | 'password' | 'submit', string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

type RegisterFormProps = {
    navigate?: (path: string) => void;
};

export function RegisterForm({
    navigate = (path) => window.location.assign(path),
}: RegisterFormProps = {}) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<FieldErrors>({});

    function validate(): FieldErrors {
        const next: FieldErrors = {};

        if (name.trim() === '') {
            next.name = t('auth.register.error.name');
        }

        if (!EMAIL_PATTERN.test(email)) {
            next.email = t('auth.register.error.email');
        }

        if (password === '') {
            next.password = t('auth.register.error.password');
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

        let response: Response | null = null;

        try {
            await bootstrapCsrfCookie();

            response = await fetch(
                '/register',
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name,
                        email,
                        password,
                        password_confirmation: passwordConfirmation,
                    }),
                }),
            );

            if (response.ok) {
                navigate('/email/verify');

                return;
            }
        } catch {
            // Buraya yalnız İSTEK KURULAMADIĞINDA düşülür. Sunucunun
            // reddettiği durum aşağıda, gövdesi okunarak ele alınır.
            setErrors((current) => ({ ...current, submit: t('auth.register.error.submit') }));

            return;
        }

        // Sunucu neyin yanlış olduğunu SÖYLEDİ; gövdesi okunmadan
        // atılırsa kullanıcı neyi düzelteceğini bilemez ve aynı veriyi
        // tekrar gönderir.
        const failure = await readValidationFailure(response, t('auth.register.error.submit'));

        setErrors((current) => ({
            ...current,
            ...failure.fields,
            submit: failure.message ?? t('auth.register.error.submit'),
        }));

        focusFirstInvalidField(failure.fields, [
            'name',
            'email',
            'password',
            'password_confirmation',
        ]);
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-fg">{t('auth.register.heading')}</h1>

            {errors.submit && (
                <p role="alert" className="text-sm font-medium text-fg-danger">
                    {errors.submit}
                </p>
            )}

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="register-name">{t('auth.register.name')}</Label>
                </div>
                <TextInput
                    id="register-name"
                    name="name"
                    className="w-full"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? 'register-name-error' : undefined}
                />
                {errors.name && (
                    <p
                        id="register-name-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.name}
                    </p>
                )}
            </div>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="register-email">{t('auth.register.email')}</Label>
                </div>
                <TextInput
                    id="register-email"
                    name="email"
                    type="email"
                    className="w-full"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    aria-invalid={Boolean(errors.email)}
                    aria-describedby={errors.email ? 'register-email-error' : undefined}
                />
                {errors.email && (
                    <p
                        id="register-email-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.email}
                    </p>
                )}
            </div>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="register-password">{t('auth.register.password')}</Label>
                </div>
                <TextInput
                    id="register-password"
                    name="password"
                    type="password"
                    className="w-full"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    aria-invalid={Boolean(errors.password)}
                    aria-describedby={errors.password ? 'register-password-error' : undefined}
                />
                {errors.password && (
                    <p
                        id="register-password-error"
                        role="alert"
                        className="mt-1 text-sm text-fg-danger"
                    >
                        {errors.password}
                    </p>
                )}
            </div>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor="register-password-confirmation">
                        {t('auth.register.password_confirmation')}
                    </Label>
                </div>
                <TextInput
                    id="register-password-confirmation"
                    name="password_confirmation"
                    type="password"
                    className="w-full"
                    value={passwordConfirmation}
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                />
            </div>

            <Button type="submit" className="w-full">
                {t('auth.register.submit')}
            </Button>
        </form>
    );
}

export default RegisterForm;
