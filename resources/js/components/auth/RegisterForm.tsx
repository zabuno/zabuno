import { useState, type FormEvent } from 'react';
import { Label } from '../catalog/forms/micro/Label';
import { TextInput } from '../catalog/forms/micro/TextInput';
import { Button } from '../catalog/forms/micro/Button';
import { LegalDocumentCard, type RegisterLegalPayload } from './LegalDocumentModal';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { focusFirstInvalidField, readValidationFailure } from '../../lib/validationErrors';
import { t } from '../../i18n/auth';

type FieldErrors = Partial<
    Record<
        'name' | 'email' | 'password' | 'terms_accepted' | 'privacy_acknowledged' | 'submit',
        string
    >
>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/*
    ALANLARIN EKRANDAKİ SIRASI — "ilk hatalı alan" bununla belirlenir.

    Nesne anahtar sırası buna güvenilir bir cevap vermez; ayrıca yasal
    kutular formun EN ALTINDA durur, yani boş bir formda odak metin
    alanlarına gitmeli, kutulara değil. Aynı liste hem istemci hem sunucu
    doğrulamasında kullanılır: iki yerde iki farklı sıra, aynı formun iki
    farklı davranışı olurdu.
*/
const FIELD_ORDER = [
    'name',
    'email',
    'password',
    'password_confirmation',
    'terms_accepted',
    'privacy_acknowledged',
] as const;

type OpenDocument = 'terms' | 'privacy' | 'marketing' | null;

type RegisterFormProps = {
    navigate?: (path: string) => void;
    /*
        Yasal metin SUNUCUDAN, sayfayla birlikte gelir
        (`RegistrationLegalPayload`). Gelmediğinde kart yine çizilir ama
        kutusu işaretlenemez: gösteremediğimiz bir belgeyi okuduğunu
        söyletmeyiz.
    */
    legal?: RegisterLegalPayload;
};

export function RegisterForm({
    navigate = (path) => window.location.assign(path),
    legal,
}: RegisterFormProps = {}) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    /*
        ÜÇ KUTU, ÜÇ AYRI OLGU (REG-LEGAL-01).

        1. Hizmet Koşulları KABUL edilir — sözleşme kurulur.
        2. Gizlilik Politikası yalnız OKUNDUĞU BEYAN EDİLİR. Onay değildir ve
           onay olarak istenmez (KVKK Kurulu 2026/347). Yine de zorunludur:
           bilgilendirilmemiş bir kişi için veri işlemeye başlayamayız.
        3. Ticari ileti izni İSTEĞE BAĞLI, ayrı ve varsayılanı BOŞ — önceden
           işaretli bir kutu onay değildir.

        Sunucu üçünü de ayrıca uygular (`CreateNewUser`, `ConsentRecorder`).
    */
    const [termsAccepted, setTermsAccepted] = useState(false);
    const [privacyAcknowledged, setPrivacyAcknowledged] = useState(false);
    const [marketingConsent, setMarketingConsent] = useState(false);
    const [openDocument, setOpenDocument] = useState<OpenDocument>(null);
    const [errors, setErrors] = useState<FieldErrors>({});

    const documents = legal?.documents ?? {};
    const reviewPending = legal?.reviewPending ?? false;
    const closeDocument = () => setOpenDocument(null);

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

        if (!privacyAcknowledged) {
            next.privacy_acknowledged = t('auth.register.error.privacy');
        }

        return next;
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const nextErrors = validate();
        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            /*
                HATA GÖSTERMEK YETMEZ, ONU BULDURMAK GEREKİR.

                Sunucu reddettiğinde odak zaten ilk hatalı alana taşınıyordu;
                İSTEMCİ reddettiğinde taşınmıyordu. 320×480'de fark ölçülebilir:
                boş bir formu gönderen kişi ekranın altındaki düğmededir, hata
                metinleri katlanmanın üstünde kalır ve sayfa hiç kıpırdamaz —
                kullanıcıya hiçbir şey olmamış gibi görünür. `focusFirstInvalidField`
                `preventScroll` kullanmaz, yani alan görünür alana KAYAR.
            */
            focusFirstInvalidField(nextErrors as Record<string, string>, FIELD_ORDER);

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
                        // Aydınlatma beyanı AYRI bir alan olarak gider:
                        // sunucu neyin kabul, neyin beyan olduğunu ancak
                        // böyle ayırt edebilir.
                        privacy_acknowledged: privacyAcknowledged,
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

        focusFirstInvalidField(failure.fields, FIELD_ORDER);
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

            {/* ÜÇ KART, ÜÇ BELGE — her biri kendi metnini yanında taşır. */}
            <div
                role="group"
                aria-label={t('auth.register.legal_links')}
                className="flex flex-col gap-3"
            >
                <LegalDocumentCard
                    legal={documents.terms}
                    reviewPending={reviewPending}
                    fallbackTitle={t('auth.register.legal.name.terms')}
                    checkboxId="register-terms"
                    checkboxName="terms_accepted"
                    checkboxLabel={t('auth.register.terms')}
                    required
                    checked={termsAccepted}
                    onChange={setTermsAccepted}
                    errorText={errors.terms_accepted}
                    readLabel={t('auth.register.legal.read_terms')}
                    open={openDocument === 'terms'}
                    onOpen={() => setOpenDocument('terms')}
                    onClose={closeDocument}
                />

                <LegalDocumentCard
                    legal={documents.privacy}
                    reviewPending={reviewPending}
                    fallbackTitle={t('auth.register.legal.name.privacy')}
                    checkboxId="register-privacy"
                    checkboxName="privacy_acknowledged"
                    checkboxLabel={t('auth.register.privacy')}
                    checkboxNote={t('auth.register.privacy.note')}
                    required
                    checked={privacyAcknowledged}
                    onChange={setPrivacyAcknowledged}
                    errorText={errors.privacy_acknowledged}
                    readLabel={t('auth.register.legal.read_privacy')}
                    open={openDocument === 'privacy'}
                    onOpen={() => setOpenDocument('privacy')}
                    onClose={closeDocument}
                />

                <LegalDocumentCard
                    legal={documents['marketing-consent']}
                    reviewPending={reviewPending}
                    fallbackTitle={t('auth.register.legal.name.marketing')}
                    checkboxId="register-marketing"
                    checkboxName="marketing_consent"
                    checkboxLabel={t('auth.register.marketing')}
                    checkboxNote={t('auth.register.marketing.note')}
                    checked={marketingConsent}
                    onChange={setMarketingConsent}
                    readLabel={t('auth.register.legal.read_marketing')}
                    /* Kutu metni tam kalır; kısalan yalnız düğmenin adı. */
                    actionLabel={t('auth.register.legal.accept_marketing')}
                    open={openDocument === 'marketing'}
                    onOpen={() => setOpenDocument('marketing')}
                    onClose={closeDocument}
                />
            </div>

            <Button type="submit" className="w-full">
                {t('auth.register.submit')}
            </Button>
        </form>
    );
}

export default RegisterForm;
