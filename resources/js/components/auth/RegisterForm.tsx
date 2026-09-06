import { useState, type FormEvent } from 'react';
import { Label } from '../catalog/forms/micro/Label';
import { TextInput } from '../catalog/forms/micro/TextInput';
import { Button } from '../catalog/forms/micro/Button';
import { CheckboxField } from '../catalog/forms/compound/CheckboxField';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<
    Record<'name' | 'email' | 'password' | 'terms_accepted' | 'submit', string>
>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/*
    BELGE BAĞLANTISI BİR DOKUNMA HEDEFİDİR (FF-198, `docs/118` E3).

    Bağlantı cümlenin İÇİNE gömülmez: satır içi bir bağlantı 18 piksel
    yüksekliğindedir ve parmakla vurulamaz (`docs/117` K1). Her belge kendi
    satırında, 44 piksel yüksekliğinde bir hedef olarak durur.
*/
const DOCUMENT_LINK_CLASS =
    'inline-flex min-h-[var(--density-hit-area-min)] items-center text-body text-fg underline';

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
    /*
        İKİ KUTU, İKİ ANLAM (FF-198). Hizmet Koşulları + Gizlilik Politikası
        ZORUNLU: işaretlenmeden form sunucuya hiç gitmez. Ticari ileti izni
        İSTEĞE BAĞLI ve varsayılanı BOŞ — önceden işaretli bir kutu onay
        değildir. Sunucu aynı kuralı ayrıca uygular (`CreateNewUser`).
    */
    const [termsAccepted, setTermsAccepted] = useState(false);
    const [marketingConsent, setMarketingConsent] = useState(false);
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

        if (!termsAccepted) {
            next.terms_accepted = t('auth.register.error.terms');
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

        // Başlangıç değeri YOK: `null` ataması hiçbir zaman okunmuyor,
        // çünkü buraya ancak fetch başarıyla döndüyse ulaşılıyor —
        // diğer iki yol (istek kurulamadı / yanıt uygun) `return` ediyor.
        // TypeScript bunu zaten daraltıyor.
        let response: Response;

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
                        terms_accepted: termsAccepted,
                        marketing_consent: marketingConsent,
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
            'terms_accepted',
        ]);
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
            <h1 className="text-section font-bold text-fg">{t('auth.register.heading')}</h1>

            {errors.submit && (
                <p role="alert" className="text-body font-medium text-fg-danger">
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
                        className="mt-1 text-body text-fg-danger"
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
                        className="mt-1 text-body text-fg-danger"
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
                        className="mt-1 text-body text-fg-danger"
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

            <div className="flex flex-col gap-1">
                <CheckboxField
                    id="register-terms"
                    name="terms_accepted"
                    required
                    checked={termsAccepted}
                    onChange={(event) => setTermsAccepted(event.target.checked)}
                    label={t('auth.register.terms')}
                    errorText={errors.terms_accepted}
                />
                <nav aria-label={t('auth.register.legal_links')} className="flex flex-wrap gap-x-4">
                    <a href="/terms" className={DOCUMENT_LINK_CLASS}>
                        {t('auth.register.terms.read_terms')}
                    </a>
                    <a href="/privacy" className={DOCUMENT_LINK_CLASS}>
                        {t('auth.register.terms.read_privacy')}
                    </a>
                </nav>
            </div>

            <div className="flex flex-col gap-1">
                <CheckboxField
                    id="register-marketing"
                    name="marketing_consent"
                    checked={marketingConsent}
                    onChange={(event) => setMarketingConsent(event.target.checked)}
                    label={t('auth.register.marketing')}
                />
                <a href="/marketing-consent" className={DOCUMENT_LINK_CLASS}>
                    {t('auth.register.marketing.read')}
                </a>
            </div>

            <Button type="submit" className="w-full">
                {t('auth.register.submit')}
            </Button>
        </form>
    );
}

export default RegisterForm;
