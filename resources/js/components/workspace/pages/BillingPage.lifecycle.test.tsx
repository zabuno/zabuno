import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BillingPage } from './BillingPage';

/**
 * ABONELİĞİN EKSİK YARISI — panel yolu (docs/107 Faz 1.3, docs/134).
 *
 * Dondurulan sözleşme:
 *  - İptal düğmesi GÖRÜNÜR (saklanmaz) ama tek tıkla iptal ETMEZ: onay
 *    metni hizmetin hangi TARİHE kadar süreceğini yazar ve geri dönüş yolunu
 *    aynı ekranda söyler.
 *  - İptalden sonra panel "X tarihine kadar kullanmaya devam edeceksiniz"
 *    der ve cayma düğmesi sunar; cayma HİÇBİR ödeme isteği atmaz.
 *  - Düşürme seçilince kaybedilecek yetenek ADIYLA ve YÜRÜRLÜK TARİHİYLE
 *    gösterilir; liste sunucudan gelir, arayüzde ikinci bir etiket sözlüğü
 *    yoktur.
 *  - Ödemesiz sürede ve askıda uyarı okunur ve misafirin etkilenmediği
 *    açıkça yazılır.
 *  - Yükseltme bu bölgede HİÇ görünmez: hedef listesi yalnız daha ucuz
 *    planları taşır.
 */
const WORKSPACE_ID = 34;
const PLANS = `/api/workspaces/${WORKSPACE_ID}/plans`;
const SUBSCRIPTION = `/api/workspaces/${WORKSPACE_ID}/subscription`;
const PLAN_CHANGE = `/api/workspaces/${WORKSPACE_ID}/subscription/plan-change`;
const CANCELLATION = `/api/workspaces/${WORKSPACE_ID}/subscription/cancellation`;
const CSRF = '/sanctum/csrf-cookie';

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
            entitlements: [],
            amount_minor: 149900,
            currency: 'TRY',
        },
        {
            id: 12,
            name: 'Starter',
            code: 'starter',
            entitlements: [],
            amount_minor: 49900,
            currency: 'TRY',
        },
        {
            id: 13,
            name: 'Team',
            code: 'team',
            entitlements: [],
            amount_minor: 299900,
            currency: 'TRY',
        },
    ];
}

function subscription(overrides: Record<string, unknown> = {}) {
    return {
        state: 'active',
        phase: 'active',
        plan_id: 11,
        plan_code: 'pro',
        plan_name: 'Pro',
        plan_version: 1,
        ends_at: '2026-09-30T10:00:00+00:00',
        cancelled_at: null,
        grace_ends_at: '2026-10-07T10:00:00+00:00',
        scheduled_plan_id: null,
        scheduled_plan_code: null,
        scheduled_plan_name: null,
        ...overrides,
    };
}

