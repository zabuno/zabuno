import { useState, type FormEvent } from 'react';
import { Label } from '../catalog/forms/micro/Label';
import { TextInput } from '../catalog/forms/micro/TextInput';
import { Button } from '../catalog/forms/micro/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
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

        // Başlangıç değeri YOK: `null` ataması hiçbir zaman okunmuyor,
        // çünkü buraya ancak fetch başarıyla döndüyse ulaşılıyor —
        // diğer iki yol (istek kurulamadı / yanıt uygun) `return` ediyor.
        // TypeScript bunu zaten daraltıyor.
        let response: Response;

        try {
            await bootstrapCsrfCookie();

            response = await fetch(
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
            // Buraya yalnız İSTEK KURULAMADIĞINDA düşülür. Sunucunun
            // reddettiği durum aşağıda, gövdesi okunarak ele alınır.
            setErrors((current) => ({
                ...current,
                submit: t('auth.forgot_password.error.submit'),
            }));

            return;
        }

        // Sunucu neyin yanlış olduğunu SÖYLEDİ; gövdesi okunmadan
        // atılırsa kullanıcı neyi düzelteceğini bilemez.
        const failure = await readValidationFailure(
            response,
            t('auth.forgot_password.error.submit'),
        );

        setErrors((current) => ({
            ...current,
            ...failure.fields,
            submit: failure.message ?? t('auth.forgot_password.error.submit'),
        }));

        focusFirstInvalidField(failure.fields, ['email']);
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-fg">{t('auth.forgot_password.heading')}</h1>

            {errors.submit && (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {errors.submit}
                </p>
            )}

            {sent && (
                <p role="status" className="text-body font-medium text-fg-success">
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
                        className="mt-1 text-body text-fg-danger"
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
