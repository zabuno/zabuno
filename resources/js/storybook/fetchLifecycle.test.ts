import type React from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render } from '@testing-library/react';
import type { Decorator } from '@storybook/react-vite';

/**
 * RED test candidate for the reviewer blocker on deterministic per-story
 * fetch isolation (docs/35 UI Storybook Component Factory Contract).
 *
 * No resources/js/storybook/fetchLifecycle.ts module exists in this
 * snapshot, so the static import below fails RED (module-not-found) until
 * the shared reset-aware fetch lifecycle hook/helper is authored.
 *
 * Required contract:
 * - `stubFetch(handler)` replaces `globalThis.fetch` for the currently
 *   mounted story, capturing whatever `globalThis.fetch` was immediately
 *   before the first stub of the story lifecycle (the "original").
 * - `withFetchLifecycle` is a Storybook decorator that, on unmount/story
 *   cleanup, restores `globalThis.fetch` back to that captured original —
 *   even when the stub installed by the story never resolves (a permanent
 *   "loading" stub) and even when the story never calls `stubFetch` at all.
 * - Restoration must happen for every story in a run, including back to
 *   back stories, so no story's fetch stub can leak into the next story's
 *   render.
 */
import { stubFetch, withFetchLifecycle } from './fetchLifecycle';

type DecoratorContext = Parameters<Decorator>[1];

function makeContext(overrides: Partial<DecoratorContext> = {}): DecoratorContext {
    return {
        args: {},
        globals: {},
        parameters: {},
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ...(overrides as any),
    } as DecoratorContext;
}

function renderDecorated(storyRender: () => React.ReactElement) {
    const element = withFetchLifecycle(storyRender, makeContext()) as React.ReactElement;
    return render(element);
}

describe('resources/js/storybook/fetchLifecycle', () => {
    afterEach(() => {
        cleanup();
    });

    it('exports a stubFetch function and a withFetchLifecycle Storybook decorator', () => {
        expect(typeof stubFetch).toBe('function');
        expect(typeof withFetchLifecycle).toBe('function');
    });

    it('stubFetch replaces globalThis.fetch with the given handler while a story is mounted', () => {
        const originalFetch = globalThis.fetch;
        let capturedFetch: typeof fetch | undefined;

        const { unmount } = renderDecorated(() => {
            stubFetch(async () => new Response(null, { status: 204 }));
            capturedFetch = globalThis.fetch;
            return null as unknown as React.ReactElement;
        });

        expect(capturedFetch).toBeDefined();
        expect(capturedFetch).not.toBe(originalFetch);
        unmount();
    });

    it('restores the pre-story globalThis.fetch reference after the decorated story unmounts', () => {
        const originalFetch = globalThis.fetch;

        const { unmount } = renderDecorated(() => {
            stubFetch(async () => new Response(null, { status: 204 }));
            return null as unknown as React.ReactElement;
        });

        expect(globalThis.fetch).not.toBe(originalFetch);
        unmount();
        expect(globalThis.fetch).toBe(originalFetch);
    });

    it('restores globalThis.fetch after cleanup even when the stub is a never-resolving loading stub', async () => {
        const originalFetch = globalThis.fetch;

        renderDecorated(() => {
            stubFetch(
                () =>
                    new Promise<Response>(() => {
                        // never resolves: simulates a permanent loading state
                    }),
            );
            return null as unknown as React.ReactElement;
        });

        expect(globalThis.fetch).not.toBe(originalFetch);
        cleanup();
        expect(globalThis.fetch).toBe(originalFetch);
    });

    it('does not leak one story fetch stub into the next story render', async () => {
        const originalFetch = globalThis.fetch;
        let firstStoryFetchDuringSecondStory: typeof fetch | undefined;

        const first = renderDecorated(() => {
            stubFetch(async () => new Response('first', { status: 200 }));
            return null as unknown as React.ReactElement;
        });
        const fetchDuringFirstStory = globalThis.fetch;
        first.unmount();

        renderDecorated(() => {
            firstStoryFetchDuringSecondStory = fetchDuringFirstStory;
            stubFetch(async () => new Response('second', { status: 200 }));
            return null as unknown as React.ReactElement;
        });

        expect(globalThis.fetch).not.toBe(firstStoryFetchDuringSecondStory);

        const secondStoryResponse = await globalThis.fetch('/anything');
        expect(await secondStoryResponse.text()).toBe('second');

        cleanup();
        expect(globalThis.fetch).toBe(originalFetch);
    });

    it('restores the untouched original globalThis.fetch when a story never calls stubFetch', () => {
        const originalFetch = globalThis.fetch;

        const { unmount } = renderDecorated(() => null as unknown as React.ReactElement);

        expect(globalThis.fetch).toBe(originalFetch);
        unmount();
        expect(globalThis.fetch).toBe(originalFetch);
    });
});
