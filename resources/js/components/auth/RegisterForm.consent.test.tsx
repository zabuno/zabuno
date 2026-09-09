import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { RegisterForm } from './RegisterForm';
import {
    REGISTER_LEGAL as LEGAL,
    acceptLegalDocument as acceptThroughDocument,
    acceptMandatoryLegalDocuments as acceptMandatoryDocuments,
} from './registerLegal.fixture';

/**
 * REG-CONSENT-UI-01…04 — kayıt formu, neyi kabul ettiğini SÖYLER (FF-198).
 *
 * Kebapçı kaydolurken bir onay kutusu görür: "Hizmet Koşulları'nı okudum ve
 * kabul ediyorum." Kutu boşken form GÖNDERİLMEZ ve sunucuya hiç gitmez;
 * ticari ileti izni isteğe bağlıdır ve varsayılanı BOŞTUR — sessizlik onay
 * değildir.
 *
 * REG-LEGAL-01'de aydınlatma metni bu kutudan AYRILDI ve kendi beyan
 * kutusuna taşındı (`RegisterForm.legal.test.tsx`); bu dosyadaki senaryolar
 * artık o beyanı da veriyor, çünkü onsuz form zaten sunucuya çıkmaz.
 *
 * Kutular metnin kendisi olmadan işaretlenemediği için buradaki senaryolar
 * da belgeleri sunucudan gelmiş gibi taşır ve beyanı metnin sonundaki
 * düğmeden verir — gerçek yolculuğun aynısı.
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
        render(<RegisterForm legal={LEGAL} />);

        const terms = screen.getByRole('checkbox', { name: /terms of service/i });
        const marketing = screen.getByRole('checkbox', { name: /commercial messages/i });

        expect(terms).not.toBeChecked();
        expect(marketing).not.toBeChecked();
        expect(terms).toBeRequired();
        expect(marketing).not.toBeRequired();
    });

    it('REG-CONSENT-UI-02: the text of each document is read inside the form, not behind a link out of it', async () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        expect(screen.queryAllByRole('link')).toHaveLength(0);

        fireEvent.click(screen.getByRole('checkbox', { name: /accept the terms of service/i }));

        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).getByText(/the body of the terms of service\./i)).toBeInTheDocument();
    });

    it('REG-CONSENT-UI-03: refuses to submit while the terms box is empty and never calls the server', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);
        fillIdentity();

        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() => {
            expect(screen.getByText(/tick this box to create an account/i)).toBeInTheDocument();
        });
        expect(calls).toHaveLength(0);
    });

    /**
     * REG-CONSENT-UI-05 — HATAYI GÖSTERMEK YETMEZ, ONU BULDURMAK GEREKİR.
     *
     * Sunucu reddettiğinde odak zaten ilk hatalı alana taşınıyordu; İSTEMCİ
     * reddettiğinde taşınmıyordu ve fark 320×480'de ölçülebilir bir kusurdu:
     * boş formu gönderen kişi ekranın ALTINDAKİ düğmededir, hata metinleri
     * katlanmanın üstünde kalır, sayfa kıpırdamaz — kullanıcıya hiçbir şey
     * olmamış gibi görünür ve aynı düğmeye tekrar basar.
     *
     * Odak SIRAYA göre taşınır: yasal kutular formun en altındadır, yani boş
     * bir formda ilk hatalı alan addır, kutu değil. Sunucuya hâlâ hiç
     * gidilmez — gerçek bir hesap açılmaz.
     */
    it('REG-CONSENT-UI-05: a client-side rejection moves focus to the first invalid field', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        const submit = screen.getByRole('button', { name: /^register$/i });
        submit.focus();
        expect(document.activeElement).toBe(submit);

        fireEvent.click(submit);

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getByLabelText(/^name/i));
        });
        expect(calls).toHaveLength(0);

        // Ad doldurulduğunda sıradaki hatalı alan e-postadır.
        fireEvent.change(screen.getByLabelText(/^name/i), { target: { value: 'Tolga' } });
        submit.focus();
        fireEvent.click(submit);

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getByLabelText(/^email/i));
        });
        expect(calls).toHaveLength(0);
    });

    it('REG-CONSENT-UI-04: sends both answers to the server, marketing false unless ticked', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} navigate={() => {}} />);
        fillIdentity();

        await acceptMandatoryDocuments();
        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() => expect(calls).toHaveLength(1));

        expect(calls[0].body).toMatchObject({
            terms_accepted: true,
            privacy_acknowledged: true,
            marketing_consent: false,
        });
    });

    it('REG-CONSENT-UI-04b: a ticked marketing box travels as true', async () => {
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} navigate={() => {}} />);
        fillIdentity();

        await acceptMandatoryDocuments();
        await acceptThroughDocument(/commercial messages/i);
        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() => expect(calls).toHaveLength(1));

        expect(calls[0].body).toMatchObject({
            terms_accepted: true,
            privacy_acknowledged: true,
            marketing_consent: true,
        });
    });

    it('shows the server-side terms error beside the box when the server says so', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { terms_accepted: ['The terms accepted field must be accepted.'] },
            }),
        );
        render(<RegisterForm legal={LEGAL} />);
        fillIdentity();
        await acceptMandatoryDocuments();
        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() => {
            expect(screen.getByText(/must be accepted/i)).toBeInTheDocument();
        });
    });
});
