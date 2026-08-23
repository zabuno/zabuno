import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { DashboardPage } from './DashboardPage';
import type { DashboardMenuTree } from './DashboardPage';
import type { BrandProfile } from '../BrandEditForm';
import type { LocationProfile } from '../LocationEditForm';

/**
 * DASHBOARD_SETUP_RED
 *
 * RED suite for the S1-WP01A Dashboard Setup surface: each Setup row must be
 * an actionable link to its real workspace section hash (#brand, #locations,
 * #menu, #publication), brand/location/menu status must be derived only from
 * the props passed in (no invented data), and Publication/QR must honestly
 * read "not connected" since no backend wiring exists yet.
 */
const FIXED_PIXEL_CLASS_PATTERN = /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: width });
    Object.defineProperty(window, 'innerHeight', { writable: true, configurable: true, value: height });
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

function makeBrand(): BrandProfile {
    return { id: 12, name: 'Zabuno Kahve', slug: 'zabuno-kahve', timezone: 'Europe/Istanbul', currency: 'TRY' } as BrandProfile;
}

function makeLocation(): LocationProfile {
    return {
        id: 34,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        city: 'Istanbul',
        address_line1: 'Moda Cad. 1',
    } as LocationProfile;
}

function makeMenuTree(): DashboardMenuTree {
    return {
        id: 1,
        workspaceId: 1,
        locationId: 34,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 1,
                menuId: 1,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 1,
                        categoryId: 1,
                        productId: 1,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

describe('DashboardPage — Dashboard Setup rows (DASHBOARD_SETUP_RED)', () => {
    beforeEach(() => {
        setViewport(320, 480);
    });

    it('exposes an accessible Dashboard Setup region', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        expect(screen.getByRole('region', { name: /dashboard setup/i })).toBeInTheDocument();
    });

    it('renders every setup row as an actionable link to its real workspace hash', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });

        const brandLink = within(region).getByRole('link', { name: /brand/i });
        expect(brandLink).toHaveAttribute('href', '#brand');

        const locationLink = within(region).getByRole('link', { name: /location/i });
        expect(locationLink).toHaveAttribute('href', '#locations');

        const menuLink = within(region).getByRole('link', { name: /menu/i });
        expect(menuLink).toHaveAttribute('href', '#menu');

        const publicationLink = within(region).getByRole('link', { name: /publication/i });
        expect(publicationLink).toHaveAttribute('href', '#publication');

        const qrLink = within(region).getByRole('link', { name: /qr/i });
        expect(qrLink).toHaveAttribute('href', '#publication');
    });

    it('shows real brand and location names derived only from props, with an honest empty menu status', () => {
        render(<DashboardPage dashboardMenuTree={null} brand={makeBrand()} location={makeLocation()} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).toMatch(/Zabuno Kahve/);
        expect(regionText).toMatch(/Kadıköy Şube/);
        expect(regionText).toMatch(/no menu yet/i);
    });

    it('derives the menu summary from the loaded dashboardMenuTree only', () => {
        render(<DashboardPage dashboardMenuTree={makeMenuTree()} brand={makeBrand()} location={makeLocation()} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).toMatch(/1 categor/i);
        expect(regionText).toMatch(/1 item/i);
    });

    it('shows an honest not connected status for Publication and QR', () => {
        render(<DashboardPage dashboardMenuTree={null} brand={makeBrand()} location={makeLocation()} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const publicationLink = within(region).getByRole('link', { name: /publication/i });
        const qrLink = within(region).getByRole('link', { name: /qr/i });

        expect(publicationLink.closest('div')?.textContent ?? '').toMatch(/not connected/i);
        expect(qrLink.closest('div')?.textContent ?? '').toMatch(/not connected/i);
    });

    it('shows empty brand/location status without inventing any name when props are absent', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        expect(screen.queryByText('Zabuno Kahve')).toBeNull();
        expect(screen.queryByText('Kadıköy Şube')).toBeNull();
    });

    it('renders no fake ID, token or AI-generated claim inside the Setup region', () => {
        render(<DashboardPage dashboardMenuTree={makeMenuTree()} brand={makeBrand()} location={makeLocation()} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).not.toMatch(/#\d+/);
        expect(regionText).not.toMatch(/\btoken\b/i);
        expect(regionText).not.toMatch(/\bai\b/i);
    });

    it('carries no fixed-pixel or breakpoint class on the Setup region at 320x480', () => {
        render(<DashboardPage dashboardMenuTree={makeMenuTree()} brand={makeBrand()} location={makeLocation()} />);

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const classLists = collectClassLists(region);
        const offenders = classLists.filter(
            (classList) => FIXED_PIXEL_CLASS_PATTERN.test(classList) || BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });

    it('makes zero fetch calls on mount', () => {
        const fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);

        render(<DashboardPage dashboardMenuTree={makeMenuTree()} brand={makeBrand()} location={makeLocation()} />);

        expect(fetchSpy).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });
});
