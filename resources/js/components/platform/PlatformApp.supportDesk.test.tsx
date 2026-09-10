import { describe, beforeEach, afterEach, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * DESTEK MASASI, `/platform` KABUĞUNDA — `docs/122` §3 boşluk 3 ve Y7.
 *
 * Bölüm ADRESTEN gelir, fragment'ten değil (`docs/38` §4): destek görevlisi
 * bulduğu ekranın bağlantısını meslektaşına yollayabilmeli ve ölçüm bu
 * ekranı kendi adıyla görebilmeli.
 *
 * BÖLÜM GÖZETİM GRUBUNDA. Ticari grupta olsaydı bir destek aracı bir satış
 * işi gibi görünürdü; `docs/122` §5'in "en tehlikeli yetenek" cümlesi de
 * plan/abonelik komşuluğunda hafiflerdi.
 */

const SUPPORT_ACCESS_ENDPOINT = '/api/admin/support-access';
const SUPPORT_REQUESTS_ENDPOINT = '/api/admin/support-requests';
const WORKSPACES_ENDPOINT = '/api/admin/workspaces';
const SUPPORT_VIEW_ENDPOINT = '/api/admin/workspaces/7/support-view';

/** Kuyruktaki tek satır: bir restoran hesabına BAĞLI, yani açılabilir. */
const queueRow = {
    id: 11,
    reference: 'ZB-3F7K2',
    workspace_id: 7,
    name: 'Hüseyin',
    email: 'huseyin@example.com',
    subject: 'Menüm görünmüyor',
    message: 'Karekodu okutunca boş sayfa açılıyor.',
    channel: 'panel',
    status: 'received',
    received_at: '2026-09-06 09:14:02',
    first_response_at: null,
    acknowledgement: 'sent',
};

const supportView = {
    workspace: { id: 7, name: 'Acme Corp', slug: 'acme-corp', state: 'active' },
    subscription: { state: 'active' },
    locations: [],
    supportRequests: [],
    accessHistory: [],
    findings: [],
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function buildFetchMock(
    options: { queue?: unknown[]; supportViewStatus?: number } = {},
) {
    const queue = options.queue ?? [];
    const supportViewStatus = options.supportViewStatus ?? 200;

    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const parsed = new URL(String(url), 'http://localhost');

        if (parsed.pathname === SUPPORT_ACCESS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, { session: null, windowMinutes: 15, reasonMinLength: 12 });
        }

        if (parsed.pathname === SUPPORT_REQUESTS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, queue);
        }

        if (parsed.pathname === SUPPORT_VIEW_ENDPOINT && method === 'GET') {
            return jsonResponse(
                supportViewStatus,
                supportViewStatus === 200 ? supportView : { message: 'nope' },
            );
        }

        if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET') {
            return jsonResponse(200, [
                { id: 7, name: 'Acme Corp', slug: 'acme-corp', state: 'active' },
            ]);
        }

        throw new Error(
            `Unhandled fetch in PlatformApp support desk test: ${method} ${String(url)}`,
        );
    });
}

async function importPlatformAppModule() {
    return import('./PlatformApp') as unknown as Promise<{ PlatformApp: React.ComponentType }>;
}

describe('PlatformApp — support desk', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        vi.stubGlobal('fetch', buildFetchMock());
        history.replaceState(null, '', '/platform');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        history.replaceState(null, '', '/platform');
    });

    it('exposes a Support desk nav item', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        expect(await screen.findByRole('link', { name: /support desk/i })).toBeInTheDocument();
    });

    it('opens the support desk at /platform/support and asks for nothing until a tenant is picked', async () => {
        history.replaceState(null, '', '/platform/support');

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const nav = await screen.findByRole('link', { name: /support desk/i });
        expect(nav).toHaveAttribute('aria-current', 'page');

        // Kiracı seçilmeden hiçbir kiracı verisi okunmaz ve hiçbir bakma
        // kartı çizilmez: impersonation bir varsayılan değil, bir karardır.
        expect(
            await screen.findByText(/Pick a restaurant to see what its guests/),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('region', { name: /Look at this account as the tenant/ }),
        ).toBeNull();
    });

    /**
     * TEK TIKLA MASA AÇILIR — seçici hiç kullanılmadan.
     *
     * Destek günü şöyle geçiyordu: kuyrukta talebi oku, restoranın adını
     * aklında tut, aşağı in, seçicide o adı ARA, tıkla, masanın yüklenmesini
     * bekle. Arada iki ekran ve bir hafıza sınavı vardı; yanlış restoranı
     * seçmek de mümkündü. Artık talebin kendi satırındaki tek düğme
     * SUNUCUNUN döndürdüğü kiracıyı seçili hâle getiriyor ve odak masaya
     * gidiyor: klavyeyle çalışan görevli, tıkladığı yerden devam ediyor.
     */
    it('opens the tenant support desk from the queue in one click, without using the picker', async () => {
        history.replaceState(null, '', '/platform/support');
        vi.stubGlobal('fetch', buildFetchMock({ queue: [queueRow] }));

        const user = userEvent.setup();
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        await user.click(await screen.findByRole('button', { name: /Open the support desk/ }));

        const desk = await screen.findByRole('group', { name: 'Support desk for Acme Corp' });
        expect(desk).toHaveFocus();

        // Seçim SUNUCUNUN gövdesinden kuruldu; seçiciye hiç dokunulmadı.
        expect(screen.getByRole('combobox', { name: 'Workspace' })).toHaveTextContent('Acme Corp');
    });

    /**
     * OKUNAMAYAN HESAP SEÇİLİ KALMAZ.
     *
     * Yükleme başarısızken seçimi yine de kurmak, en tehlikeli yetenek olan
     * "kiracı olarak bak" düğmesini HİÇ OKUNMAMIŞ bir hesap için silahlamak
     * demekti: görevli sonra "aç" derdi ve sahibinin denetim izine, aslında
     * hiç görülmemiş bir hesap için bir bakış kaydı düşerdi.
     */
    it('leaves no tenant armed when the one-click load fails', async () => {
        history.replaceState(null, '', '/platform/support');
        vi.stubGlobal('fetch', buildFetchMock({ queue: [queueRow], supportViewStatus: 500 }));

        const user = userEvent.setup();
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        await user.click(await screen.findByRole('button', { name: /Open the support desk/ }));

        expect(await screen.findByText('We could not read this account.')).toBeInTheDocument();
        expect(screen.getByRole('combobox', { name: 'Workspace' })).toHaveTextContent(
            'Select a workspace',
        );
    });
});
