import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { AppShell } from './AppShell';

/**
 * ZABUNO_PUBLIC_HOME_RED
 *
 * Freezes the smallest user-visible contract for the real Zabuno public
 * home: it must be an honest product landing page (not a "foundation
 * status" placeholder), with a skip link, a discoverable in-page
 * navigation, a product-facing heading, named capability/how-it-works/
 * pricing/FAQ/contact sections, and accessible /app, /login, /register
 * CTAs. Pricing and Contact must state their live limitations rather than
 * fabricate results, and no fake social-proof metrics may appear. No
 * Tailwind responsive breakpoint tokens (sm:/md:/lg:/xl:/2xl:) may appear
 * in rendered class names — layout must be fluid/adaptive starting at
 * 320px, not breakpoint-gated.
 */
describe('AppShell — public product home (S1-WP01A)', () => {
  it('renders a skip link to the main content', () => {
    render(<AppShell coreModuleCount={7} />);

    const skipLink = screen.getByRole('link', { name: /skip to (main )?content/i });
    expect(skipLink).toHaveAttribute('href', expect.stringMatching(/^#/));
  });

  it('renders a main landmark', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.getByRole('main')).toBeInTheDocument();
  });

  it('renders a public-site navigation with discoverable in-page links', () => {
    render(<AppShell coreModuleCount={7} />);

    const nav = screen.getByRole('navigation', { name: /primary|main|site/i });

    expect(
      within(nav).getByRole('link', { name: /features/i }),
    ).toHaveAttribute('href', expect.stringMatching(/^#/));
    expect(
      within(nav).getByRole('link', { name: /how it works/i }),
    ).toHaveAttribute('href', expect.stringMatching(/^#/));
    expect(
      within(nav).getByRole('link', { name: /pricing/i }),
    ).toHaveAttribute('href', expect.stringMatching(/^#/));
    expect(
      within(nav).getByRole('link', { name: /faq/i }),
    ).toHaveAttribute('href', expect.stringMatching(/^#/));
    expect(
      within(nav).getByRole('link', { name: /contact/i }),
    ).toHaveAttribute('href', expect.stringMatching(/^#/));
  });

  it('renders a product-facing h1, not a foundation-status heading', () => {
    render(<AppShell coreModuleCount={7} />);

    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading.textContent).not.toMatch(/foundation/i);
  });

  it('does not state foundation status or "no product feature yet" anymore', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.queryByText(/foundation status/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/no product feature yet/i)).not.toBeInTheDocument();
  });

  it('exposes an accessible link to open the workspace app at /app', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(
      screen.getByRole('link', { name: 'Open workspace app' }),
    ).toHaveAttribute('href', '/app');
  });

  it('exposes an accessible link to log in at /login', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.getByRole('link', { name: 'Log in' })).toHaveAttribute(
      'href',
      '/login',
    );
  });

  it('exposes an accessible link to create an account at /register', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(
      screen.getByRole('link', { name: 'Create account' }),
    ).toHaveAttribute('href', '/register');
  });

  it('renders a named product capability/features section', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(
      screen.getByRole('heading', { name: /features/i }),
    ).toBeInTheDocument();
  });

  it('renders a named how-it-works section', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(
      screen.getByRole('heading', { name: /how it works/i }),
    ).toBeInTheDocument();
  });

  it('renders a pricing section that honestly states no published plan prices or checkout exist yet', () => {
    render(<AppShell coreModuleCount={7} />);

    const pricingHeading = screen.getByRole('heading', { name: /pricing/i });
    expect(pricingHeading).toBeInTheDocument();

    expect(
      screen.getByText(/no (published )?(plan )?prices?.*(checkout|yet)|checkout.*not (yet )?available/i),
    ).toBeInTheDocument();
  });

  it('renders an FAQ section', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.getByRole('heading', { name: /faq/i })).toBeInTheDocument();
  });

  it('renders a contact section that honestly states there is no connected contact form yet', () => {
    render(<AppShell coreModuleCount={7} />);

    const contactHeading = screen.getByRole('heading', { name: /contact/i });
    expect(contactHeading).toBeInTheDocument();

    expect(
      screen.getByText(/no connected contact form|contact form.*not (yet )?(connected|available)/i),
    ).toBeInTheDocument();
  });

  it('does not fabricate testimonial, trusted-customer, or percentage social-proof metrics', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.queryByText(/testimonial/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/trusted by/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/\d+%\s*(faster|increase|growth|satisfaction|uptime)/i)).not.toBeInTheDocument();
  });

  it('describes setup and media honestly, without claiming unavailable invitation or menu-item attachment', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.queryByText(/invite your team/i)).not.toBeInTheDocument();
    expect(
      screen.getByText(/workspace and restaurant setup/i),
    ).toBeInTheDocument();

    expect(screen.queryByText(/attach media to menu items?/i)).not.toBeInTheDocument();
    expect(
      screen.getByText(/quarantined (media )?intake (and|&)? ?review/i),
    ).toBeInTheDocument();
  });

  it('renders no Tailwind responsive breakpoint tokens in rendered class names', () => {
    const { container } = render(<AppShell coreModuleCount={7} />);

    const elements = container.querySelectorAll('[class]');
    const breakpointPattern = /(^|\s)(sm|md|lg|xl|2xl):/;

    elements.forEach((element) => {
      expect(element.className).not.toMatch(breakpointPattern);
    });
  });
});
