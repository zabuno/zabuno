import { useState } from 'react';
import { Button, Label } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { LocationEditForm, type LocationProfile } from '../LocationEditForm';
import { LocationOnboardingForm } from '../LocationOnboardingForm';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

type LocationsPageProps = {
    workspaceId: number;
    locations: LocationProfile[];
    selectedLocationId: number | null;
    onSelectLocation: (locationId: number) => void;
    onLocationSaved: (location: LocationProfile) => void;
    onLocationCreated: (location: LocationProfile) => void;
};

export function LocationsPage({
    workspaceId,
    locations,
    selectedLocationId,
    onSelectLocation,
    onLocationSaved,
    onLocationCreated,
}: LocationsPageProps) {
    const [addingLocation, setAddingLocation] = useState(false);

    const badges: WorkspacePageStatusBadge[] =
        locations.length > 0
            ? [{ key: 'locations-count', status: 'success', label: `#${locations.length}` }]
            : [];

    const grouped = Array.from(
        locations
            .reduce<
                Map<string, { city: string; countryCode: string; locations: LocationProfile[] }>
            >((byCityCountry, location) => {
                const groupKey = `${location.city} ${location.country_code}`;
                const group = byCityCountry.get(groupKey) ?? {
                    city: location.city,
                    countryCode: location.country_code,
                    locations: [],
                };
                group.locations.push(location);
                byCityCountry.set(groupKey, group);

                return byCityCountry;
            }, new Map())
            .entries(),
    );

    return (
        <div id="section-locations">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.locations')}
                description={t('workspace.locations.operational.description')}
                badges={badges}
                actions={
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setAddingLocation((open) => !open)}
                    >
                        {t('workspace.locations.add.button')}
                    </Button>
                }
            >
                <p className="text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.brandLocations.locations.count', {
                        count: String(locations.length),
                    })}
                </p>

                {addingLocation && (
                    <LocationOnboardingForm
                        workspaceId={workspaceId}
                        onCreated={(location) => {
                            onLocationCreated(location);
                            setAddingLocation(false);
                        }}
                    />
                )}

                {locations.length > 0 && (
                    <div>
                        <div className="mb-2 block">
                            <Label htmlFor="workspace-locations-current">
                                {t('workspace.catalog.location.label')}
                            </Label>
                        </div>
                        <select
                            id="workspace-locations-current"
                            className="w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            value={selectedLocationId === null ? '' : String(selectedLocationId)}
                            onChange={(event) => onSelectLocation(Number(event.target.value))}
                        >
                            {locations.map((location) => (
                                <option key={location.id} value={String(location.id)}>
                                    {`${location.display_name} (#${location.id})`}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {locations.length === 0 && !addingLocation && (
                    <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                        {t('workspace.locations.empty')}
                    </p>
                )}

                {grouped.map(([groupKey, group]) => (
                    <div key={groupKey}>
                        <p className="mb-2 font-medium text-gray-900 dark:text-white">
                            {group.city}
                        </p>
                        <p className="mb-2 text-sm text-gray-700 dark:text-gray-300">
                            {group.countryCode}
                        </p>
                        <ul className="flex flex-col gap-3">
                            {group.locations.map((location) => (
                                <li
                                    key={location.id}
                                    data-testid="brand-location-row"
                                    className="rounded-lg border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300"
                                >
                                    <LocationEditForm
                                        workspaceId={workspaceId}
                                        location={location}
                                        onSaved={onLocationSaved}
                                    />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                <AiAssistPanel context={t('workspace.shell.nav.locations')} />
            </WorkspacePageFrame>
        </div>
    );
}

export default LocationsPage;
