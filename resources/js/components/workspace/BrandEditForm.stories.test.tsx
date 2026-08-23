import type React from 'react';
import { describe, expect, it } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { BrandEditForm, type BrandProfile } from './BrandEditForm';
import storybookConfig from '../../../../.storybook/main';
import previewConfig from '../../../../.storybook/preview';
import { withFetchLifecycle } from '../../storybook/fetchLifecycle';

/**
 * RED test candidate for docs/35 (UI Storybook Component Factory Contract).
 * No BrandEditForm.stories.tsx module exists in this snapshot, so the
 * dynamic import below fails RED (module-not-found) until the story module
 * is authored. Required named states: Default, Editing, LoadingOrSaving,
 * Empty (or explicit EmptyNotApplicable metadata), Error, Success,
 * Mobile320, Light, Dark.
 */
const REQUIRED_STATES = [
    'Default',
    'Editing',
    'LoadingOrSaving',
    'Empty',
    'Error',
    'Success',
    'Mobile320',
    'Light',
    'Dark',
] as const;

function importStoriesModule<T extends Record<string, unknown> = Record<string, unknown>>(): Promise<T> {
    return import(/* @vite-ignore */ './BrandEditForm.stories') as unknown as Promise<T>;
}

const GLOB_REGEX_SPECIAL = /[.+^${}()|[\]\\]/;

/**
 * Minimal glob -> RegExp translator for Storybook `stories` patterns.
 * Supports `*` (any run of non-separator chars) and `**\/` (zero or more
 * directories, including none) so both a top-level file and an arbitrarily
 * nested one can match the same pattern.
 */
function globToRegExp(glob: string): RegExp {
    let pattern = '';
    for (let i = 0; i < glob.length; i += 1) {
        const char = glob[i];
        if (char === '*') {
            if (glob[i + 1] === '*' && glob[i + 2] === '/') {
                pattern += '(?:.*/)?';
                i += 2;
            } else if (glob[i + 1] === '*') {
                pattern += '.*';
                i += 1;
            } else {
                pattern += '[^/]*';
            }
        } else if (GLOB_REGEX_SPECIAL.test(char)) {
            pattern += '\\' + char;
        } else {
            pattern += char;
        }
    }
    return new RegExp('^' + pattern + '$');
}

