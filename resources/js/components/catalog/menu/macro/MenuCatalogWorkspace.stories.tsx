import type { Meta, StoryObj } from '@storybook/react-vite';
import { MenuCatalogWorkspace } from './MenuCatalogWorkspace';

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function brandUrl(): string {
    return `/api/workspaces/${WORKSPACE_ID}/brand`;
}

function menuUrl(): string {
    return `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu`;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function brand() {
    return {
        id: 1,
        workspaceId: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        locale: 'tr-TR',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
    };
}

function stubFetch(menuResponse: Response) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (globalThis as any).fetch = async (url: string) => {
        if (String(url) === brandUrl()) return jsonResponse(200, brand());
        if (String(url) === menuUrl()) return menuResponse;
        return jsonResponse(404, { message: 'Not Found.' });
    };
}

const meta: Meta<typeof MenuCatalogWorkspace> = {
    title: 'Macro/Menu/MenuCatalogWorkspace',
    component: MenuCatalogWorkspace,
    parameters: {
        docs: {
            description: {
                component:
                    'Owner journey for creating a menu, categories, products, menu items, and allergens.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof MenuCatalogWorkspace>;

export const Empty: Story = {
    loaders: [
        async () => {
            stubFetch(jsonResponse(404, { message: 'Not Found.' }));
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, locationId: LOCATION_ID },
};

export const Populated: Story = {
    loaders: [
        async () => {
            stubFetch(
                jsonResponse(200, {
                    id: 42,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_ID,
                    name: 'Ana Menü',
                    state: 'draft',
                    categories: [
                        {
                            id: 5,
                            menuId: 42,
                            name: 'Başlangıçlar',
                            position: 0,
                            menuItems: [
                                {
                                    id: 11,
                                    categoryId: 5,
                                    productId: 9,
                                    productName: 'Mercimek Çorbası',
                                    priceMinorAmount: 4250,
                                    currencyCode: 'TRY',
                                    position: 0,
                                    allergens: ['gluten', 'süt'],
                                },
                            ],
                        },
                    ],
                }),
            );
            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID, locationId: LOCATION_ID },
};
