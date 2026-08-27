import { Select } from '../../catalog/forms/micro/Select';
import { useState } from 'react';
import { Button, Label } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { LocationEditForm, type LocationProfile } from '../LocationEditForm';
import { LocationOnboardingForm } from '../LocationOnboardingForm';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

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
                <p className="text-body text-fg-secondary">
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
                        <Select
                            id="workspace-locations-current"
                            value={selectedLocationId === null ? '' : String(selectedLocationId)}
                            onChange={(event) => onSelectLocation(Number(event.target.value))}
                        >
                            {locations.map((location) => (
                                <option key={location.id} value={String(location.id)}>
                                    {`${location.display_name} (#${location.id})`}
                                </option>
                            ))}
                        </Select>
                    </div>
                )}

                {locations.length === 0 && !addingLocation && (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.locations.empty')}
                    </p>
                )}

                {grouped.map(([groupKey, group]) => (
                    <div key={groupKey}>
                        <p className="mb-2 font-medium text-fg">{group.city}</p>
                        <p className="mb-2 text-body text-fg-secondary">{group.countryCode}</p>
                        <ul className="flex flex-col gap-3">
                            {group.locations.map((location) => (
                                <li
                                    key={location.id}
                                    data-testid="brand-location-row"
                                    className="rounded-lg border border-border p-3 text-body text-fg-secondary"
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
            </WorkspacePageFrame>
        </div>
    );
}

export default LocationsPage;
