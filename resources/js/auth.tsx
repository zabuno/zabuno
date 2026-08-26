import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RegisterForm } from './components/auth/RegisterForm';
import { LoginForm } from './components/auth/LoginForm';
import { ForgotPasswordForm } from './components/auth/ForgotPasswordForm';
import { ResetPasswordForm } from './components/auth/ResetPasswordForm';
import { VerificationPending } from './components/auth/VerificationPending';
import { VerifiedDestination } from './components/auth/VerifiedDestination';
import { InvitationAcceptForm } from './components/auth/InvitationAcceptForm';
import { ThemeRoot } from './components/theme/ThemeRoot';

const container = document.getElementById('auth-app');

if (!container) {
    throw new Error('Root mount element #auth-app not found.');
}

const view = container.dataset.authView;
const authEmail = container.dataset.authEmail ?? '';

function renderView(container: HTMLElement) {
    switch (view) {
        case 'register':
            return <RegisterForm />;
        case 'login':
            return <LoginForm />;
        case 'forgot-password':
            return <ForgotPasswordForm />;
        case 'reset-password':
            return (
                <ResetPasswordForm
                    token={container.dataset.resetToken ?? ''}
                    email={container.dataset.resetEmail ?? ''}
                />
            );
        case 'verification-pending':
            return <VerificationPending email={authEmail} />;
        case 'verified':
            return <VerifiedDestination />;
        case 'invitation-accept': {
            const status = (container.dataset.invitationStatus ?? 'invalid') as
                'available' | 'guest' | 'invalid' | 'expired' | 'consumed';
            const authenticated = container.dataset.authenticated === 'true';
            const loginUrl = container.dataset.loginUrl;

            return (
                <InvitationAcceptForm
                    invitation={{
                        workspaceName: container.dataset.workspaceName ?? '',
                        invitedEmail: container.dataset.invitedEmail ?? '',
                        role: container.dataset.role ?? '',
                        acceptUrl: container.dataset.acceptUrl ?? '',
                    }}
                    status={status}
                    authenticated={authenticated}
                    loginUrl={loginUrl}
                />
            );
        }
        default:
            throw new Error(`Unknown auth view: ${String(view)}`);
    }
}

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <div className="min-h-screen w-full min-w-[320px] bg-surface-subtle text-fg">
                <div
                    aria-hidden="true"
                    className="pointer-events-none fixed inset-0 -z-10 bg-gradient-to-b from-surface-hover to-surface-subtle"
                />
                <div className="flex min-h-screen items-center justify-center px-4 py-8">
                    <div className="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sm">
                        {renderView(container)}
                    </div>
                </div>
            </div>
        </ThemeRoot>
    </StrictMode>,
);
