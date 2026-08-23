import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';

/**
 * ZABUNO_THEME_FOUNDATION_RED
 *
 * Freezes the smallest user-visible contract for the global adaptive theme:
 * a 'system' | 'light' | 'dark' preference that (a) defaults to following
 * the OS via matchMedia('(prefers-color-scheme: dark)'), (b) is applied to
 * <html> immediately as both a 'dark' class and a data-theme attribute on
 * every explicit choice, (c) persists under one stable Zabuno-owned
 * localStorage key and rehydrates from it, (d) reacts live to OS scheme
 * changes while on 'system', (e) is reachable and operable via keyboard
 * with accessible System/Light/Dark labels, and (f) treats any unrecognised
 * stored value as if nothing were stored (falls back to 'system') rather
 * than throwing or freezing on a broken value.
 *
 * ThemeRoot.tsx does not exist yet — every assertion below is expected to
 * fail RED (module resolution failure) until S1-WP01B implements it.
 */

const STORAGE_KEY = 'zabuno-theme';

type MediaQueryListenerMap = Map<string, Set<(event: MediaQueryListEvent) => void>>;

function installMatchMediaMock(initialPrefersDark: boolean) {
  let prefersDark = initialPrefersDark;
  const listeners: MediaQueryListenerMap = new Map();

  const mql = {
    get matches() {
      return prefersDark;
    },
    media: '(prefers-color-scheme: dark)',
    addEventListener: (type: string, listener: (event: MediaQueryListEvent) => void) => {
      const set = listeners.get(type) ?? new Set();
      set.add(listener);
      listeners.set(type, set);
    },
    removeEventListener: (type: string, listener: (event: MediaQueryListEvent) => void) => {
      listeners.get(type)?.delete(listener);
    },
    dispatchEvent: () => true,
  };

  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      ...mql,
      media: query,
    })),
  });

  return {
    setPrefersDark(next: boolean) {
      prefersDark = next;
      const event = { matches: next } as MediaQueryListEvent;
      listeners.get('change')?.forEach((listener) => listener(event));
    },
  };
}

function resetDocumentThemeState() {
  document.documentElement.classList.remove('dark');
  document.documentElement.removeAttribute('data-theme');
  window.localStorage.clear();
}

describe('ThemeRoot — adaptive theme foundation (S1-WP01B)', () => {
  beforeEach(() => {
    resetDocumentThemeState();
  });

  afterEach(() => {
    resetDocumentThemeState();
    vi.restoreAllMocks();
  });

  it('defaults to system and follows an OS that currently prefers dark', () => {
    installMatchMediaMock(true);

    render(<ThemeRoot>{null}</ThemeRoot>);

    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('defaults to system and follows an OS that currently prefers light', () => {
    installMatchMediaMock(false);

    render(<ThemeRoot>{null}</ThemeRoot>);

    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('applies the dark class and data-theme immediately when the user explicitly chooses dark', async () => {
    installMatchMediaMock(false);
    const user = userEvent.setup();
    render(<ThemeRoot>{null}</ThemeRoot>);

    await user.click(screen.getByRole('radio', { name: 'Dark' }));

    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('applies no dark class and data-theme=light immediately when the user explicitly chooses light', async () => {
    installMatchMediaMock(true);
    const user = userEvent.setup();
    render(<ThemeRoot>{null}</ThemeRoot>);

    await user.click(screen.getByRole('radio', { name: 'Light' }));

    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('persists an explicit choice under the stable zabuno-theme key and rehydrates it on remount', async () => {
    installMatchMediaMock(false);
    const user = userEvent.setup();
    const { unmount } = render(<ThemeRoot>{null}</ThemeRoot>);

    await user.click(screen.getByRole('radio', { name: 'Dark' }));
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('dark');
    unmount();

    resetDocumentThemeState();
    window.localStorage.setItem(STORAGE_KEY, 'dark');

    render(<ThemeRoot>{null}</ThemeRoot>);

    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(screen.getByRole('radio', { name: 'Dark' })).toBeChecked();
  });

  it('reacts live to an OS scheme change while the preference is system', async () => {
    const media = installMatchMediaMock(false);
    render(<ThemeRoot>{null}</ThemeRoot>);
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');

    media.setPrefersDark(true);

    await waitFor(() => {
      expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('exposes a keyboard-reachable theme control with accessible System, Light and Dark labels', async () => {
    installMatchMediaMock(false);
    const user = userEvent.setup();
    render(<ThemeRoot>{null}</ThemeRoot>);

    const group = screen.getByRole('radiogroup', { name: /theme/i });
    const options = within(group).getAllByRole('radio');
    expect(options.map((option) => option.getAttribute('aria-label') ?? option.textContent)).toEqual(
      expect.arrayContaining([
        expect.stringMatching(/system/i),
        expect.stringMatching(/light/i),
        expect.stringMatching(/dark/i),
      ]),
    );

    await user.tab();
    expect(screen.getByRole('radio', { name: 'System' })).toHaveFocus();
  });

  it('falls back to system when the stored preference value is not one of system/light/dark', () => {
    installMatchMediaMock(true);
    window.localStorage.setItem(STORAGE_KEY, 'not-a-real-theme');

    render(<ThemeRoot>{null}</ThemeRoot>);

    expect(screen.getByRole('radio', { name: 'System' })).toBeChecked();
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('marks the checked theme option with a forced-colors border/outline fallback, not color alone (WCAG 2.2 AA 1.4.1)', async () => {
    installMatchMediaMock(false);
    const user = userEvent.setup();
    render(<ThemeRoot>{null}</ThemeRoot>);

    await user.click(screen.getByRole('radio', { name: 'Light' }));
    const checked = screen.getByRole('radio', { name: 'Light' });

    expect(checked).toHaveAttribute('aria-checked', 'true');
    // In forced-colors mode (Windows High Contrast), background/text-color
    // pairs collapse to the OS palette, so aria-checked:bg-* alone stops
    // rendering any visible distinction. The selected option must also carry
    // a forced-colors-specific border or outline declaration so selection
    // survives on a non-color channel.
    expect(checked.className).toMatch(/forced-colors:(?:outline|border)/);
  });
});
