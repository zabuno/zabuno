import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { BrandEditForm, type BrandProfile } from './BrandEditForm';
import {
    stubFetch as stubFetchLifecycle,
    withFetchLifecycle,
} from '../../storybook/fetchLifecycle';

type BrandEditFormHarnessProps = {
    workspaceId: number;
    brand: BrandProfile;
    onSaved: (brand: BrandProfile) => void;
};

function BrandEditFormHarness({ workspaceId, brand, onSaved }: BrandEditFormHarnessProps) {
    const [current, setCurrent] = useState(brand);

    return (
        <BrandEditForm
            workspaceId={workspaceId}
            brand={current}
            onSaved={(updated) => {
                setCurrent(updated);
                onSaved(updated);
            }}
        />
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

function stubFetch(createResponse: Response) {
    stubFetchLifecycle(async (url) => {
        if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
        if (String(url) === brandUrl()) return createResponse;
        return jsonResponse(404, { message: 'Not Found.' });
    });
}

const BASE_BRAND: BrandProfile = {
    id: 3,
    workspace_id: WORKSPACE_ID,
    name: 'Zeytin Restoranları',
    slug: 'zeytin-restoranlari',
    locale: 'tr-TR',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: 'Aile işletmesi zincir restoran markası.',
    contact_email: 'iletisim@zeytin.example',
    contact_phone: '+90 555 000 00 00',
};

const EMPTY_BRAND: BrandProfile = {
    id: 4,
    workspace_id: WORKSPACE_ID,
    name: 'Yeni Marka',
    slug: 'yeni-marka',
    locale: 'tr-TR',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: null,
    contact_email: null,
    contact_phone: null,
};

const meta: Meta<typeof BrandEditForm> = {
    title: 'Macro/Workspace/BrandEditForm',
    component: BrandEditForm,
    decorators: [withFetchLifecycle],
    parameters: {
        docs: {
            description: {
                component: 'Owner journey for editing an existing brand profile.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof BrandEditForm>;

export const Default: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(200, BASE_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
};

export const Editing: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(200, BASE_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        await expect(canvas.getByLabelText(/marka adı|name/i)).toBeInTheDocument();
    },
};

export const LoadingOrSaving: Story = {
    loaders: [
        async () => {
            stubFetch(
                new Promise(() => {
                    // never resolves: keeps the form in the saving state
                }) as unknown as Response,
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
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
            stubFetch(jsonResponse(200, EMPTY_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: EMPTY_BRAND, onSaved: () => {} },
};

export const Error: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(422, { message: 'The given data was invalid.' }));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
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
            stubFetch(jsonResponse(200, { ...BASE_BRAND, name: 'Güncellenmiş Marka' }));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
    render: (args) => <BrandEditFormHarness {...(args as BrandEditFormHarnessProps)} />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const editButton = await canvas.findByRole('button', { name: /düzenle|edit/i });
        await userEvent.click(editButton);
        const saveButton = await canvas.findByRole('button', { name: /kaydet|save/i });
        await userEvent.click(saveButton);
        await expect(await canvas.findByText('Güncellenmiş Marka')).toBeInTheDocument();
    },
};

export const Mobile320: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(200, BASE_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
    globals: { viewport: { value: 'xs320', isRotated: false } },
};

export const Light: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(200, BASE_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
    globals: { theme: 'light' },
};

export const Dark: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(200, BASE_BRAND));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, brand: BASE_BRAND, onSaved: () => {} },
    globals: { theme: 'dark' },
};
