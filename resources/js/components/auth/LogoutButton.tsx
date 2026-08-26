import { useState } from 'react';
import { Button } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/auth';

type LogoutButtonProps = {
    navigate?: (path: string) => void;
};

export function LogoutButton({
    navigate = (path) => window.location.assign(path),
}: LogoutButtonProps = {}) {
    const [error, setError] = useState('');

    async function handleLogout() {
        try {
            await bootstrapCsrfCookie();

            const response = await fetch('/logout', buildAuthRequestInit({ method: 'POST' }));

            if (response.ok) {
                navigate('/login');

                return;
            }
        } catch {
            setError(t('auth.logout.error.submit'));

            return;
        }

        setError(t('auth.logout.error.submit'));
    }

    return (
        <div className="flex flex-col gap-4">
            {error && (
                <p role="alert" className="text-sm font-medium text-fg-danger">
                    {error}
                </p>
            )}
            <Button onClick={handleLogout} className="w-full">
                {t('auth.logout.submit')}
            </Button>
        </div>
    );
}

export default LogoutButton;
