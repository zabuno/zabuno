import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

import { BrandPage } from './BrandPage';
import type { BrandProfile } from '../BrandEditForm';

/**
 * Feature-local unit coverage for BrandPage that the shared
 * WorkspaceApp.brandLocations.test.tsx suite exercises only through a fully
 * loaded brand: the honest loading state before the brand has arrived, and
 * fluid-first (no fixed-pixel / breakpoint) markup at a 320px viewport.
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

function makeBrand(): BrandProfile {
    return {
        id: 501,
        workspace_id: 61,
        name: 'Menekşe',
        slug: 'menekse',
        locale: 'tr',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: 'Semt kahvecisi, 1998den beri.',
        contact_email: 'iletisim@menekse.example',
        contact_phone: '+90 212 555 01 01',
    };
}

describe('BrandPage — honest loading state before the brand has arrived', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('shows a real loading status and renders no brand edit form or fake brand data when brand is null', () => {
        render(<BrandPage workspaceId={61} brand={null} onSaved={vi.fn()} />);

        const loadingStatus = screen
            .getAllByRole('status')
            .find((element) => /loading your brand/i.test(element.textContent ?? ''));
        expect(loadingStatus).toHaveTextContent('Loading your brand…');
        expect(screen.queryByText('menekse')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('delegates to the real BrandEditForm once a brand is loaded, replacing the loading status', () => {
        render(<BrandPage workspaceId={61} brand={makeBrand()} onSaved={vi.fn()} />);

        // Niyet aynı: marka geldiğinde yükleniyor durumu KALMAZ.
        //
        // Öncesinde bu, adsız bir `role="status"` üzerinden ifade ediliyordu
        // ve o rolü aslında sayfadaki AI kartı sağlıyordu. Kart kalkınca
        // sorgu `null` döndü ve matcher patladı — yani test, ölçmek
        // istediği şeyi hiç ölçmüyormuş. Metnin kendisi aranır.
        expect(screen.queryByText('Loading your brand…')).toBeNull();
        expect(screen.getByText('Menekşe')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    });
});

describe('BrandPage — fluid-first markup', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('carries no fixed-pixel or breakpoint class at a 320x480 viewport', () => {
        setViewport(320, 480);

        const { container } = render(
            <BrandPage workspaceId={61} brand={makeBrand()} onSaved={vi.fn()} />,
        );

        const root = container.querySelector('#section-brand') as HTMLElement;
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
