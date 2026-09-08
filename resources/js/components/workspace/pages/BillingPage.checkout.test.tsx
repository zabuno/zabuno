import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BillingPage } from './BillingPage';

/**
 * SELF-SERVE-FRONTEND — kiracının plan seçip abone olduğu panel yolu
 * (docs/107 Faz 1.1 + 1.3, docs/123).
 *
 * Dondurulan sözleşme:
 *  - Bölge yalnız SATIN ALINABİLİR planları (fiyatı olan) seçtirir; kart
 *    alanı asla çizmez — kart Iyzico'nun sayfasına girilir.
 *  - Sandbox kipinde "test mode" cümlesi okunur.
 *  - Fatura profili eksikse bunu ADIYLA söyler ve formu açan düğme sunar;
 *    "Proceed to payment" o sırada devre dışıdır ve hiçbir POST atılmaz.
 *  - Proceed: CSRF önyüklemesi → POST {plan_id, idempotency_key} (başka
 *    alan YOK, tutar yok) → 202'deki https adrese yönlendirme.
 *  - Son ödeme başarısızsa nedeni ekranda; yol yeniden açık.
 *  - Sunucu 422 ile profil eksik derse aynı cümle ve form; genel hata değil.
 *  - Profil formu sekiz alanı PUT eder; kayıt sonrası Proceed açılır.
 */
const WORKSPACE_ID = 34;
const PLANS = `/api/workspaces/${WORKSPACE_ID}/plans`;
const SUBSCRIPTION = `/api/workspaces/${WORKSPACE_ID}/subscription`;
const CHECKOUT = `/api/workspaces/${WORKSPACE_ID}/checkout`;
const PROFILE = `/api/workspaces/${WORKSPACE_ID}/billing-profile`;
const CSRF = '/sanctum/csrf-cookie';
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function plans() {
    return [
        {
            id: 11,
            name: 'Pro',
            code: 'pro',
            entitlements: ['Unlimited menus'],
            amount_minor: 149900,
            currency: 'TRY',
        },
        {
            id: 12,
            name: 'Free',
            code: 'free',
            entitlements: [],
            amount_minor: null,
            currency: null,
        },
    ];
}

function checkoutStatus(overrides: Record<string, unknown> = {}) {
    return {
        mode: 'sandbox',
        period_days: 30,
        profile_complete: false,
        latest: null,
        ...overrides,
    };
}

function completeProfile() {
    return {
        state: 'complete',
        legal_name: 'Kadıköy Kebap Ltd.',
        tax_number: '1234567890',
        tax_office: 'Kadıköy',
        address: 'Moda Cad. 1',
        city: 'Istanbul',
        country: 'TR',
        email: 'muhasebe@kadikoykebap.test',
        phone: '+905551112233',
    };
}

function initiatedTransaction() {
    return {
        id: 5,
        state: 'initiated',
        mode: 'sandbox',
        plan_id: 11,
        conversation_id: 'conv-5',
        amount_minor: 149900,
        currency: 'TRY',
        period_days: 30,
        redirect_url: 'https://sandbox-cf.iyzipay.com/checkout/x',
        failure_reason: null,
        refund_reason: null,
        refunded_at: null,
        created_at: '2026-09-06T10:00:00Z',
    };
}

type Handler = (init?: RequestInit) => Promise<Response>;

function routes(map: Record<string, Handler>) {
    return async (url: string, init?: RequestInit) => {
        const handler = map[String(url)];
        if (handler) return handler(init);
        throw new Error(`Unhandled fetch: ${String(url)}`);
    };
}

