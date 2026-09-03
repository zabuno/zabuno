import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ProviderCredentialsPage } from './ProviderCredentialsPage';

/**
 * CRED-GUI — superadmin sağlayıcı kasası paneli (Vault Faz 4 + `docs/95` Faz 3).
 *
 * Panel sırrı asla değer olarak göstermez (yalnız maske), boş bırakılan
 * sırrı GÖNDERMEZ (öncekini korur), ve yazma isteği CSRF + XSRF ile gider.
 *
 * Faz 3'te görünüm "her sağlayıcı bir kart"tan **sağlayıcı → N bağlantı
 * kartı**na geçti: aynı sağlayıcının iki hesabı olabilir (`docs/96` Faz 3,
 * toplu içe aktarma paylaşılan kotayı korumak için ayrı bir hesapta çalışır)
 * ve düz liste bunu gösteremezdi.
 */

function providers() {
    return [
        {
            provider: 'mailgun',
            fields: [
                { name: 'domain', secret: false, required: true, default: null },
                { name: 'secret', secret: true, required: true, default: null },
                { name: 'endpoint', secret: false, required: false, default: 'api.mailgun.net' },
            ],
        },
        {
            provider: 'openai',
            fields: [
                { name: 'api_key', secret: true, required: true, default: null },
                {
                    name: 'base_url',
                    secret: false,
                    required: false,
                    default: 'https://api.openai.com/v1',
                },
                { name: 'organization', secret: false, required: false, default: null },
                { name: 'project', secret: false, required: false, default: null },
            ],
        },
    ];
}

function connections() {
    return [
        {
            id: 1,
            provider: 'mailgun',
            label: 'Varsayılan',
            scope: 'platform_owned',
            workspaceId: null,
            configured: true,
            state: 'active',
            health: 'unknown',
            lastRotatedAt: '2026-09-03T00:00:00Z',
            lastHealthCheckAt: null,
            fields: [
                { name: 'domain', secret: false, isSet: true, preview: 'sandbox123.mailgun.org' },
                { name: 'secret', secret: true, isSet: true, preview: '••••b1c0' },
                { name: 'endpoint', secret: false, isSet: true, preview: 'api.mailgun.net' },
            ],
        },
        {
            id: 2,
            provider: 'openai',
            label: 'OpenAI — Menü İçe Aktarma',
            scope: 'platform_owned',
            workspaceId: null,
            configured: true,
            state: 'active',
            health: 'healthy',
            lastRotatedAt: null,
            lastHealthCheckAt: null,
            fields: [
                { name: 'api_key', secret: true, isSet: true, preview: '••••1111' },
                { name: 'base_url', secret: false, isSet: false, preview: null },
                { name: 'organization', secret: false, isSet: false, preview: null },
                { name: 'project', secret: false, isSet: false, preview: null },
            ],
        },
        {
            id: 3,
            provider: 'openai',
            label: 'OpenAI — Toplu İçe Aktarma',
            scope: 'platform_owned',
            workspaceId: null,
            configured: true,
            state: 'active',
            health: 'unknown',
            lastRotatedAt: null,
            lastHealthCheckAt: null,
            fields: [
                { name: 'api_key', secret: true, isSet: true, preview: '••••2222' },
                { name: 'base_url', secret: false, isSet: false, preview: null },
                { name: 'organization', secret: false, isSet: false, preview: null },
                { name: 'project', secret: false, isSet: false, preview: null },
            ],
        },
    ];
}

function payload() {
    return { providers: providers(), connections: connections() };
}

function jsonResponse(body: unknown, ok = true): Response {
    return {
        ok,
        status: ok ? 200 : 500,
        json: async () => body,
    } as Response;
}