describe('BillingPage — abonelik yaşam döngüsü (FF-219)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: string) => {
            throw new Error(`Unhandled fetch in lifecycle test: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function region() {
        return screen.getByRole('region', { name: 'Subscription' });
    }

    function route(subscriptionBody: unknown, extra: Record<string, unknown> = {}) {
        fetchSpy.mockImplementation(async (url: string) => {
            const target = String(url);

            if (target === PLANS) return jsonResponse(200, plans());
            if (target === SUBSCRIPTION) return jsonResponse(200, subscriptionBody);
            if (target === CSRF) return jsonResponse(204, null);

            for (const [prefix, body] of Object.entries(extra)) {
                if (target.startsWith(prefix)) return jsonResponse(200, body);
            }

            // Ödeme ve fatura bölgeleri bu testin konusu değil; boş cevap.
            return jsonResponse(200, { state: 'missing' });
        });
    }

    it('shows the cancellation route without hiding it, and does not cancel on a single click', async () => {
        route(subscription());

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        const cancel = await within(region()).findByRole('button', {
            name: 'Cancel subscription',
        });

        await userEvent.click(cancel);

        // Onay metni TARİHİ söyler ve geri dönüş yolunu aynı ekranda verir.
        expect(within(region()).getByText(/stays active until 2026-09-30/i)).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: 'Keep my subscription' }),
        ).toBeInTheDocument();

        expect(fetchSpy.mock.calls.some(([called]) => String(called) === CANCELLATION)).toBe(false);
    });

    it('cancels only after the confirmation step and then says how long the service continues', async () => {
        route(subscription());

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await userEvent.click(
            await within(region()).findByRole('button', { name: 'Cancel subscription' }),
        );

        fetchSpy.mockImplementation(async (url: string) => {
            const target = String(url);

            if (target === PLANS) return jsonResponse(200, plans());
            if (target === CSRF) return jsonResponse(204, null);
            if (target === SUBSCRIPTION) {
                return jsonResponse(
                    200,
                    subscription({
                        phase: 'cancelling',
                        cancelled_at: '2026-09-08T10:00:00+00:00',
                    }),
                );
            }
            if (target === CANCELLATION) {
                return jsonResponse(
                    200,
                    subscription({
                        phase: 'cancelling',
                        cancelled_at: '2026-09-08T10:00:00+00:00',
                    }),
                );
            }

            return jsonResponse(200, { state: 'missing' });
        });

        await userEvent.click(within(region()).getByRole('button', { name: 'Yes, cancel' }));

        await waitFor(() => {
            expect(
                within(region()).getByText(/You keep using Pro until 2026-09-30/i),
            ).toBeInTheDocument();
        });

        const cancelCall = fetchSpy.mock.calls.find(([called]) => String(called) === CANCELLATION);
        expect(cancelCall).toBeDefined();
        expect((cancelCall?.[1] as RequestInit).method).toBe('POST');
    });

    it('offers a way back from a cancellation that costs nothing', async () => {
        route(subscription({ phase: 'cancelling', cancelled_at: '2026-09-08T10:00:00+00:00' }));

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        const resume = await within(region()).findByRole('button', {
            name: 'Undo the cancellation',
        });
        expect(
            within(region()).getByText('You will not be asked to pay again.'),
        ).toBeInTheDocument();

        await userEvent.click(resume);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(([called]) => String(called) === CANCELLATION);
            expect((call?.[1] as RequestInit | undefined)?.method).toBe('DELETE');
        });

        // Cayma bir ÖDEME DEĞİLDİR: hiçbir ödeme başlatılmaz. (Sayfanın
        // ödeme bölgesi durumunu OKUR; ölçülen şey yazma isteğidir.)
        expect(
            fetchSpy.mock.calls.some(
                ([called, init]) =>
                    String(called).endsWith(`/workspaces/${WORKSPACE_ID}/checkout`) &&
                    (init as RequestInit | undefined)?.method === 'POST',
            ),
        ).toBe(false);
    });

    it('only offers cheaper plans and names what will be lost before anything is scheduled', async () => {
        route(subscription(), {
            [`${PLAN_CHANGE}?plan_id=12`]: {
                current_plan_id: 11,
                current_plan_name: 'Pro',
                target_plan_id: 12,
                target_plan_name: 'Starter',
                direction: 'downgrade',
                effective_at: '2026-09-30T10:00:00+00:00',
                losing: [{ key: 'menu.rich-media', label: 'Zengin görsel' }],
                gaining: [],
                already_scheduled: false,
            },
        });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        const starter = await within(region()).findByRole('radio', { name: /Starter/ });
        expect(within(region()).queryByRole('radio', { name: /Team/ })).toBeNull();

        await userEvent.click(starter);

        await waitFor(() => {
            expect(within(region()).getByText('Zengin görsel')).toBeInTheDocument();
        });

        expect(
            within(region()).getByText(/Takes effect on 2026-09-30.*Nothing is refunded/i),
        ).toBeInTheDocument();
        expect(within(region()).getByText(/next publication/i)).toBeInTheDocument();
    });

    it('says what is scheduled and offers a way back from it', async () => {
        route(subscription({ scheduled_plan_id: 12, scheduled_plan_name: 'Starter' }));

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        expect(
            await within(region()).findByText(
                /On 2026-09-30T10:00:00\+00:00 your plan becomes Starter/i,
            ),
        ).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: 'Keep my current plan' }),
        ).toBeInTheDocument();
    });

    it('warns during the grace period and tells the owner the guest is unaffected', async () => {
        route(subscription({ phase: 'grace' }));

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        const alert = await within(region()).findByRole('alert');
        expect(alert).toHaveTextContent(/features stay on until 2026-10-07/i);
        expect(
            within(region()).getByText(/menu behind a printed QR code keeps showing/i),
        ).toBeInTheDocument();
    });

    it('explains a suspension without claiming the account or the menus are gone', async () => {
        route(subscription({ phase: 'suspended' }));

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        const alert = await within(region()).findByRole('alert');
        expect(alert).toHaveTextContent(/published menus are still online/i);
        expect(within(region()).queryByRole('button', { name: 'Cancel subscription' })).toBeNull();
    });

    it('does not invent a phase for a workspace that has no subscription', async () => {
        route({ state: 'none' });

        render(<BillingPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(region()).getByRole('status')).toHaveTextContent(/no paid subscription/i);
        });

        expect(within(region()).queryByRole('button', { name: 'Cancel subscription' })).toBeNull();
    });
});
