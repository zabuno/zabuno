import type { LocationProfile } from '../LocationEditForm';
import { Select } from '../../catalog/forms/micro/Select';
import { t } from '../../../i18n/workspace';

export type WorkspaceContextControlsProps = {
    workspaceName: string;
    locationProfiles: LocationProfile[];
    catalogLocationId: number | null;
    onSwitchWorkspace: () => void;
    onSelectLocation: (locationId: number) => void;
};

/**
 * Workspace-shell compound: top-bar current-workspace / current-location
 * context, orchestrated entirely from props (docs/35 workspace-shell
 * boundary). No fetch or route ownership — WorkspaceApp owns state.
 */
export function WorkspaceContextControls({
    workspaceName,
    locationProfiles,
    catalogLocationId,
    onSwitchWorkspace,
    onSelectLocation,
}: WorkspaceContextControlsProps) {
    return (
        <div className="flex min-w-0 flex-wrap items-center gap-[var(--space-fluid-sm)]">
            <button
                type="button"
                onClick={onSwitchWorkspace}
                className="min-w-0 truncate rounded-md px-2 py-1 text-sm font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-800"
            >
                {workspaceName}
            </button>

            {locationProfiles.length > 0 && catalogLocationId !== null && (
                <Select
                    aria-label={t('workspace.shell.currentLocation.label')}
                    className="min-w-0"
                    value={String(catalogLocationId)}
                    onChange={(event) => onSelectLocation(Number(event.target.value))}
                >
                    {locationProfiles.map((location) => (
                        <option key={location.id} value={location.id}>
                            {location.display_name}
                        </option>
                    ))}
                </Select>
            )}
        </div>
    );
}
