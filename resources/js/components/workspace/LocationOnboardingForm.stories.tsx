import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { LocationOnboardingForm, type LocationProfile } from './LocationOnboardingForm';
import { stubFetch, withFetchLifecycle } from '../../storybook/fetchLifecycle';

type LocationOnboardingSuccessHarnessProps = {
    workspaceId: number;
    onCreated: (location: LocationProfile) => void;
};

function LocationOnboardingSuccessHarness({
    workspaceId,
    onCreated,
}: LocationOnboardingSuccessHarnessProps) {
    const [created, setCreated] = useState<LocationProfile | null>(null);

    return (
        <div>
            <LocationOnboardingForm
                workspaceId={workspaceId}
                onCreated={(location) => {
                    setCreated(location);
                    onCreated(location);
                }}
            />
            <p role="status">{created ? created.display_name : ''}</p>
        </div>
    );
}

const WORKSPACE_ID = 5;
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function locationsUrl(): string {
    return `/api/workspaces/${WORKSPACE_ID}/brand/locations`;
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
        if (String(url).startsWith('/api/reference/markets')) {
            // Ülke ve saat dilimi LİSTEDEN seçilir (docs/62).
            return jsonResponse(200, {
                markets: [{ code: 'TR', name: 'Türkiye' }],
                timezones: [{ id: 'Europe/Istanbul', label: 'İstanbul — UTC+03:00' }],
                defaults: { timezone: 'Europe/Istanbul', currency: 'TRY' },
                suggestedCountry: null,
            });
        }
        if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
        if (String(url) === locationsUrl()) return createResponse as Response;
        return jsonResponse(404, { message: 'Not Found.' });
    });
}

const CREATED_LOCATION = {
    id: 9,
    workspace_id: WORKSPACE_ID,
    brand_id: 3,
    display_name: 'Kadıköy Şube',
    country_code: 'TR',
    timezone: 'Europe/Istanbul',
    city: 'İstanbul',
    address_line1: 'Moda Cd. No:12',
    address_line2: null,
    postal_code: null,
};

const meta: Meta<typeof LocationOnboardingForm> = {
    title: 'Macro/Workspace/LocationOnboardingForm',
    component: LocationOnboardingForm,
    decorators: [withFetchLifecycle],
    parameters: {
        docs: {
            description: {
                component: 'Owner journey for creating the first location of a brand.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof LocationOnboardingForm>;

export const Default: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_LOCATION));
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
                    errors: { display_name: ['The display name field is required.'] },
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
            respondWith(jsonResponse(201, CREATED_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    render: (args) => (
        <LocationOnboardingSuccessHarness {...(args as LocationOnboardingSuccessHarnessProps)} />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        respondWith(jsonResponse(201, CREATED_LOCATION));

        await userEvent.type(
            canvasElement.querySelector<HTMLInputElement>('#location-display-name')!,
            CREATED_LOCATION.display_name,
        );
        /*
            Ülke artık yazılmaz, SEÇİLİR (docs/62). Seçenek sunucudan geldiği
            için önce beklenir.
        */
        await canvas.findByRole('option', { name: 'Türkiye' });
        await userEvent.selectOptions(
            canvasElement.querySelector<HTMLSelectElement>('#location-country-code')!,
            CREATED_LOCATION.country_code,
        );
        await userEvent.type(
            canvasElement.querySelector<HTMLInputElement>('#location-city')!,
            CREATED_LOCATION.city,
        );
        await userEvent.type(
            canvasElement.querySelector<HTMLInputElement>('#location-address-line1')!,
            CREATED_LOCATION.address_line1,
        );

        await userEvent.click(canvas.getByRole('button'));

        await expect(await canvas.findByText(CREATED_LOCATION.display_name)).toBeInTheDocument();
    },
};

export const Mobile320: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { viewport: { value: 'xs320', isRotated: false } },
};

export const Light: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { theme: 'light' },
};

export const Dark: Story = {
    loaders: [
        async () => {
            respondWith(jsonResponse(201, CREATED_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, onCreated: () => {} },
    globals: { theme: 'dark' },
};
