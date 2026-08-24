import { createElement, useEffect } from 'react';
import type { Decorator } from '@storybook/react-vite';

type FetchHandler = (...args: Parameters<typeof fetch>) => Promise<Response>;

let capturedOriginal: typeof fetch | undefined;
let hasCapturedForCycle = false;

export function stubFetch(handler: FetchHandler): void {
    if (!hasCapturedForCycle) {
        capturedOriginal = globalThis.fetch;
        hasCapturedForCycle = true;
    }
    globalThis.fetch = handler as typeof fetch;
}

function restoreCapturedFetch(): void {
    if (hasCapturedForCycle) {
        globalThis.fetch = capturedOriginal as typeof fetch;
        hasCapturedForCycle = false;
    }
}

function FetchLifecycleBoundary({ render }: { render: () => React.ReactElement }) {
    useEffect(() => {
        return () => {
            restoreCapturedFetch();
        };
    }, []);

    return render();
}

export const withFetchLifecycle: Decorator = (StoryFn) => {
    return createElement(FetchLifecycleBoundary, { render: () => StoryFn() as React.ReactElement });
};
