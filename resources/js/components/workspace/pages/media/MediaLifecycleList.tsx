import { t } from '../../../../i18n/workspace';

const LIFECYCLE_KEYS = [
    'workspace.media.lifecycle.quarantine',
    'workspace.media.lifecycle.validation',
    'workspace.media.lifecycle.securityScan',
    'workspace.media.lifecycle.derivatives',
] as const;

/**
 * Visible, ordered lifecycle stages only — no Ready/Published stage exists
 * because no asset has ever genuinely passed through this pipeline yet.
 */
export function MediaLifecycleList() {
    return (
        <ol className="flex flex-col gap-1">
            {LIFECYCLE_KEYS.map((key) => (
                <li key={key} className="text-body text-fg-secondary">
                    {t(key)}
                </li>
            ))}
        </ol>
    );
}

export default MediaLifecycleList;
