import { useRef, useState } from 'react';
import { Button } from 'flowbite-react/components/Button';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type InvitationDetails = {
    workspaceName: string;
    invitedEmail: string;
    role: string;
    acceptUrl: string;
};

type InvitationStatus = 'available' | 'guest' | 'invalid' | 'expired' | 'consumed';

type InvitationAcceptFormProps = {
    invitation: InvitationDetails;
    status: InvitationStatus;
    authenticated?: boolean;
    navigate?: (path: string) => void;
    loginUrl?: string;
};

export function InvitationAcceptForm({
    invitation,
    status,
    authenticated = false,
    navigate = (path) => window.location.assign(path),
    loginUrl,
}: InvitationAcceptFormProps) {
    const [error, setError] = useState<string | null>(null);
    const submittingRef = useRef(false);

    async function handleAccept() {
        if (submittingRef.current) {
            return;
        }

        submittingRef.current = true;
        setError(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                invitation.acceptUrl,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                }),
            );

            if (response.ok) {
                navigate('/app#dashboard');

                return;
            }

            setError(t('auth.invitation_accept.error.submit'));
        } catch {
            setError(t('auth.invitation_accept.error.submit'));
        } finally {
            submittingRef.current = false;
        }
    }

    if (status === 'guest') {
        return (
            <div className="flex flex-col gap-4">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                    {t('auth.invitation_accept.heading')}
                </h1>
                <p>{t('auth.invitation_accept.guest_body')}</p>
                <a href={loginUrl} className="font-medium text-cyan-600 hover:underline dark:text-cyan-500">
                    {t('auth.invitation_accept.login_link')}
                </a>
            </div>
        );
    }

    if (status !== 'available') {
        return (
            <div className="flex flex-col gap-4">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                    {t('auth.invitation_accept.heading')}
                </h1>
                <p>{t('auth.invitation_accept.unavailable_body')}</p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                {t('auth.invitation_accept.heading')}
            </h1>

            {error && (
                <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}

            <dl className="flex flex-col gap-2">
                <div>
                    <dt className="text-sm text-gray-500 dark:text-gray-400">
                        {t('auth.invitation_accept.workspace')}
                    </dt>
                    <dd>{invitation.workspaceName}</dd>
                </div>
                <div>
                    <dt className="text-sm text-gray-500 dark:text-gray-400">
                        {t('auth.invitation_accept.email')}
                    </dt>
                    <dd>{invitation.invitedEmail}</dd>
                </div>
                <div>
                    <dt className="text-sm text-gray-500 dark:text-gray-400">
                        {t('auth.invitation_accept.role')}
                    </dt>
                    <dd>{invitation.role}</dd>
                </div>
            </dl>

            <Button type="button" className="w-full" onClick={handleAccept} disabled={!authenticated}>
                {t('auth.invitation_accept.submit')}
            </Button>
        </div>
    );
}

export default InvitationAcceptForm;
