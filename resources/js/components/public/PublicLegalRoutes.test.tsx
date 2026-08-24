import { afterEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AppShell } from '../AppShell';

/**
 * PUBLIC_LEGAL_RED
 *
 * Freezes the smallest user-visible contract for the synthesized Stage 1
 * legal-readiness frontend:
 *   - AppShell keeps rendering PublicHomePage at '/' (root is unaffected).
 *   - On pathname '/terms', '/privacy', '/kvkk' AppShell renders exactly one
 *     corresponding accessible legal page (identified by an h1 whose text
 *     names the page), reachable via a real footer link to that path.
 *   - Each legal page states an explicit pending-qualified-legal-review /
 *     unpublished status, and never states or implies binding legal terms
 *     have been reviewed, approved, or published.
 *   - No Tailwind responsive breakpoint tokens (sm:/md:/lg:/xl:/2xl:) appear
 *     in rendered class names anywhere in AppShell — layout is fluid from a
 *     320px start, not breakpoint-gated.
 */

function setPathname(pathname: string) {
    window.history.pushState({}, '', pathname);
}

afterEach(() => {
    setPathname('/');
});

describe('AppShell — root is unaffected by legal routing', () => {
    it('still renders the public product home at "/"', () => {
        setPathname('/');
        render(<AppShell coreModuleCount={7} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: /run your restaurant.s menu and workspace/i,
            }),
        ).toBeInTheDocument();
    });
});

describe('PublicSiteFooter — real links to the legal pages', () => {
    it('links to /terms, /privacy, and /kvkk', () => {
        render(<AppShell coreModuleCount={7} />);

        expect(screen.getByRole('link', { name: /terms/i })).toHaveAttribute('href', '/terms');
        expect(screen.getByRole('link', { name: /privacy/i })).toHaveAttribute('href', '/privacy');
        expect(screen.getByRole('link', { name: /kvkk/i })).toHaveAttribute('href', '/kvkk');
    });
});

describe.each([
    { path: '/terms', heading: /terms/i },
    { path: '/privacy', heading: /privacy/i },
    { path: '/kvkk', heading: /kvkk/i },
])('AppShell — legal page at $path', ({ path, heading }) => {
    it('renders exactly one accessible legal page main heading naming the page', () => {
        setPathname(path);
        render(<AppShell coreModuleCount={7} />);

        const headings = screen.getAllByRole('heading', { level: 1 });
        const matching = headings.filter((node) => heading.test(node.textContent ?? ''));

        expect(matching).toHaveLength(1);
        expect(screen.getByRole('main')).toBeInTheDocument();
    });

    it('does not render the public home hero heading', () => {
        setPathname(path);
        render(<AppShell coreModuleCount={7} />);

        expect(
            screen.queryByRole('heading', {
                level: 1,
                name: /run your restaurant.s menu and workspace/i,
            }),
        ).not.toBeInTheDocument();
    });

    it('states an explicit pending-qualified-legal-review / unpublished status', () => {
        setPathname(path);
        render(<AppShell coreModuleCount={7} />);

        expect(
            screen.getByText(
                /pending (qualified )?legal review|not (yet )?(published|reviewed|approved)|awaiting legal review/i,
            ),
        ).toBeInTheDocument();
    });

    it('never states or implies binding legal copy has been reviewed, approved, or published', () => {
        setPathname(path);
        render(<AppShell coreModuleCount={7} />);

        expect(screen.queryByText(/legally binding/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/effective date/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/last updated/i)).not.toBeInTheDocument();
    });
});

describe.each([{ path: '/terms' }, { path: '/privacy' }, { path: '/kvkk' }])(
    'AppShell — public section links from a legal page at $path',
    ({ path }) => {
        it('points every public section link back to the root page anchors, not dead local anchors', () => {
            setPathname(path);
            render(<AppShell coreModuleCount={7} />);

            expect(screen.getByRole('link', { name: /features/i })).toHaveAttribute(
                'href',
                '/#features',
            );
            expect(screen.getByRole('link', { name: /how it works/i })).toHaveAttribute(
                'href',
                '/#how-it-works',
            );
            expect(screen.getByRole('link', { name: /pricing/i })).toHaveAttribute(
                'href',
                '/#pricing',
            );
            expect(screen.getByRole('link', { name: /faq/i })).toHaveAttribute('href', '/#faq');
            expect(screen.getByRole('link', { name: /contact/i })).toHaveAttribute(
                'href',
                '/#contact',
            );
        });
    },
);

describe('AppShell — no responsive breakpoint tokens across legal routing', () => {
    it.each(['/', '/terms', '/privacy', '/kvkk'])(
        'renders no sm:/md:/lg:/xl:/2xl: class tokens at %s',
        (path) => {
            setPathname(path);
            const { container } = render(<AppShell coreModuleCount={7} />);

            const elements = container.querySelectorAll('[class]');
            const breakpointPattern = /(^|\s)(sm|md|lg|xl|2xl):/;

            elements.forEach((element) => {
                expect(element.className).not.toMatch(breakpointPattern);
            });
        },
    );
});
