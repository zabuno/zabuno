import { PublicHomePage } from './public/PublicHomePage';
import { PublicLegalPage } from './public/PublicLegalPage';
import { PublicSiteFooter } from './public/PublicSiteFooter';
import { PublicSiteHeader } from './public/PublicSiteHeader';

type AppShellProps = {
    coreModuleCount: number;
};

const LEGAL_PAGE_TITLES: Record<string, string> = {
    '/terms': 'Terms',
    '/privacy': 'Privacy',
    '/kvkk': 'KVKK',
};

export function AppShell({ coreModuleCount }: AppShellProps) {
    const legalTitle = LEGAL_PAGE_TITLES[window.location.pathname];

    return (
        <div
            className="min-h-screen bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100"
            data-core-module-count={coreModuleCount}
        >
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-blue-700 focus:px-4 focus:py-2 focus:text-white"
            >
                Skip to main content
            </a>

            <PublicSiteHeader />
            {legalTitle ? <PublicLegalPage title={legalTitle} /> : <PublicHomePage />}
            <PublicSiteFooter />
        </div>
    );
}
