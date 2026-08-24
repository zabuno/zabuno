import { useState } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type VerificationPendingProps = {
    email: string;
};

type ResendStatus = 'idle' | 'sending' | 'sent' | 'error';

export function VerificationPending({ email }: VerificationPendingProps) {
    const [status, setStatus] = useState<ResendStatus>('idle');

    async function handleResend() {
        setStatus('sending');

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                '/email/verification-notification',
                buildAuthRequestInit({ method: 'POST' }),
            );

            setStatus(response.ok ? 'sent' : 'error');
        } catch {
            setStatus('error');
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                {t('auth.verification_pending.heading')}
            </h1>
            <p className="text-sm text-gray-700 dark:text-gray-300">
                {t('auth.verification_pending.body', { email })}
            </p>

            <Button onClick={handleResend} className="w-full">
                {t('auth.verification_pending.resend')}
            </Button>

            <p
                role="status"
                aria-live="polite"
                className="text-sm text-gray-600 dark:text-gray-400"
            >
                {t(`auth.verification_pending.status.${status}`)}
            </p>
        </div>
    );
}

export default VerificationPending;
