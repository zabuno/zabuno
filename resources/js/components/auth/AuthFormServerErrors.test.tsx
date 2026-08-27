import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { RegisterForm } from './RegisterForm';
import { LoginForm } from './LoginForm';

/**
 * Kimlik formları sunucunun söylediğini gösterir.
 *
 * Dördü de 422 yanıtının GÖVDESİNİ hiç okumadan sabit bir cümle
 * gösteriyordu. Sunucu her seferinde tam olarak neyin yanlış olduğunu
 * söylüyordu — "The email has already been taken" — ve o cümle ağdan geçip
 * çöpe gidiyordu.
 *
 * Bunlar ürünün İLK ekranları. Zayıf parolayla kayıt olmaya çalışan biri
 * "bir şeyler ters gitti" görüp aynı parolayı tekrar deniyordu; ürünle ilk
 * karşılaşma, son karşılaşma oluyordu.
 */

function jsonResponse(status: number, body: unknown): Response {
    return { ok: status >= 200 && status < 300, status, json: async () => body } as Response;
}

function stubFetch(failure: Response) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            if (String(url).includes('csrf-cookie')) return { ok: true, status: 204 } as Response;

            return failure;
        }),
    );
}

beforeEach(() => {
    window.history.pushState({}, '', '/');
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('RegisterForm — the server said which field was wrong', () => {
    it('shows the taken-email message beside the email field instead of a generic retry', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { email: ['The email has already been taken.'] },
            }),
        );

        render(<RegisterForm />);

        fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Tolga' } });
        fireEvent.change(screen.getByLabelText(/^email/i), {
            target: { value: 'taken@example.com' },
        });
        fireEvent.change(screen.getByLabelText(/^password$/i), {
            target: { value: 'sifre-12345' },
        });
        fireEvent.change(screen.getByLabelText(/confirm/i), { target: { value: 'sifre-12345' } });

        fireEvent.click(screen.getByRole('button', { name: /register|create|sign up/i }));

        await waitFor(() => {
            expect(screen.getByText(/already been taken/i)).toBeInTheDocument();
        });
    });

    it('moves focus to the offending field so it does not have to be hunted for', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { email: ['The email has already been taken.'] },
            }),
        );

        render(<RegisterForm />);

        fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Tolga' } });
        fireEvent.change(screen.getByLabelText(/^email/i), {
            target: { value: 'taken@example.com' },
        });
        fireEvent.change(screen.getByLabelText(/^password$/i), {
            target: { value: 'sifre-12345' },
        });
        fireEvent.change(screen.getByLabelText(/confirm/i), { target: { value: 'sifre-12345' } });

        fireEvent.click(screen.getByRole('button', { name: /register|create|sign up/i }));

        await waitFor(() => {
            expect(screen.getByLabelText(/^email/i)).toHaveFocus();
        });
    });

    it('still says something when the failure carries no field detail', async () => {
        stubFetch(jsonResponse(500, {}));

        render(<RegisterForm />);

        fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Tolga' } });
        fireEvent.change(screen.getByLabelText(/^email/i), { target: { value: 'a@example.com' } });
        fireEvent.change(screen.getByLabelText(/^password$/i), {
            target: { value: 'sifre-12345' },
        });
        fireEvent.change(screen.getByLabelText(/confirm/i), { target: { value: 'sifre-12345' } });

        fireEvent.click(screen.getByRole('button', { name: /register|create|sign up/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });
    });
});

describe('LoginForm — the server said why the sign-in failed', () => {
    it('shows the credentials message rather than a generic failure', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'These credentials do not match our records.',
                errors: { email: ['These credentials do not match our records.'] },
            }),
        );

        render(<LoginForm />);

        fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@example.com' } });
        fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'yanlis' } });

        fireEvent.click(screen.getByRole('button', { name: /log in|sign in|login/i }));

        await waitFor(() => {
            expect(screen.getAllByText(/do not match our records/i).length).toBeGreaterThan(0);
        });
    });
});
