import { useState } from 'react';
import { Button } from '../catalog/forms/micro/Button';
import { LogoutButton } from './LogoutButton';
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
            <h1 className="text-section font-bold text-fg">
                {t('auth.verification_pending.heading')}
            </h1>
            <p className="text-body text-fg-secondary">
                {t('auth.verification_pending.body', { email })}
            </p>

            <Button onClick={handleResend} className="w-full">
                {t('auth.verification_pending.resend')}
            </Button>

            <p role="status" aria-live="polite" className="text-body text-fg-secondary ">
                {t(`auth.verification_pending.status.${status}`)}
            </p>

            {/*
                ÇIKIŞ YOLU — BU EKRANIN TEK KAPISI OLAMAZ.

                Bu ekranda bir tek "yeniden gönder" vardı ve posta gelmediğinde
                kullanıcı KAPANA KISILIYORDU: uygulamaya giremiyor (doğrulanmamış),
                çıkış yapamıyor (düğme yok), yeni hesap açamıyor (zaten girişli,
                ana sayfaya atılıyor). Adres çubuğuna `/logout` yazmak da çare
                değil — çıkış POST'tur ve GET 405 döner; bu doğru bir güvenlik
                kararıdır, eksik olan ona basacak düğmedir.

                Bir kullanıcı 2026-09-11'de tam olarak bu döngüyü bildirdi.

                Kapan, posta sorunundan BAĞIMSIZ olarak yanlıştır: postası
                gecikeni, spam'e düşeni, adresini yanlış yazanı da aynı yere
                sıkıştırır. Her ekranın bir çıkışı olmalı.
            */}
            <hr className="border-border" />
            <LogoutButton />
        </div>
    );
}

export default VerificationPending;
