import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ProviderCredentialsPage } from './ProviderCredentialsPage';

/**
 * CRED-GUI — superadmin sağlayıcı kasası paneli (Vault Faz 4).
 *
 * Panel sırrı asla değer olarak göstermez (yalnız maske), boş bırakılan
 * sırrı GÖNDERMEZ (öncekini korur), ve yazma isteği CSRF + XSRF ile gider.
 */

function statusList() {
    return [
        {
            provider: 'mailgun',
            configured: true,
            state: 'active',
            lastRotatedAt: '2026-09-03T00:00:00Z',
            fields: [
                { name: 'domain', secret: false, isSet: true, preview: 'sandbox123.mailgun.org' },
                { name: 'secret', secret: true, isSet: true, preview: '••••b1c0' },
                { name: 'endpoint', secret: false, isSet: true, preview: 'api.mailgun.net' },
            ],
        },
        {
            provider: 'openai',
            configured: false,
            state: 'unset',
            lastRotatedAt: null,
            fields: [
                { name: 'api_key', secret: true, isSet: false, preview: null },
                { name: 'base_url', secret: false, isSet: false, preview: null },
                { name: 'organization', secret: false, isSet: false, preview: null },
                { name: 'project', secret: false, isSet: false, preview: null },
            ],
        },
    ];
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

    it('lists every provider with its state and never shows a raw secret', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(statusList()));

        render(<ProviderCredentialsPage />);

        await waitFor(() => expect(screen.getByText('Mailgun (email)')).toBeInTheDocument());
        expect(screen.getByText('OpenAI (ChatGPT)')).toBeInTheDocument();
        expect(screen.getByTestId('state-mailgun')).toHaveTextContent('Active');
        expect(screen.getByTestId('state-openai')).toHaveTextContent('Not set');

        // Sır alanı password tipinde ve mevcut değeri boş (maske ayrı gösterilir).
        const secretInput = screen.getByLabelText(/Secret \/ API key/i) as HTMLInputElement;
        expect(secretInput.type).toBe('password');
        expect(secretInput.value).toBe('');
    });

    it('omits a blank secret on save but sends what the admin typed', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(statusList())) // ilk yükleme
            .mockResolvedValueOnce(jsonResponse({})) // csrf
            .mockResolvedValueOnce(jsonResponse(statusList()[0])) // PUT
            .mockResolvedValueOnce(jsonResponse(statusList())); // yeniden yükleme

        render(<ProviderCredentialsPage />);
        await waitFor(() => expect(screen.getByText('Mailgun (email)')).toBeInTheDocument());

        const mailgunCard = screen.getByText('Mailgun (email)').closest('li')!;
        // domain'i değiştir, secret'ı boş bırak (korunmalı).
        const domainInput = within(mailgunCard).getByLabelText('Domain');
        await userEvent.clear(domainInput);
        await userEvent.type(domainInput, 'new.mailgun.org');

        await userEvent.click(within(mailgunCard).getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved.'));

        // PUT çağrısını bul.
        const putCall = fetchMock.mock.calls.find(
            (call) =>
                String(call[0]).endsWith('/credentials/mailgun') &&
                (call[1] as RequestInit)?.method === 'PUT',
        );
        expect(putCall).toBeTruthy();
        const body = JSON.parse((putCall![1] as RequestInit).body as string);
        expect(body.domain).toBe('new.mailgun.org');
        // Boş sır gönderilmedi.
        expect(body).not.toHaveProperty('secret');
        // XSRF başlığı var.
        const headers = (putCall![1] as RequestInit).headers as Headers;
        expect(headers.get('X-XSRF-TOKEN')).toBe('test-token');
    });

    it('sends the typed secret when the admin enters one', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(statusList()))
            .mockResolvedValueOnce(jsonResponse({}))
            .mockResolvedValueOnce(jsonResponse(statusList()[1]))
            .mockResolvedValueOnce(jsonResponse(statusList()));

        render(<ProviderCredentialsPage />);
        await waitFor(() => expect(screen.getByText('OpenAI (ChatGPT)')).toBeInTheDocument());

        const openaiCard = screen.getByText('OpenAI (ChatGPT)').closest('li')!;
        await userEvent.type(within(openaiCard).getByLabelText('API key'), 'sk-live-xyz');
        await userEvent.click(within(openaiCard).getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved.'));

        const putCall = fetchMock.mock.calls.find(
            (call) =>
                String(call[0]).endsWith('/credentials/openai') &&
                (call[1] as RequestInit)?.method === 'PUT',
        );
        const body = JSON.parse((putCall![1] as RequestInit).body as string);
        expect(body.api_key).toBe('sk-live-xyz');
    });

    it('disables a configured provider', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(jsonResponse(statusList()))
            .mockResolvedValueOnce(jsonResponse({}))
            .mockResolvedValueOnce(
                jsonResponse({ ...statusList()[0], state: 'disabled', configured: false }),
            )
            .mockResolvedValueOnce(jsonResponse(statusList()));

        render(<ProviderCredentialsPage />);
        await waitFor(() => expect(screen.getByText('Mailgun (email)')).toBeInTheDocument());

        const mailgunCard = screen.getByText('Mailgun (email)').closest('li')!;
        await userEvent.click(within(mailgunCard).getByRole('button', { name: 'Disable' }));

        await waitFor(() => {
            const disableCall = fetchMock.mock.calls.find((call) =>
                String(call[0]).endsWith('/credentials/mailgun/disable'),
            );
            expect(disableCall).toBeTruthy();
        });
    });

    it('shows an error with retry when loading fails', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({}, false));

        render(<ProviderCredentialsPage />);

        await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
        expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
    });
});
