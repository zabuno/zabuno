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
