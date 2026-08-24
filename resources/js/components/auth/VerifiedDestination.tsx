import { t } from '../../i18n/auth';
import { LogoutButton } from './LogoutButton';

export function VerifiedDestination() {
    return (
        <div className="flex flex-col gap-4">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                {t('auth.verified.heading')}
            </h1>
            <p className="text-sm text-gray-700 dark:text-gray-300">{t('auth.verified.body')}</p>
            <LogoutButton />
        </div>
    );
}

export default VerifiedDestination;
