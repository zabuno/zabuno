import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';

import { LocationsPage } from './LocationsPage';
import type { LocationProfile } from '../LocationEditForm';

/**
 * Feature-local unit coverage for LocationsPage that the shared
 * WorkspaceApp.brandLocations.test.tsx / WorkspaceApp.locationEdit.test.tsx
 * suites do not exercise directly: the honest 0-location empty state, the
 * current-location select control, and fluid-first (no fixed-pixel /
 * breakpoint) markup at a 320px viewport.
 */

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

function makeLocation(overrides: Partial<LocationProfile> = {}): LocationProfile {
    return {
        id: 811,
        workspace_id: 61,
        brand_id: 501,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Moda Cd. 12',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

describe('LocationsPage — honest 0-location empty state', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('shows a real 0 count, no current-location select and no location rows when there are no locations', () => {
        render(
            <LocationsPage
                workspaceId={61}
                locations={[]}
                selectedLocationId={null}
                onSelectLocation={vi.fn()}
                onLocationSaved={vi.fn()}
                onLocationCreated={vi.fn()}
            />,
        );

        expect(screen.getByText('0 locations')).toBeInTheDocument();
        expect(screen.queryByLabelText('Location')).not.toBeInTheDocument();
        expect(screen.queryAllByTestId('brand-location-row')).toHaveLength(0);
        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('still exposes the Add location control with zero locations', () => {
        render(
            <LocationsPage
                workspaceId={61}
                locations={[]}
                selectedLocationId={null}
                onSelectLocation={vi.fn()}
                onLocationSaved={vi.fn()}
                onLocationCreated={vi.fn()}
            />,
        );

        expect(screen.getByRole('button', { name: 'Add location' })).toBeInTheDocument();
    });
});

describe('LocationsPage — current-location select', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('lists every real location and reflects the selected id', () => {
        const locationA = makeLocation();
        const locationB = makeLocation({ id: 812, display_name: 'Beşiktaş Şube' });

        render(
            <LocationsPage
                workspaceId={61}
                locations={[locationA, locationB]}
                selectedLocationId={locationB.id}
                onSelectLocation={vi.fn()}
                onLocationSaved={vi.fn()}
                onLocationCreated={vi.fn()}
            />,
        );

        const select = screen.getByLabelText('Location') as HTMLSelectElement;
        expect(select.value).toBe(String(locationB.id));

        const options = Array.from(select.options).map((option) => option.textContent);
        expect(options).toContain(`${locationA.display_name} (#${locationA.id})`);
        expect(options).toContain(`${locationB.display_name} (#${locationB.id})`);
    });

    it('calls onSelectLocation with the chosen real location id', () => {
        const locationA = makeLocation();
        const locationB = makeLocation({ id: 812, display_name: 'Beşiktaş Şube' });
        const onSelectLocation = vi.fn();

        render(
            <LocationsPage
                workspaceId={61}
                locations={[locationA, locationB]}
                selectedLocationId={locationA.id}
                onSelectLocation={onSelectLocation}
                onLocationSaved={vi.fn()}
                onLocationCreated={vi.fn()}
            />,
        );

        fireEvent.change(screen.getByLabelText('Location'), {
            target: { value: String(locationB.id) },
        });

        expect(onSelectLocation).toHaveBeenCalledWith(locationB.id);
    });
});

describe('LocationsPage — fluid-first markup', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('carries no fixed-pixel or breakpoint class at a 320x480 viewport', () => {
        setViewport(320, 480);

        const { container } = render(
            <LocationsPage
                workspaceId={61}
                locations={[makeLocation()]}
                selectedLocationId={811}
                onSelectLocation={vi.fn()}
                onLocationSaved={vi.fn()}
                onLocationCreated={vi.fn()}
            />,
        );

        const root = container.querySelector('#section-locations') as HTMLElement;
        expect(root).not.toBeNull();

        const classLists = collectClassLists(root);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});
