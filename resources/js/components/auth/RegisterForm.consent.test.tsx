import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { RegisterForm } from './RegisterForm';

/**
 * REG-CONSENT-UI-01…04 — kayıt formu, neyi kabul ettiğini SÖYLER (FF-198).
 *
 * Kebapçı kaydolurken bir onay kutusu görür: "Hizmet Koşulları'nı ve
 * Gizlilik Politikası'nı okudum ve kabul ediyorum." Kutu boşken form
 * GÖNDERİLMEZ ve sunucuya hiç gitmez; ikinci kutu (ticari ileti izni)
 * isteğe bağlıdır ve varsayılanı BOŞTUR — sessizlik onay değildir.
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function stubFetch(final: Response) {
    const calls: { url: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            if (String(url).includes('csrf-cookie')) return { ok: true, status: 204 } as Response;

            calls.push({ url: String(url), body: JSON.parse(String(init?.body ?? 'null')) });

            return final;
        }),
    );

    return calls;
}

function fillIdentity() {
    fireEvent.change(screen.getByLabelText(/^name/i), { target: { value: 'Tolga' } });
    fireEvent.change(screen.getByLabelText(/^email/i), { target: { value: 'tolga@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'sifre-12345' } });
    fireEvent.change(screen.getByLabelText(/confirm/i), { target: { value: 'sifre-12345' } });
}

beforeEach(() => {
    window.history.pushState({}, '', '/register');
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('RegisterForm — consent', () => {
    it('REG-CONSENT-UI-01: shows a required terms box and an optional marketing box, both unticked', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm />);

        const terms = screen.getByRole('checkbox', { name: /terms of service/i });
        const marketing = screen.getByRole('checkbox', { name: /commercial messages/i });

        expect(terms).not.toBeChecked();
        expect(marketing).not.toBeChecked();
        expect(terms).toBeRequired();
        expect(marketing).not.toBeRequired();
    });

    it('REG-CONSENT-UI-02: links to the documents the person is asked to accept', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm />);

        expect(screen.getByRole('link', { name: /terms of service/i })).toHaveAttribute(
            'href',
            '/terms',
        );
        expect(screen.getByRole('link', { name: /privacy policy/i })).toHaveAttribute(
            'href',
            '/privacy',
        );
        expect(screen.getByRole('link', { name: /commercial message/i })).toHaveAttribute(
            'href',
            '/marketing-consent',
        );
    });

    it('REG-CONSENT-UI-03: refuses to submit while the terms box is empty and never calls the server', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm />);
        fillIdentity();

        fireEvent.click(screen.getByRole('button', { name: /register/i }));

        await waitFor(() => {
            expect(screen.getByText(/tick this box to create an account/i)).toBeInTheDocument();
        });
        expect(calls).toHaveLength(0);
    });

    it('REG-CONSENT-UI-04: sends both answers to the server, marketing false unless ticked', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm navigate={() => {}} />);
        fillIdentity();

        fireEvent.click(screen.getByRole('checkbox', { name: /terms of service/i }));
        fireEvent.click(screen.getByRole('button', { name: /register/i }));

        await waitFor(() => expect(calls).toHaveLength(1));

        expect(calls[0].body).toMatchObject({ terms_accepted: true, marketing_consent: false });
    });

    it('REG-CONSENT-UI-04b: a ticked marketing box travels as true', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm navigate={() => {}} />);
        fillIdentity();

        fireEvent.click(screen.getByRole('checkbox', { name: /terms of service/i }));
        fireEvent.click(screen.getByRole('checkbox', { name: /commercial messages/i }));
        fireEvent.click(screen.getByRole('button', { name: /register/i }));

        await waitFor(() => expect(calls).toHaveLength(1));

        expect(calls[0].body).toMatchObject({ terms_accepted: true, marketing_consent: true });
    });

    it('shows the server-side terms error beside the box when the server says so', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { terms_accepted: ['The terms accepted field must be accepted.'] },
            }),
        );
        render(<RegisterForm />);
        fillIdentity();
        fireEvent.click(screen.getByRole('checkbox', { name: /terms of service/i }));
        fireEvent.click(screen.getByRole('button', { name: /register/i }));

        await waitFor(() => {
            expect(screen.getByText(/must be accepted/i)).toBeInTheDocument();
        });
    });
});