describe('ProviderCredentialsPage', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-token';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('groups connections under their provider and never shows a raw secret', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);

        await waitFor(() =>
            expect(screen.getByRole('heading', { name: 'Mailgun (email)' })).toBeInTheDocument(),
        );

        // Aynı sağlayıcının İKİ hesabı yan yana ve adlarıyla ayrılıyor.
        expect(
            screen.getByRole('heading', { name: 'OpenAI — Menü İçe Aktarma' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'OpenAI — Toplu İçe Aktarma' }),
        ).toBeInTheDocument();

        expect(screen.getByTestId('state-1')).toHaveTextContent('Active');

        // Sır alanı password tipinde ve mevcut değeri boş (maske ayrı gösterilir).
        const secretInput = screen.getByLabelText(/Secret \/ API key/i) as HTMLInputElement;
        expect(secretInput.type).toBe('password');
        expect(secretInput.value).toBe('');
    });

    it('shows a health state, and “not checked yet” is not the same as healthy', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(
                screen.getByRole('heading', { name: 'OpenAI — Menü İçe Aktarma' }),
            ).toBeInTheDocument(),
        );

        const healthy = screen
            .getByRole('heading', { name: 'OpenAI — Menü İçe Aktarma' })
            .closest('li')!;
        const unchecked = screen
            .getByRole('heading', { name: 'OpenAI — Toplu İçe Aktarma' })
            .closest('li')!;

        expect(within(healthy).getByText(/Healthy/)).toBeInTheDocument();
        expect(within(unchecked).getByText(/Not checked yet/)).toBeInTheDocument();
    });

    it('omits a blank secret on save but sends what the admin typed', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(payload())) // ilk yükleme
            .mockResolvedValueOnce(jsonResponse({})) // csrf
            .mockResolvedValueOnce(jsonResponse(connections()[0])) // PUT
            .mockResolvedValueOnce(jsonResponse(payload())); // yeniden yükleme

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(screen.getByRole('heading', { name: 'Varsayılan' })).toBeInTheDocument(),
        );

        const card = screen.getByRole('heading', { name: 'Varsayılan' }).closest('li')!;
        const domainInput = within(card).getByLabelText('Domain');
        await userEvent.clear(domainInput);
        await userEvent.type(domainInput, 'new.mailgun.org');

        await userEvent.click(within(card).getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved.'));

        const putCall = fetchMock.mock.calls.find(
            (call) =>
                String(call[0]).endsWith('/connections/1') &&
                (call[1] as RequestInit)?.method === 'PUT',
        );
        expect(putCall).toBeTruthy();
        const body = JSON.parse((putCall![1] as RequestInit).body as string);
        expect(body.fields.domain).toBe('new.mailgun.org');
        // Boş sır gönderilmedi.
        expect(body.fields).not.toHaveProperty('secret');
        // XSRF başlığı var.
        const headers = (putCall![1] as RequestInit).headers as Headers;
        expect(headers.get('X-XSRF-TOKEN')).toBe('test-token');
    });

    it('disables one connection without touching its sibling', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(payload()))
            .mockResolvedValueOnce(jsonResponse({}))
            .mockResolvedValueOnce(jsonResponse({ ...connections()[2], state: 'disabled' }))
            .mockResolvedValueOnce(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(
                screen.getByRole('heading', { name: 'OpenAI — Toplu İçe Aktarma' }),
            ).toBeInTheDocument(),
        );

        const card = screen
            .getByRole('heading', { name: 'OpenAI — Toplu İçe Aktarma' })
            .closest('li')!;
        await userEvent.click(within(card).getByRole('button', { name: 'Disable' }));

        await waitFor(() => {
            const call = fetchMock.mock.calls.find((entry) =>
                String(entry[0]).endsWith('/connections/3/disable'),
            );
            expect(call).toBeTruthy();
        });
    });

    // --- `docs/95` Faz 3 UX sözleşmesi, adım 2-4 --------------------------

    it('keeps the shared fields really disabled until a provider is chosen', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(screen.getByRole('button', { name: '+ Add a connection' })).toBeInTheDocument(),
        );

        await userEvent.click(screen.getByRole('button', { name: '+ Add a connection' }));

        // Gerçek `disabled` — yalnız soluk bir görünüm değil; ekran okuyucu
        // da "devre dışı" demeli.
        expect(screen.getByLabelText('Connection name')).toBeDisabled();
        expect(screen.getByLabelText('Who owns this key')).toBeDisabled();
        // Sağlayıcıya özel alanlar henüz HİÇ çizilmemiş olmalı.
        expect(screen.queryByLabelText('API key')).not.toBeInTheDocument();

        await userEvent.selectOptions(screen.getByLabelText('Provider'), 'openai');

        expect(screen.getByLabelText('Connection name')).toBeEnabled();
        expect(screen.getByLabelText('Who owns this key')).toBeEnabled();

        // Ve şimdi OpenAI'ın kendi alanları geldi. Kapsam FORMLA sınırlı:
        // aynı adı taşıyan alanlar mevcut bağlantı kartlarında da var.
        const form = screen.getByRole('region', { name: 'New connection' });
        expect(within(form).getByLabelText('API key')).toBeInTheDocument();
        expect(within(form).getByLabelText('Organization')).toBeInTheDocument();
    });

    it('creates a second connection for a provider that already has one', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(payload()))
            .mockResolvedValueOnce(jsonResponse({})) // csrf
            .mockResolvedValueOnce(jsonResponse(connections()[2])) // POST
            .mockResolvedValueOnce(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(screen.getByRole('button', { name: '+ Add a connection' })).toBeInTheDocument(),
        );

        await userEvent.click(screen.getByRole('button', { name: '+ Add a connection' }));
        await userEvent.selectOptions(screen.getByLabelText('Provider'), 'openai');
        await userEvent.type(screen.getByLabelText('Connection name'), 'OpenAI — Toplu');
        await userEvent.type(screen.getByLabelText('API key'), 'sk-bulk-9999');
        await userEvent.click(screen.getByRole('button', { name: 'Save connection' }));

        await waitFor(() => {
            const post = fetchMock.mock.calls.find(
                (call) =>
                    String(call[0]).endsWith('/admin/connections') &&
                    (call[1] as RequestInit)?.method === 'POST',
            );
            expect(post).toBeTruthy();
            const body = JSON.parse((post![1] as RequestInit).body as string);
            expect(body.provider).toBe('openai');
            expect(body.label).toBe('OpenAI — Toplu');
            expect(body.scope).toBe('platform_owned');
            expect(body.fields.api_key).toBe('sk-bulk-9999');
        });
    });

    it('asks for a workspace only when the key belongs to a customer', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(payload()));

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(screen.getByRole('button', { name: '+ Add a connection' })).toBeInTheDocument(),
        );

        await userEvent.click(screen.getByRole('button', { name: '+ Add a connection' }));
        await userEvent.selectOptions(screen.getByLabelText('Provider'), 'openai');

        expect(screen.queryByLabelText('Workspace ID')).not.toBeInTheDocument();

        await userEvent.selectOptions(screen.getByLabelText('Who owns this key'), 'tenant_byok');

        expect(screen.getByLabelText('Workspace ID')).toBeInTheDocument();
    });

    it('surfaces the server’s reason when a connection is rejected', async () => {
        vi.spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(payload()))
            .mockResolvedValueOnce(jsonResponse({}))
            .mockResolvedValueOnce({
                ok: false,
                status: 422,
                json: async () => ({
                    message: 'BYOK bağlantısı bir workspace adı taşımak zorunda.',
                }),
            } as Response);

        render(<ProviderCredentialsPage />);
        await waitFor(() =>
            expect(screen.getByRole('button', { name: '+ Add a connection' })).toBeInTheDocument(),
        );

        await userEvent.click(screen.getByRole('button', { name: '+ Add a connection' }));
        await userEvent.selectOptions(screen.getByLabelText('Provider'), 'openai');
        await userEvent.type(screen.getByLabelText('Connection name'), 'X');
        await userEvent.click(screen.getByRole('button', { name: 'Save connection' }));

        expect(
            await screen.findByText('BYOK bağlantısı bir workspace adı taşımak zorunda.'),
        ).toBeInTheDocument();
    });

    it('shows an error with retry when loading fails', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({}, false));

        render(<ProviderCredentialsPage />);

        await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
        expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
    });
});
