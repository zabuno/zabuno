import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'auth.register.heading': 'Create your account',
    'auth.register.name': 'Name',
    'auth.register.email': 'Email',
    'auth.register.password': 'Password',
    'auth.register.password_confirmation': 'Confirm password',
    'auth.register.submit': 'Register',
    'auth.register.error.name': 'Enter your name.',
    'auth.register.error.email': 'Enter a valid email address.',
    'auth.register.error.password': 'Enter a password.',
    'auth.register.error.submit': 'We could not create your account. Please try again.',
    /*
        ÜÇ BELGE, ÜÇ AYRI OLGU (REG-LEGAL-01). Etiket kutunun ERİŞİLEBİLİR
        ADIDIR ve bağlantı içermez: bağlantılar ayrı, 44 piksellik
        hedeflerdir. Hata metni etiketle aynı kelimeleri tekrar etmez ki
        ekran okuyucu ikisini karıştırmasın.

        HİZMET KOŞULLARI KABUL EDİLİR; GİZLİLİK POLİTİKASI YALNIZ OKUNUR.
        İkisi tek kutudayken aydınlatma metni de onay alınmış görünüyordu —
        KVKK Kurulu'nun 18.02.2026 tarihli 2026/347 sayılı ilke kararı tam
        olarak bunu yasaklıyor. Ticari ileti izni üçüncü ve isteğe bağlı.
    */
    'auth.register.terms': 'I have read and accept the Terms of Service.',
    'auth.register.error.terms':
        'Tick this box to create an account. The full text is on this page.',
    'auth.register.privacy': 'I have read and understood the Privacy Policy.',
    'auth.register.privacy.note':
        'This says only that you were informed. It is not consent, and consent to it is not asked for.',
    'auth.register.error.privacy':
        'Confirm that you have read the Privacy Policy to create an account.',
    'auth.register.legal_links': 'Legal documents',
    'auth.register.marketing':
        'I agree to receive electronic commercial messages from Zabuno by e-mail. This is optional.',
    'auth.register.marketing.note':
        'Optional. Leaving this box empty changes nothing else about your account.',

    /*
        BELGE KARTI VE TAM METİN PENCERESİ (REG-LEGAL-01). "…in full"
        ekleri gereksiz gibi görünür ama üç düğmenin erişilebilir adını
        birbirinden ayıran tek şey odur — ekran okuyucu kullanan biri
        "Read" diyen üç düğme arasında hangisinin hangi belge olduğunu
        göremezdi.
    */
    'auth.register.legal.heading': 'Documents you are asked to read',
    'auth.register.legal.name.terms': 'Terms of Service',
    'auth.register.legal.name.privacy': 'Privacy Policy',
    'auth.register.legal.name.marketing': 'Commercial message consent text',
    'auth.register.legal.read_terms': 'Read the Terms of Service in full',
    'auth.register.legal.read_privacy': 'Read the Privacy Policy in full',
    'auth.register.legal.read_marketing': 'Read the commercial message consent text in full',
    /*
        METNİN SONUNDAKİ OLUMLU EYLEMİN ADI — kutu etiketinin kendisi değil.

        Diğer iki belgede eylem ile kutu aynı cümledir ve öyle kalır. Ticari
        ileti kutusunun etiketi ise iki cümle: ikincisi ("İsteğe bağlıdır")
        seçimin bir NİTELİĞİ, eylemin adı değil. Aynı cümle düğmeye konunca
        320 pikselde beş satıra sarıyordu (ölçüldü: 228×122 piksel, 480
        piksellik ekranın dörtte biri). Ad kısaldı ama NEYİN kabul edildiği
        açık kalmalı — "ticari e-posta" düğmeden çıkmaz.
    */
    'auth.register.legal.accept_marketing': 'I agree to receive commercial e-mail',
    'auth.register.legal.version': 'Version {version}, in force from {date}',
    'auth.register.legal.language': 'text language: {language}',
    'auth.register.legal.review_pending':
        'This text has not yet been reviewed by a lawyer. It is published as it stands so you can read it before you decide.',
    'auth.register.legal.action_hint':
        'Your answer is recorded when your account is created. You can change it until then.',
    'auth.register.legal.close': 'Close',
    'auth.register.legal.unavailable':
        'The full text cannot be shown right now, so this box cannot be ticked yet. Please try again in a moment.',

    'auth.login.heading': 'Log in',
    'auth.login.email': 'Email',
    'auth.login.password': 'Password',
    'auth.login.submit': 'Log in',
    'auth.login.forgot_password': 'Forgot your password?',
    'auth.login.error.email': 'Enter a valid email address.',
    'auth.login.error.password': 'Enter your password.',
    'auth.login.error.submit': 'We could not log you in. Please try again.',

    'auth.forgot_password.heading': 'Forgot your password?',
    'auth.forgot_password.email': 'Email',
    'auth.forgot_password.submit': 'Send reset link',
    'auth.forgot_password.status.sent':
        'If an account exists for that email, a reset link has been sent.',
    'auth.forgot_password.error.email': 'Enter a valid email address.',
    'auth.forgot_password.error.submit': 'We could not process your request. Please try again.',

    'auth.reset_password.heading': 'Reset your password',
    'auth.reset_password.password': 'Password',
    'auth.reset_password.password_confirmation': 'Confirm password',
    'auth.reset_password.submit': 'Reset password',
    'auth.reset_password.error.password': 'Enter a new password.',
    'auth.reset_password.error.password_confirmation': 'Confirm your new password.',
    'auth.reset_password.error.submit': 'We could not reset your password. Please try again.',

    'auth.verification_pending.heading': 'Verification pending',
    'auth.verification_pending.body':
        'We sent a verification link to {email}. Click it to activate your account.',
    'auth.verification_pending.resend': 'Resend verification email',
    'auth.verification_pending.status.idle': '',
    'auth.verification_pending.status.sending': 'Sending verification email…',
    'auth.verification_pending.status.sent': 'Verification email sent.',
    'auth.verification_pending.status.error': 'Could not resend verification email.',

    'auth.verified.heading': 'Email verified',
    'auth.verified.body': 'Your email address has been verified.',

    'auth.logout.submit': 'Log out',
    'auth.logout.error.submit': 'We could not log you out. Please try again.',

    'auth.invitation_accept.heading': 'Workspace invitation',
    'auth.invitation_accept.workspace': 'Workspace',
    'auth.invitation_accept.email': 'Invited email',
    'auth.invitation_accept.role': 'Role',
    'auth.invitation_accept.submit': 'Accept invitation',
    'auth.invitation_accept.guest_body': 'Log in to accept this invitation.',
    'auth.invitation_accept.login_link': 'Log in',
    'auth.invitation_accept.unavailable_body': 'This invitation is not available.',
    'auth.invitation_accept.error.submit': 'We could not accept this invitation. Please try again.',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('auth'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const authTranslations: Record<string, string> = en;
