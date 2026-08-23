import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { BrandOnboardingForm, type BrandProfile } from './BrandOnboardingForm';
import { stubFetch, withFetchLifecycle } from '../../storybook/fetchLifecycle';

type BrandOnboardingSuccessHarnessProps = {
    workspaceId: number;
    onCreated: (brand: BrandProfile) => void;
};

function BrandOnboardingSuccessHarness({ workspaceId, onCreated }: BrandOnboardingSuccessHarnessProps) {
    const [created, setCreated] = useState<BrandProfile | null>(null);

    return (
        <div>
            <BrandOnboardingForm
                workspaceId={workspaceId}
                onCreated={(brand) => {
                    setCreated(brand);
                    onCreated(brand);
                }}
            />
            <p role="status">{created ? created.name : ''}</p>
        </div>
    );
}

const WORKSPACE_ID = 5;
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function brandUrl(): string {
    return `/api/workspaces/${WORKSPACE_ID}/brand`;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function respondWith(createResponse: Response | Promise<never>) {
    stubFetch(async (url) => {
        if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
        if (String(url) === brandUrl()) return createResponse as Response;
        return jsonResponse(404, { message: 'Not Found.' });
    });
}

const CREATED_BRAND = {
    id: 3,
    workspace_id: WORKSPACE_ID,
    name: 'Zeytin Restoranları',
    slug: 'zeytin-restoranlari',
    locale: 'tr-TR',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: null,
    contact_email: null,
    contact_phone: null,
};

const meta: Meta<typeof BrandOnboardingForm> = {
    title: 'Macro/Workspace/BrandOnboardingForm',
    component: BrandOnboardingForm,
    decorators: [withFetchLifecycle],
    parameters: {
        docs: {
            description: {
                component: 'Owner journey for creating the first brand of a workspace.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof BrandOnboardingForm>;

export const Default: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
};

// A create-only onboarding form has no prior record to edit; there is
// nothing distinct from Default to represent an "editing" state.
export const EditingNotApplicable = true;

export const LoadingOrSaving: Story = {
    loaders: [
        async () => {
            respondWith(
                new Promise<never>(() => {
                    // never resolves: keeps the form in the submitting state
                }),
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
};

// The onboarding form always starts with blank fields; there is no
// separate "empty" record state distinct from Default to represent here.
export const EmptyNotApplicable = true;

export const Error: Story = {
    loaders: [
        async () => {
            respondWith(
                jsonResponse(422, {
                    message: 'The given data was invalid.',
                    errors: { name: ['The name field is required.'] },
                }),
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
};

export const Success: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    render: (args) => <BrandOnboardingSuccessHarness {...(args as BrandOnboardingSuccessHarnessProps)} />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        respondWith(jsonResponse(201, CREATED_BRAND));

        await userEvent.type(canvasElement.querySelector<HTMLInputElement>('#brand-name')!, CREATED_BRAND.name);
        await userEvent.type(
            canvasElement.querySelector<HTMLInputElement>('#brand-timezone')!,
            CREATED_BRAND.timezone,
        );
        await userEvent.type(
            canvasElement.querySelector<HTMLInputElement>('#brand-currency')!,
            CREATED_BRAND.currency,
        );

        await userEvent.click(canvas.getByRole('button'));

        await expect(await canvas.findByText(CREATED_BRAND.name)).toBeInTheDocument();
    },
};

export const Mobile320: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { viewport: { value: 'xs320', isRotated: false } },
};

export const Light: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { theme: 'light' },
};

export const Dark: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { theme: 'dark' },
};
