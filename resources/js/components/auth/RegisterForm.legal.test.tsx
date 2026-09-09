import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RegisterForm } from './RegisterForm';
import type { RegisterLegalPayload } from './LegalDocumentModal';

/**
 * REG-LEGAL-UI-01…09 — kayıt ekranı, imzalanan metni GÖSTERİR (FF-198 devamı).
 *
 * ÖLÇÜLDÜ: kayıt formu yalnız "Hizmet Koşulları'nı okuyun" diyen bir
 * bağlantı satırı taşıyordu. Kebapçı, kabul ettiği metni görmek için
 * formu terk etmek zorundaydı; geri döndüğünde yazdıkları gitmişti.
 *
 * Ve daha ağırı: aynı kutu hem Hizmet Koşulları'nı KABUL ediyor hem
 * Gizlilik Politikası'na ONAY veriyordu. KVKK Kurulu'nun 18.02.2026
 * tarihli 2026/347 sayılı ilke kararı aydınlatmanın onay olarak
 * istenemeyeceğini söylüyor: aydınlatma OKUNUR ve bilgilenildiği
 * beyan edilir, onaylanmaz. Bu yüzden gizlilik metni artık ayrı bir
 * BEYAN kutusudur, ticari ileti izni ise ayrı ve isteğe bağlıdır.
 *
 * SON DÜZELTME: kutuya basmak tek başına beyan sayılmıyordu artık —
 * metni AÇIYOR ve kutu boş kalıyor. İşaret ancak metnin sonundaki
 * düğmeyle konuyor; formdan çıkan çıplak belge bağlantıları kaldırıldı.
 */

const LEGAL: RegisterLegalPayload = {
    reviewPending: true,
    documents: {
        terms: {
            key: 'terms',
            url: '/terms',
            title: 'Terms of Service',
            summary: 'The rules of using Zabuno.',
            version: '0.4',
            effectiveDate: '2026-09-01',
            language: 'en',
            sections: [
                { heading: 'Who we are', paragraphs: ['Zabuno is operated by Kebapci Ltd.'] },
                { heading: 'Your account', paragraphs: ['You keep your password to yourself.'] },
            ],
        },
        privacy: {
            key: 'privacy',
            url: '/privacy',
            title: 'Privacy Policy',
            summary: 'What personal data Zabuno collects.',
            version: '0.3',
            effectiveDate: '2026-09-09',
            language: 'en',
            sections: [
                { heading: 'Who is responsible', paragraphs: ['The controller is Kebapci Ltd.'] },
            ],
        },
        'marketing-consent': {
            key: 'marketing-consent',
            url: '/marketing-consent',
            title: 'Commercial message consent text',
            summary: 'What we send and how you stop it.',
            version: '0.1',
            effectiveDate: '2026-09-01',
            language: 'en',
            sections: [{ heading: 'What we send', paragraphs: ['Occasional product e-mails.'] }],
        },
    },
};

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

/** Metni açar, sonuna kadar iner ve oradaki tek olumlu düğmeye basar. */
async function acceptThroughDocument(user: ReturnType<typeof userEvent.setup>, name: RegExp) {
    fireEvent.click(screen.getByRole('checkbox', { name }));

    const dialog = await screen.findByRole('dialog');

    await user.click(within(within(dialog).getByTestId('legal-document-action')).getByRole('button'));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
}

