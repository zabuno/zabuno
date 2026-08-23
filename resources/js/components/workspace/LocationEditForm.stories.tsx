import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { LocationEditForm, type LocationProfile } from './LocationEditForm';
import { stubFetch as stubFetchLifecycle, withFetchLifecycle } from '../../storybook/fetchLifecycle';

type LocationEditFormHarnessProps = {
    workspaceId: number;
    location: LocationProfile;
    onSaved: (location: LocationProfile) => void;
};

function LocationEditFormHarness({ workspaceId, location, onSaved }: LocationEditFormHarnessProps) {
    const [current, setCurrent] = useState(location);

    return (
        <LocationEditForm
            workspaceId={workspaceId}
            location={current}
            onSaved={(updated) => {
                setCurrent(updated);
                onSaved(updated);
            }}
        />
    );
}

const WORKSPACE_ID = 5;
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function locationUrl(id: number): string {
    return `/api/workspaces/${WORKSPACE_ID}/brand/locations/${id}`;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function stubFetch(id: number, createResponse: Response) {
    stubFetchLifecycle(async (url) => {
        if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
        if (String(url) === locationUrl(id)) return createResponse;
        return jsonResponse(404, { message: 'Not Found.' });
    });
}

const BASE_LOCATION: LocationProfile = {
    id: 7,
    workspace_id: WORKSPACE_ID,
    brand_id: 3,
    display_name: 'Kadıköy Şube',
    country_code: 'TR',
    city: 'İstanbul',
    address_line1: 'Moda Cd. No: 12',
    address_line2: 'Kat 2',
    postal_code: '34710',
};

const EMPTY_LOCATION: LocationProfile = {
    id: 8,
    workspace_id: WORKSPACE_ID,
    brand_id: 3,
    display_name: 'Yeni Şube',
    country_code: 'TR',
    city: 'Ankara',
    address_line1: 'Merkez Cd. No: 1',
    address_line2: null,
    postal_code: null,
};

const meta: Meta<typeof LocationEditForm> = {
    title: 'Macro/Workspace/LocationEditForm',
    component: LocationEditForm,
    decorators: [withFetchLifecycle],
    parameters: {
        docs: {
            description: {
                component: 'Owner journey for editing an existing brand location.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof LocationEditForm>;

export const Default: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(200, BASE_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
};

export const Editing: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(200, BASE_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        await expect(canvas.getByLabelText(/görünen ad|display name/i)).toBeInTheDocument();
    },
};

export const LoadingOrSaving: Story = {
    loaders: [
        async () => {
            stubFetch(
                BASE_LOCATION.id,
                new Promise(() => {
                    // never resolves: keeps the form in the saving state
                }) as unknown as Response,
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        const saveButton = await canvas.findByRole('button', { name: /kaydet|save/i });
        await userEvent.click(saveButton);
        await expect(saveButton).toBeDisabled();
    },
};

export const Empty: Story = {
    loaders: [
        async () => {
            stubFetch(EMPTY_LOCATION.id, jsonResponse(200, EMPTY_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: EMPTY_LOCATION, onSaved: () => {} },
};

export const Error: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(422, { message: 'The given data was invalid.' }));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        const saveButton = await canvas.findByRole('button', { name: /kaydet|save/i });
        await userEvent.click(saveButton);
        await expect(await canvas.findByRole('alert')).toBeInTheDocument();
    },
};

export const Success: Story = {
    loaders: [
        async () => {
            stubFetch(
                BASE_LOCATION.id,
                jsonResponse(200, { ...BASE_LOCATION, display_name: 'Güncellenmiş Şube' }),
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    render: (args) => <LocationEditFormHarness {...(args as LocationEditFormHarnessProps)} />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        const saveButton = await canvas.findByRole('button', { name: /kaydet|save/i });
        await userEvent.click(saveButton);
        await expect(await canvas.findByText('Güncellenmiş Şube')).toBeInTheDocument();
    },
};

export const Mobile320: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(200, BASE_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    globals: { viewport: { value: 'xs320', isRotated: false } },
};

export const Light: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(200, BASE_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    globals: { theme: 'light' },
};

export const Dark: Story = {
    loaders: [
        async () => {
            stubFetch(BASE_LOCATION.id, jsonResponse(200, BASE_LOCATION));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, location: BASE_LOCATION, onSaved: () => {} },
    globals: { theme: 'dark' },
};
