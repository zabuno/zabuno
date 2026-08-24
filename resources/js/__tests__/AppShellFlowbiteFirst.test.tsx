import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AppShell } from '../components/AppShell';

/**
 * Encodes docs/03 ADR-L06 (Flowbite React is the primary component
 * library; Next.js is never used for any purpose).
 */
describe('AppShell — Flowbite-first React shell', () => {
  it('renders the primary navigation using a Flowbite component', () => {
    render(<AppShell coreModuleCount={16} />);

    expect(screen.getByTestId('flowbite-navbar')).toBeInTheDocument();
  });

  it('never imports anything from the next package', async () => {
    const source = await import('../components/AppShell?raw');

    expect(String(source.default)).not.toMatch(/from ['"]next/);
  });
});

/**
 * Encodes docs/03 ADR-L04/L05 (finite, registry-driven Core Kernel): the
 * Core Kernel badge numerator AppShell renders must come from a
 * coreModuleCount prop derived by the caller from the CORE-05 module
 * registry, not from a numerator constant hardcoded inside the component.
 */
describe('AppShell — registry-derived core module count', () => {
  it('renders the Core Kernel badge numerator from the coreModuleCount prop', () => {
    render(<AppShell coreModuleCount={7} />);

    expect(screen.getByText('7/16 modules registered')).toBeInTheDocument();
  });

  it('never hardcodes a CORE_MODULE_COUNT numerator constant in source', async () => {
    const source = await import('../components/AppShell?raw');

    expect(String(source.default)).not.toMatch(/CORE_MODULE_COUNT/);
  });
});