beforeEach(() => {
    window.history.pushState({}, '', '/register');
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('RegisterForm — legal document cards', () => {
    it('REG-LEGAL-UI-01: each document is a card with its own box and a control that opens the full text', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        expect(
            screen.getByRole('button', { name: /read the terms of service in full/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /read the privacy policy in full/i }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: /read the commercial message consent text in full/i,
            }),
        ).toBeInTheDocument();
    });

    it('REG-LEGAL-UI-02: no standalone document link leaves the form', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        // Çıplak bağlantı, yazılanları kaybettiren tek çıkıştı: kaldırıldı.
        // Belgelerin kalıcı rotaları duruyor, yalnız formdan buraya
        // bağlanmıyorlar.
        expect(screen.queryAllByRole('link')).toHaveLength(0);
    });

    it('REG-LEGAL-UI-03: the dialog shows the whole text, its version, its language and the pending-review warning', async () => {
        const user = userEvent.setup();
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        await user.click(
            screen.getByRole('button', { name: /read the terms of service in full/i }),
        );

        const dialog = await screen.findByRole('dialog');

        expect(dialog).toHaveAccessibleName('Terms of Service');
        expect(
            within(dialog).getByText(/zabuno is operated by kebapci ltd\./i),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(/you keep your password to yourself\./i),
        ).toBeInTheDocument();
        expect(within(dialog).getByText(/0\.4/)).toBeInTheDocument();
        expect(within(dialog).getByText(/not yet been reviewed/i)).toBeInTheDocument();
        expect(within(dialog).getByTestId('legal-document-text')).toHaveAttribute('lang', 'en');
    });

    it('REG-LEGAL-UI-04: the card carries no version line — the identity of the text lives where the text is read', async () => {
        const user = userEvent.setup();
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        expect(screen.queryByText(/in force from/i)).not.toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: /read the terms of service in full/i }),
        );

        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).getByText(/in force from/i)).toBeInTheDocument();
    });

    it('REG-LEGAL-UI-05: ticking an empty box opens the text instead of accepting it', async () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        const box = screen.getByRole('checkbox', { name: /accept the terms of service/i });

        fireEvent.click(box);

        expect(await screen.findByRole('dialog')).toHaveAccessibleName('Terms of Service');
        // Metin açıldı, beyan VERİLMEDİ: tek dokunuş okunmamış bir belgeyi
        // kabul etmez.
        expect(box).not.toBeChecked();
    });

    it('REG-LEGAL-UI-06: the affirmative action after the text accepts, and it sits inside the scrolling region', async () => {
        const user = userEvent.setup();
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        fireEvent.click(screen.getByRole('checkbox', { name: /accept the terms of service/i }));

        const dialog = await screen.findByRole('dialog');
        const scroller = within(dialog).getByTestId('legal-document-scroll');
        const text = within(dialog).getByTestId('legal-document-text');
        const action = within(dialog).getByTestId('legal-document-action');

        expect(scroller).toContainElement(text);
        expect(scroller).toContainElement(action);
        expect(
            text.compareDocumentPosition(action) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        await user.click(within(action).getByRole('button'));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(
            screen.getByRole('checkbox', { name: /accept the terms of service/i }),
        ).toBeChecked();
    });

    it('REG-LEGAL-UI-07: closing, Escape and scrolling to the end never accept; unticking is immediate', async () => {
        const user = userEvent.setup();
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        fireEvent.click(screen.getByRole('checkbox', { name: /accept the terms of service/i }));

        const dialog = await screen.findByRole('dialog');

        // Sona kaydırmak KABUL DEĞİLDİR: okuduğunu kanıtlamaz.
        fireEvent.scroll(within(dialog).getByTestId('legal-document-scroll'), {
            target: { scrollTop: 9999 },
        });
        await user.keyboard('{Escape}');

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(
            screen.getByRole('checkbox', { name: /accept the terms of service/i }),
        ).not.toBeChecked();

        // Verilmiş bir beyanı geri almak için metni tekrar açmak gerekmez.
        await acceptThroughDocument(user, /accept the terms of service/i);
        fireEvent.click(screen.getByRole('checkbox', { name: /accept the terms of service/i }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: /accept the terms of service/i }),
        ).not.toBeChecked();
    });

    /**
     * REG-LEGAL-UI-11 — EYLEMİN ADI KISALDI, KUTUNUN METNİ KISALMADI.
     *
     * ÖLÇÜLDÜ (gerçek tarayıcı, 320×480): ticari ileti penceresinin olumlu
     * düğmesi kutu etiketinin tamamını taşıyordu ve İngilizce dört, Türkçe
     * BEŞ satıra sarıyordu — 228×122 piksel, yani 480 piksellik ekranın
     * dörtte biri. Kutunun etiketi iki cümledir ve ikincisi ("İsteğe
     * bağlıdır") seçimin bir NİTELİĞİDİR, eylemin adı değil.
     *
     * Kısalan yalnız düğmenin adı: kutunun tam metni ve isteğe bağlı notu
     * yerinde kalır, düğme neyin kabul edildiğini (ticari e-posta) açıkça
     * söylemeye devam eder, ve kutuyu TRUE yapan tek yol hâlâ o düğmedir.
     */
    it('REG-LEGAL-UI-11: the marketing action is named for the act, while the box keeps its full text', async () => {
        const user = userEvent.setup();
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        const box = screen.getByRole('checkbox', { name: /commercial messages/i });
        const boxLabel = screen.getByText(/i agree to receive electronic commercial messages/i);

        // Kutunun tam metni ve isteğe bağlı notu YERİNDE.
        expect(boxLabel).toHaveTextContent(/this is optional\./i);
        expect(screen.getByText(/leaving this box empty changes nothing else/i)).toBeInTheDocument();

        fireEvent.click(box);

        const dialog = await screen.findByRole('dialog');
        const action = within(dialog).getByTestId('legal-document-action');
        const button = within(action).getByRole('button');
        const actionName = button.textContent ?? '';

        // Ad KISA ama neyin kabul edildiği hâlâ açık.
        expect(actionName).toMatch(/commercial e-mail/i);
        expect(actionName.length).toBeLessThan((boxLabel.textContent ?? '').length);
        expect(actionName).not.toMatch(/this is optional/i);

        // TEK OLUMLU YOL hâlâ bu düğme.
        expect(box).not.toBeChecked();
        await user.click(button);

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(screen.getByRole('checkbox', { name: /commercial messages/i })).toBeChecked();
    });

    it('REG-LEGAL-UI-08: the privacy box is worded as a reading acknowledgement, not as consent', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} />);

        const privacy = screen.getByRole('checkbox', {
            name: /read and understood the privacy policy/i,
        });

        expect(privacy).not.toBeChecked();
        expect(screen.getByText(/it is not consent/i)).toBeInTheDocument();
    });

    it('REG-LEGAL-UI-09: a document that cannot be shown cannot be acknowledged either', () => {
        stubFetch(jsonResponse(200, {}));
        render(<RegisterForm />);

        expect(
            screen.getByRole('checkbox', { name: /accept the terms of service/i }),
        ).toBeDisabled();
        expect(screen.getAllByText(/cannot be shown right now/i).length).toBeGreaterThan(0);
        expect(screen.queryAllByRole('link')).toHaveLength(0);
    });

    it('REG-LEGAL-UI-10: registration is refused until the privacy text is acknowledged, and the answer travels separately', async () => {
        const user = userEvent.setup();
        const calls = stubFetch(jsonResponse(200, {}));
        render(<RegisterForm legal={LEGAL} navigate={() => {}} />);
        fillIdentity();

        await acceptThroughDocument(user, /accept the terms of service/i);
        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() =>
            expect(
                screen.getByText(/confirm that you have read the privacy policy/i),
            ).toBeInTheDocument(),
        );
        expect(calls).toHaveLength(0);

        await acceptThroughDocument(user, /read and understood the privacy policy/i);
        fireEvent.click(screen.getByRole('button', { name: /^register$/i }));

        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0].body).toMatchObject({
            terms_accepted: true,
            privacy_acknowledged: true,
            marketing_consent: false,
        });
    });
});