describe('BillingPage — self-serve subscribe (SELF-SERVE-FRONTEND)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token; path=/';
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in BillingPage checkout test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });

    /**
     * İKİ ONAY KUTUSU (FF-216) — hiçbiri önceden işaretli DEĞİL.
     *
     * Ödeme yolunu ölçen her testin bunları işaretlemesi gerekir; bu, ürünün
     * gerçek yolunu ölçmek demektir: FF-216'dan önce ödeme adımında hiç onay
     * yoktu ve testler de bu yüzden onaysız POST atıyordu.
     */
    async function acceptConsents(user: ReturnType<typeof userEvent.setup>) {
        const boxes = within(region()).getAllByRole('checkbox');

        for (const box of boxes) {
            expect(box).not.toBeChecked();
            await user.click(box);
        }

        expect(boxes).toHaveLength(2);
    }

    function region() {
        return screen.getByRole('region', { name: /^subscribe$/i });
    }

    function baseRoutes(overrides: Record<string, Handler> = {}) {
        return routes({
            [PLANS]: async () => jsonResponse(200, plans()),
            [SUBSCRIPTION]: async () => jsonResponse(200, { state: 'none' }),
            [CHECKOUT]: async () => jsonResponse(200, checkoutStatus()),
            [PROFILE]: async () => jsonResponse(200, { state: 'missing' }),
            ...overrides,
        });
    }

    it('lists only purchasable plans, says test mode, names the missing billing details, and keeps Proceed disabled', async () => {
        fetchSpy.mockImplementation(baseRoutes());

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(region()).getByRole('radio', { name: /pro/i })).toBeInTheDocument();
        });
        expect(within(region()).queryByRole('radio', { name: /free/i })).not.toBeInTheDocument();
        expect(within(region()).getByText(/test mode/i)).toBeInTheDocument();
        expect(within(region()).getByText(/billing details are missing/i)).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: /add billing details/i }),
        ).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: /proceed to payment/i }),
        ).toBeDisabled();
        expect(
            within(region()).queryByLabelText(/card number|cvv|cvc|expiry/i),
        ).not.toBeInTheDocument();
        expect(
            fetchSpy.mock.calls.some(
                ([url, init]) =>
                    String(url) === CHECKOUT && (init as RequestInit)?.method === 'POST',
            ),
        ).toBe(false);
    });

    it('with a complete profile and a chosen plan, Proceed bootstraps CSRF, POSTs plan_id and idempotency_key only, and navigates to the https payment page', async () => {
        const navigate = vi.fn();
        let posted: RequestInit | undefined;

        fetchSpy.mockImplementation(
            baseRoutes({
                [PROFILE]: async () => jsonResponse(200, completeProfile()),
                [CSRF]: async () => jsonResponse(204, null),
                [CHECKOUT]: async (init) => {
                    if (init?.method === 'POST') {
                        posted = init;
                        return jsonResponse(202, initiatedTransaction());
                    }
                    return jsonResponse(200, checkoutStatus({ profile_complete: true }));
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} navigateToPayment={navigate} />);
        const user = userEvent.setup();

        await user.click(await within(region()).findByRole('radio', { name: /pro/i }));
        await waitFor(() => {
            expect(within(region()).getByText('Kadıköy Kebap Ltd.')).toBeInTheDocument();
        });

        await acceptConsents(user);

        const proceed = within(region()).getByRole('button', { name: /proceed to payment/i });
        await waitFor(() => expect(proceed).toBeEnabled());
        await user.click(proceed);

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('https://sandbox-cf.iyzipay.com/checkout/x');
        });

        const csrfIndex = fetchSpy.mock.calls.findIndex(([url]) => String(url) === CSRF);
        const postIndex = fetchSpy.mock.calls.findIndex(
            ([url, init]) => String(url) === CHECKOUT && (init as RequestInit)?.method === 'POST',
        );
        expect(csrfIndex).toBeGreaterThan(-1);
        expect(csrfIndex).toBeLessThan(postIndex);

        const body = JSON.parse(String(posted?.body)) as Record<string, unknown>;
        expect(Object.keys(body).sort()).toEqual([
            'agreements_accepted',
            'idempotency_key',
            'immediate_performance_accepted',
            'plan_id',
        ]);
        expect(body.agreements_accepted).toBe(true);
        expect(body.immediate_performance_accepted).toBe(true);
        expect(body.plan_id).toBe(11);
        expect(String(body.idempotency_key)).toMatch(UUID_RE);
        expect(posted?.credentials).toBe('include');
        const headers = new Headers(posted?.headers);
        expect(headers.get('X-XSRF-TOKEN')).toBe('test-xsrf-token');
        expect(headers.get('Content-Type')).toMatch(/application\/json/);
    });

    it('shows the last failed payment with its reason and leaves the way open to try again', async () => {
        fetchSpy.mockImplementation(
            baseRoutes({
                [PROFILE]: async () => jsonResponse(200, completeProfile()),
                [CHECKOUT]: async () =>
                    jsonResponse(
                        200,
                        checkoutStatus({
                            profile_complete: true,
                            latest: {
                                ...initiatedTransaction(),
                                state: 'failed',
                                failure_reason: 'Insufficient funds',
                            },
                        }),
                    ),
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);
        const user = userEvent.setup();

        await waitFor(() => {
            expect(within(region()).getByRole('alert')).toHaveTextContent(/insufficient funds/i);
        });
        expect(within(region()).getByText(/try again/i)).toBeInTheDocument();

        await user.click(within(region()).getByRole('radio', { name: /pro/i }));
        await waitFor(() => {
            expect(
                within(region()).getByRole('button', { name: /proceed to payment/i }),
            ).toBeEnabled();
        });
    });

    it('a 422 naming the missing billing details opens the form instead of a generic error', async () => {
        fetchSpy.mockImplementation(
            baseRoutes({
                [PROFILE]: async () => jsonResponse(200, completeProfile()),
                [CSRF]: async () => jsonResponse(204, null),
                [CHECKOUT]: async (init) => {
                    if (init?.method === 'POST') {
                        return jsonResponse(422, {
                            message: 'Billing details are missing.',
                            reason: 'billing_profile_missing',
                        });
                    }
                    return jsonResponse(200, checkoutStatus({ profile_complete: true }));
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} navigateToPayment={vi.fn()} />);
        const user = userEvent.setup();

        await user.click(await within(region()).findByRole('radio', { name: /pro/i }));
        await acceptConsents(user);
        const proceed = within(region()).getByRole('button', { name: /proceed to payment/i });
        await waitFor(() => expect(proceed).toBeEnabled());
        await user.click(proceed);

        await waitFor(() => {
            expect(within(region()).getByRole('alert')).toHaveTextContent(
                /billing details are missing/i,
            );
        });
        expect(within(region()).getByLabelText(/company name/i)).toBeInTheDocument();
    });

    it('saving billing details PUTs the eight fields and unlocks Proceed', async () => {
        let put: RequestInit | undefined;

        fetchSpy.mockImplementation(
            baseRoutes({
                [CSRF]: async () => jsonResponse(204, null),
                [PROFILE]: async (init) => {
                    if (init?.method === 'PUT') {
                        put = init;
                        return jsonResponse(200, completeProfile());
                    }
                    return jsonResponse(200, { state: 'missing' });
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);
        const user = userEvent.setup();

        await user.click(
            await within(region()).findByRole('button', { name: /add billing details/i }),
        );

        const fill = async (label: RegExp, value: string) => {
            await user.type(within(region()).getByLabelText(label), value);
        };
        await fill(/company name/i, 'Kadıköy Kebap Ltd.');
        await fill(/tax number/i, '1234567890');
        await fill(/tax office/i, 'Kadıköy');
        await fill(/^address/i, 'Moda Cad. 1');
        await fill(/^city/i, 'Istanbul');
        await fill(/country code/i, 'TR');
        await fill(/billing email/i, 'muhasebe@kadikoykebap.test');
        await fill(/^phone/i, '+905551112233');

        await user.click(within(region()).getByRole('button', { name: /save billing details/i }));

        await waitFor(() => {
            expect(within(region()).getByText('Kadıköy Kebap Ltd.')).toBeInTheDocument();
        });

        const body = JSON.parse(String(put?.body)) as Record<string, unknown>;
        expect(body).toEqual({
            legal_name: 'Kadıköy Kebap Ltd.',
            tax_number: '1234567890',
            tax_office: 'Kadıköy',
            address: 'Moda Cad. 1',
            city: 'Istanbul',
            country: 'TR',
            email: 'muhasebe@kadikoykebap.test',
            phone: '+905551112233',
        });
        expect(within(region()).queryByLabelText(/company name/i)).not.toBeInTheDocument();

        await user.click(within(region()).getByRole('radio', { name: /pro/i }));
        await waitFor(() => {
            expect(
                within(region()).getByRole('button', { name: /proceed to payment/i }),
            ).toBeEnabled();
        });
    });
    // --- SELF-SERVE-CONSENT-FRONTEND (FF-216) ----------------------------

    it('refuses to POST while a consent box is empty and says which one', async () => {
        let posted = false;

        fetchSpy.mockImplementation(
            baseRoutes({
                [PROFILE]: async () => jsonResponse(200, completeProfile()),
                [CSRF]: async () => jsonResponse(204, null),
                [CHECKOUT]: async (init) => {
                    if (init?.method === 'POST') {
                        posted = true;
                    }
                    return jsonResponse(200, checkoutStatus({ profile_complete: true }));
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} navigateToPayment={vi.fn()} />);
        const user = userEvent.setup();

        await user.click(await within(region()).findByRole('radio', { name: /pro/i }));

        // İki kutu, ikisi de KAPALI: önceden işaretli kutu onay değildir.
        const boxes = within(region()).getAllByRole('checkbox');
        expect(boxes).toHaveLength(2);
        boxes.forEach((box) => expect(box).not.toBeChecked());

        const proceed = within(region()).getByRole('button', { name: /proceed to payment/i });
        await waitFor(() => expect(proceed).toBeEnabled());
        await user.click(proceed);

        await waitFor(() => {
            expect(
                within(region()).getByText(
                    /^to continue, accept the preliminary information form/i,
                ),
            ).toBeInTheDocument();
        });
        expect(
            within(region()).getByText(/^to continue, confirm that the service should start/i),
        ).toBeInTheDocument();
        expect(posted).toBe(false);

        // Yalnız BİRİNİ işaretlemek de yetmez, ve düzelen alanın hatası kalkar.
        await user.click(boxes[0]);
        await user.click(proceed);

        await waitFor(() => {
            expect(
                within(region()).queryByText(
                    /^to continue, accept the preliminary information form/i,
                ),
            ).not.toBeInTheDocument();
        });
        expect(posted).toBe(false);
    });

    it('names the seller identity gap instead of showing a generic failure', async () => {
        fetchSpy.mockImplementation(
            baseRoutes({
                [PROFILE]: async () => jsonResponse(200, completeProfile()),
                [CSRF]: async () => jsonResponse(204, null),
                [CHECKOUT]: async (init) => {
                    if (init?.method === 'POST') {
                        return jsonResponse(409, {
                            message: 'This service cannot take payments yet.',
                            reason: 'seller_identity_missing',
                        });
                    }
                    return jsonResponse(200, checkoutStatus({ profile_complete: true }));
                },
            }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} navigateToPayment={vi.fn()} />);
        const user = userEvent.setup();

        await user.click(await within(region()).findByRole('radio', { name: /pro/i }));
        await acceptConsents(user);
        await user.click(within(region()).getByRole('button', { name: /proceed to payment/i }));

        await waitFor(() => {
            expect(within(region()).getByRole('alert')).toHaveTextContent(
                /the seller has not published its legal identity/i,
            );
        });
    });

    it('links every document that must be readable before paying', async () => {
        fetchSpy.mockImplementation(
            baseRoutes({ [PROFILE]: async () => jsonResponse(200, completeProfile()) }),
        );

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(region()).getAllByRole('checkbox')).toHaveLength(2);
        });

        for (const href of ['/pre-information', '/distance-sales', '/delivery', '/refund-policy']) {
            expect(
                within(region())
                    .getAllByRole('link')
                    .some((link) => link.getAttribute('href') === href),
            ).toBe(true);
        }
    });
});
