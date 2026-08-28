import type React from 'react';
import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { LocationOnboardingForm } from './LocationOnboardingForm';
import previewConfig from '../../../../.storybook/preview';

/**
 * RED test candidate for docs/35 (UI Storybook Component Factory Contract).
 * LocationOnboardingForm.stories.tsx currently exports only Default and
 * ValidationError. Reviewer requires the complete state matrix used by the
 * other workspace story modules (BrandEditForm.stories.tsx,
 * LocationEditForm.stories.tsx): Default, Editing, LoadingOrSaving, Empty,
 * Error, Success, Mobile320, Light, Dark — or an explicit
 * *NotApplicable declaration when a state does not semantically apply to a
 * create-only onboarding form.
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

function importStoriesModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import(/* @vite-ignore */ './LocationOnboardingForm.stories') as unknown as Promise<T>;
}

/* eslint-disable @typescript-eslint/no-explicit-any */
type AnyStory = {
    args?: Record<string, unknown>;
    parameters?: Record<string, any>;
    globals?: Record<string, any>;
};
/* eslint-enable @typescript-eslint/no-explicit-any */

describe('LocationOnboardingForm.stories — Storybook contract matrix', () => {
    it('exports a meta pointing at LocationOnboardingForm with a Macro/Workspace title', async () => {
        const mod = await importStoriesModule<{ default: { title: string; component: unknown } }>();
        expect(mod.default).toBeDefined();
        expect(mod.default.component).toBe(LocationOnboardingForm);
        expect(mod.default.title).toMatch(/Macro\/Workspace/);
    });

    it.each(REQUIRED_STATES)(
        'exports a %s story, or declares an explicit *NotApplicable metadata export',
        async (state) => {
            const mod = await importStoriesModule<
                Record<string, AnyStory | undefined> & Record<string, unknown>
            >();

            if (mod[state] === undefined) {
                expect(mod[`${state}NotApplicable`]).toBeDefined();
                return;
            }

            expect(mod[state]).toBeDefined();
        },
    );

    it('renders the Default story with real-shaped local fixture args', async () => {
        const mod = await importStoriesModule<{ Default: AnyStory }>();
        const args = mod.Default.args as {
            workspaceId: number;
            onCreated: (location: unknown) => void;
        };

        expect(args.workspaceId).toEqual(expect.any(Number));
        expect(args.onCreated).toEqual(expect.any(Function));

        render(
            <LocationOnboardingForm
                {...(args as React.ComponentProps<typeof LocationOnboardingForm>)}
            />,
        );
    });

    it('.storybook/preview.tsx declares the xs320 viewport option at 320x640 (Storybook 10 viewport.options, not the removed viewport.viewports key)', () => {
        const xs320 = (
            previewConfig as {
                parameters?: {
                    viewport?: { options?: Record<string, { styles?: Record<string, unknown> }> };
                };
            }
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

    it('Error story args produce a component-contract-shaped onCreated callback', async () => {
        const mod = await importStoriesModule<{ Error: AnyStory }>();
        const args = mod.Error.args as { onCreated?: unknown };
        expect(args.onCreated).toEqual(expect.any(Function));
    });

    it('Success story args produce a component-contract-shaped onCreated callback', async () => {
        const mod = await importStoriesModule<{ Success: AnyStory }>();
        const args = mod.Success.args as { onCreated?: unknown };
        expect(args.onCreated).toEqual(expect.any(Function));
    });

    it('LoadingOrSaving story is renderable with workspace fixture args', async () => {
        const mod = await importStoriesModule<{ LoadingOrSaving: AnyStory }>();
        const args = mod.LoadingOrSaving.args as { workspaceId?: number };
        expect(args.workspaceId).toEqual(expect.any(Number));
    });

    it('Success story provides a stateful render/play harness that surfaces the server-created location after the onCreated callback flow', async () => {
        const mod = await importStoriesModule<{
            Success: AnyStory & {
                loaders?: Array<() => Promise<unknown>>;
                render?: (args: Record<string, unknown>, ctx: unknown) => React.ReactElement;
                play?: (ctx: { canvasElement: HTMLElement }) => Promise<void>;
            };
        }>();

        expect(mod.Success.render).toEqual(expect.any(Function));
        expect(mod.Success.play).toEqual(expect.any(Function));

        /*
            Storybook, hikâyeyi çizmeden ÖNCE loader'ları çalıştırır. Bu test
            onları atlıyordu; hikâye açılışta veri çeken bir alan kazanır
            kazanmaz fark ortaya çıktı: bileşen boş bir sunucuyla monte
            oluyor, `play` ise geç kalmış bir stub kuruyordu. Loader'ları
            burada da çalıştırmak testi Storybook'un gerçek sırasına
            yaklaştırır — zayıflatmaz.
        */
        for (const load of mod.Success.loaders ?? []) {
            await load();
        }

        const args = mod.Success.args as Record<string, unknown>;
        const rendered = mod.Success.render!(args, { args } as never);
        const { container, getByText } = render(rendered as React.ReactElement);

        // Before the play step runs the server callback flow, the success
        // output surface (the created location's display name) must not be visible.
        expect(() => getByText('Kadıköy Şube')).toThrow();

        await mod.Success.play!({ canvasElement: container });

        expect(getByText('Kadıköy Şube')).toBeDefined();
    });
});
