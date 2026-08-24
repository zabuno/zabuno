import { IconButton } from '../../catalog/navigation/micro/IconButton';
import { t } from '../../../i18n/workspace';

function SearchGlyph() {
    return (
        <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            className="h-5 w-5"
        >
            <circle cx="9" cy="9" r="6" />
            <path d="m18 18-4.35-4.35" strokeLinecap="round" />
        </svg>
    );
}

function BellGlyph() {
    return (
        <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            className="h-5 w-5"
        >
            <path
                d="M10 2.5c-2.2 0-4 1.8-4 4v2.4c0 .5-.2 1-.5 1.4L4 12v1h12v-1l-1.5-1.7a2 2 0 0 1-.5-1.4V6.5c0-2.2-1.8-4-4-4Z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path d="M8.3 15.5a1.7 1.7 0 0 0 3.4 0" strokeLinecap="round" />
        </svg>
    );
}

/**
 * Workspace-shell compound: honest disabled top-bar affordances for global
 * search and notifications, neither of which is connected yet. No fetch,
 * no fabricated counts/results — the title attribute makes the unavailable
 * state discoverable while the accessible name stays the real label.
 */
export function GlobalUnavailableActions() {
    return (
        <div className="flex items-center gap-1">
            <span title={t('workspace.shell.globalSearch.unavailable')}>
                <IconButton
                    icon={<SearchGlyph />}
                    label={t('workspace.shell.globalSearch.unavailable')}
                    disabled
                />
            </span>
            <span title={t('workspace.shell.notifications.unavailable')}>
                <IconButton
                    icon={<BellGlyph />}
                    label={t('workspace.shell.notifications.unavailable')}
                    disabled
                />
            </span>
        </div>
    );
}
