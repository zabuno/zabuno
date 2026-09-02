import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { AccountSettingsRegion } from './AccountSettingsRegion';

/**
 * HESAP BAKIMI — `docs/83` (P1-07).
 *
 * Self-service bir üründe kullanıcı kendi hesabını kendi onarır. Yanlış
 * yazılmış bir ad ya da paylaşılmış bir şifre için destek talebi açmak
 * zorunda kalmak, ürünün "kendi kendine yeter" iddiasını her gün çürütür.
 */
function jsonResponse(status: number): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => ({}),
    } as Response;
}

function mount(status = 200) {
    const calls: { url: string; method: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({
                url: String(url),
                method,
                body: init?.body ? JSON.parse(String(init.body)) : null,
            });

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204);

            return jsonResponse(status);
        }),
    );

    render(<AccountSettingsRegion currentName="Ismail" />);

    return { calls, user: userEvent.setup() };
}

describe('hesap bakımı (docs/83)', () => {
    it('kullanıcı kendi adını düzeltir', async () => {
        const { calls, user } = mount();

        const field = screen.getByLabelText('Your name');
        await user.clear(field);
        await user.type(field, 'İsmail Karaca');
        await user.click(screen.getByRole('button', { name: 'Save name' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url === '/api/user/profile')).toBe(true);
        });

        const put = calls.find((call) => call.url === '/api/user/profile')!;
        expect(put.method).toBe('PUT');
        expect(put.body).toEqual({ name: 'İsmail Karaca' });
        expect(await screen.findByText('Your name was saved.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('şifre değişikliği mevcut şifreyi de gönderir ve alanları temizler', async () => {
        const { calls, user } = mount();

        await user.type(screen.getByLabelText('Current password'), 'eski-parola-123');
        await user.type(screen.getByLabelText('New password'), 'yeni-parola-456');
        await user.type(screen.getByLabelText('Repeat new password'), 'yeni-parola-456');
        await user.click(screen.getByRole('button', { name: 'Change password' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url === '/api/user/password')).toBe(true);
        });

        expect(calls.find((call) => call.url === '/api/user/password')!.body).toEqual({
            currentPassword: 'eski-parola-123',
            password: 'yeni-parola-456',
            password_confirmation: 'yeni-parola-456',
        });

        // Ekranda duran bir şifre, omuz üstünden okunabilecek bir şifredir.
        await waitFor(() => {
            expect(screen.getByLabelText('Current password')).toHaveValue('');
        });
        expect(screen.getByLabelText('New password')).toHaveValue('');

        vi.unstubAllGlobals();
    });

    it('diğer oturumların kapanacağı önceden söylenir', () => {
        mount();

        // Sürpriz bir çıkış, kullanıcıya ürünün bozulduğunu düşündürür.
        expect(
            screen.getByText(
                'Changing your password signs you out everywhere else. This device stays signed in.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('başarısızlık sessiz kalmaz', async () => {
        const { user } = mount(422);

        await user.type(screen.getByLabelText('Current password'), 'yanlis');
        await user.type(screen.getByLabelText('New password'), 'yeni-parola-456');
        await user.type(screen.getByLabelText('Repeat new password'), 'yeni-parola-456');
        await user.click(screen.getByRole('button', { name: 'Change password' }));

        expect(
            await screen.findByText(
                'Your password could not be changed. Check your current password and try again.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