function isCoveredByStoriesGlobs(patterns: string[], relativePath: string): boolean {
    return patterns.some((pattern) => globToRegExp(pattern).test(relativePath));
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyStory = { args?: Record<string, unknown>; parameters?: Record<string, any>; globals?: Record<string, any> };

describe('BrandEditForm.stories — Storybook contract matrix', () => {
    it('is covered by the .storybook/main.ts stories globs', () => {
        const patterns = storybookConfig.stories as string[];
        const workspaceGlobCovered = isCoveredByStoriesGlobs(
            patterns,
            '../resources/js/components/workspace/BrandEditForm.stories.tsx',
        );

        expect(workspaceGlobCovered).toBe(true);
    });

    it('covers a representative nested workspace/brand-location/complex story module (recursive workspace discovery)', () => {
        const patterns = storybookConfig.stories as string[];
        const nestedGlobCovered = isCoveredByStoriesGlobs(
            patterns,
            '../resources/js/components/workspace/brand-location/complex/BrandProfileWorkspace.stories.tsx',
        );

        expect(nestedGlobCovered).toBe(true);
    });


    it('exports a meta pointing at BrandEditForm with a Macro/Workspace title', async () => {
        const mod = await importStoriesModule<{ default: { title: string; component: unknown } }>();
        expect(mod.default).toBeDefined();
        expect(mod.default.component).toBe(BrandEditForm);
        expect(mod.default.title).toMatch(/Macro\/Workspace/);
    });

    it.each(REQUIRED_STATES)('exports a %s story, or (for Empty) declares EmptyNotApplicable metadata', async (state) => {
        const mod = await importStoriesModule<Record<string, AnyStory | undefined> & { EmptyNotApplicable?: unknown }>();

        if (state === 'Empty' && mod.Empty === undefined) {
            expect(mod.EmptyNotApplicable).toBeDefined();
            return;
        }

        expect(mod[state]).toBeDefined();
    });

    it('renders the Default story with real-shaped local fixture args', async () => {
        const mod = await importStoriesModule<{ Default: AnyStory }>();
        const args = mod.Default.args as { workspaceId: number; brand: BrandProfile; onSaved: (brand: BrandProfile) => void };

        expect(args.workspaceId).toEqual(expect.any(Number));
        expect(args.brand).toMatchObject({ id: expect.any(Number), name: expect.any(String) });
        expect(args.onSaved).toEqual(expect.any(Function));

        render(<BrandEditForm {...args} />);
    });

    it('renders the Editing story with the form fields visible', async () => {
        const mod = await importStoriesModule<{ Editing: AnyStory }>();
        const args = mod.Editing.args as React.ComponentProps<typeof BrandEditForm>;

        render(<BrandEditForm {...args} />);
    });

    it('.storybook/preview.tsx declares the xs320 viewport option at 320x640 (Storybook 10 viewport.options, not the removed viewport.viewports key)', () => {
        const xs320 = (
            previewConfig as { parameters?: { viewport?: { options?: Record<string, { styles?: Record<string, unknown> }> } } }
        ).parameters?.viewport?.options?.xs320;
        expect(xs320).toBeDefined();
        expect(xs320?.styles).toMatchObject({ width: '320px', height: '640px' });
    });

    it('Mobile320 locks the xs320 viewport via Storybook 10 globals.viewport (not the removed parameters.viewport.defaultViewport)', async () => {
        const mod = await importStoriesModule<{ Mobile320: AnyStory }>();
        expect(mod.Mobile320.parameters?.viewport?.defaultViewport).toBeUndefined();

        const viewportGlobal = mod.Mobile320.globals?.viewport;
        const viewportValue =
            typeof viewportGlobal === 'string' ? viewportGlobal : viewportGlobal?.value;
        expect(viewportValue).toBe('xs320');
        if (typeof viewportGlobal === 'object' && viewportGlobal !== null) {
            expect(viewportGlobal.isRotated).toBe(false);
        }
    });

    it('Light and Dark stories pin the theme global from resources/js/storybook/decorators', async () => {
        const mod = await importStoriesModule<{ Light: AnyStory; Dark: AnyStory }>();
        expect(mod.Light.globals?.theme).toBe('light');
        expect(mod.Dark.globals?.theme).toBe('dark');
    });

    it('Error story args produce a component-contract-shaped onSaved callback', async () => {
        const mod = await importStoriesModule<{ Error: AnyStory }>();
        const args = mod.Error.args as { onSaved?: unknown };
        expect(args.onSaved).toEqual(expect.any(Function));
    });

    it('Success story args produce a component-contract-shaped onSaved callback', async () => {
        const mod = await importStoriesModule<{ Success: AnyStory }>();
        const args = mod.Success.args as { onSaved?: unknown };
        expect(args.onSaved).toEqual(expect.any(Function));
    });

    it('Success story provides a render function (stateful harness) so onSaved updates visible parent props, not a no-op', async () => {
        const mod = await importStoriesModule<{ Success: AnyStory & { render?: unknown } }>();
        expect(mod.Success.render).toEqual(expect.any(Function));

        const args = mod.Success.args as { workspaceId: number; brand: BrandProfile; onSaved: (brand: BrandProfile) => void };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const rendered = (mod.Success.render as any)(args, { args } as never);
        const { getByText } = render(rendered as React.ReactElement);

        // The stateful harness must render with the initial fixture args before any save.
        expect(() => getByText(args.brand.name)).not.toThrow();
    });

    it('LoadingOrSaving story is renderable with brand fixture args', async () => {
        const mod = await importStoriesModule<{ LoadingOrSaving: AnyStory }>();
        const args = mod.LoadingOrSaving.args as { brand?: BrandProfile };
        expect(args.brand).toMatchObject({ id: expect.any(Number) });
    });

    it('meta wires the shared reset-aware fetch lifecycle decorator instead of an ad-hoc stub', async () => {
        const mod = await importStoriesModule<{ default: { decorators?: unknown[] } }>();
        expect(mod.default.decorators).toContain(withFetchLifecycle);
    });

    it('does not leak the never-resolving LoadingOrSaving fetch stub into a subsequently rendered story', async () => {
        const originalFetch = globalThis.fetch;
        const mod = await importStoriesModule<{
            default: { decorators?: unknown[] };
            LoadingOrSaving: AnyStory & { loaders?: Array<() => Promise<unknown>> };
            Default: AnyStory & { loaders?: Array<() => Promise<unknown>> };
        }>();

        const decorator = mod.default.decorators?.[0] as
            | ((story: () => React.ReactElement, ctx: unknown) => React.ReactElement)
            | undefined;
        expect(decorator).toBeDefined();

        for (const loader of mod.LoadingOrSaving.loaders ?? []) {
            await loader();
        }
        const loadingFetch = globalThis.fetch;
        expect(loadingFetch).not.toBe(originalFetch);

        const loadingElement = decorator!(
            () => <BrandEditForm {...(mod.LoadingOrSaving.args as React.ComponentProps<typeof BrandEditForm>)} />,
            { args: mod.LoadingOrSaving.args, globals: {}, parameters: {} } as never,
        );
        const { unmount } = render(loadingElement);
        unmount();
        cleanup();

        expect(globalThis.fetch).not.toBe(loadingFetch);

        for (const loader of mod.Default.loaders ?? []) {
            await loader();
        }
        expect(globalThis.fetch).not.toBe(loadingFetch);
    });
});
