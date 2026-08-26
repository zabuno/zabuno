import { useState } from 'react';
import { PlainButton } from '../catalog/forms/micro/PlainButton';
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
            <h1 className="text-xl font-semibold text-fg">
                {t('auth.verification_pending.heading')}
            </h1>
            <p className="text-sm text-fg-secondary">
                {t('auth.verification_pending.body', { email })}
            </p>

            <PlainButton variant="primary" onClick={handleResend} className="w-full">
                {t('auth.verification_pending.resend')}
            </PlainButton>

            <p role="status" aria-live="polite" className="text-sm text-fg-secondary ">
                {t(`auth.verification_pending.status.${status}`)}
            </p>
        </div>
    );
}

export default VerificationPending;
