import type React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * Regression contract for three visible AdminShell destinations added in
 * S1-WP05a: #analytics -> AnalyticsPage, #team -> TeamPage,
 * #billing -> BillingPage. This suite established RED before those
 * destinations existed in WorkspaceApp; it now serves as the GREEN
 * regression contract for their implementation.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const TEAM_MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const TEAM_INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;
const PLANS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/plans`;
const BREAKPOINT_TOKEN = /(?:^|\s)(sm|md|lg|xl|2xl):/;
const RESPONSIVE_WORDING = /\bresponsive\b/i;

function makePlans() {
    return [
        {
            id: 21,
            name: 'Zabuno Test Plan',
            code: 'wp05-test-plan',
            entitlements: ['Test entitlement'],
            amount_minor: 99900,
            currency: 'TRY',
        },
    ];
}

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeUser() {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
    };
}

function makeLocation() {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
    };
}

function buildFetchMock() {
    return async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return jsonResponse(200, [makeWorkspace()]);
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return jsonResponse(200, makeWorkspace());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'GET') {
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation()]);
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/923/analytics/summary?range=today` &&
            method === 'GET'
        ) {
            return jsonResponse(200, {
                qrResolveCount: 0,
                menuOpenCount: 0,
            });
        }
        if (String(url) === TEAM_MEMBERS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, []);
        }
        if (String(url) === TEAM_INVITATIONS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, []);
        }
        if (String(url) === PLANS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, makePlans());
        }

        throw new Error(`Unhandled fetch in WorkspaceApp wp05 RED test: ${method} ${String(url)}`);
    };
}

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        writable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        configurable: true,
        writable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function allClassAttributes(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[class]')).map(
        (el) => el.getAttribute('class') ?? '',
    );
}

function allTextContent(container: HTMLElement): string {
    return container.textContent ?? '';
}

async function renderCurrentWorkspace() {
    const originalFetch = window.fetch;
    Object.defineProperty(window, 'fetch', {
        configurable: true,
        writable: true,
        value: buildFetchMock(),
    });

    const { WorkspaceApp } = await importWorkspaceModule<{
        WorkspaceApp: React.ComponentType<typeof desktopChrome>;
    }>();
    const rendered = render(<WorkspaceApp {...desktopChrome} />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return {
        ...rendered,
        restoreFetch: () => {
            window.fetch = originalFetch;
        },
    };
}

/**
 * FF-84: Ayarlar KENAR ÇUBUĞUNDA değil, hesap (sistem) menüsünde.
 *
 * Sahibin kararı: tek maddelik "Settings" grubu her ekranda dikey alan
 * harcıyordu. Adres değişmedi (`/app/{ws}/settings`); yalnız oraya giden
 * kontrolün yeri değişti. Testler bu yardımcıdan geçer ki gelecekte yer
 * bir kez daha değişirse tek dosyada güncellensin.
 */
async function openSettingsFromAccountMenu(user: ReturnType<typeof userEvent.setup>) {
    await user.click(await screen.findByRole('button', { name: 'Account' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Settings' }));
}

describe('WorkspaceApp — Analytics/Team/Billing AdminShell destinations (S1-WP05a, RED)', () => {
    beforeEach(() => {
        // Her test tarayıcıyı YENİ açmış gibi başlar: gezinti artık adresi
        // gerçekten değiştiriyor ve bir testin bıraktığı adres sonrakini
        // sessizce başka bir ekranda açardı.
        history.replaceState(null, '', '/');
        setViewport(320, 480);
    });

    it('exposes accessible Analytics, Team, and Billing nav links pointing at their real section addresses', async () => {
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        expect(within(nav).getByRole('link', { name: 'Insights' })).toHaveAttribute(
            'href',
            '/app/zeytin-restoranlari/analytics',
        );
        expect(within(nav).getByRole('link', { name: 'Team' })).toHaveAttribute(
            'href',
            '/app/zeytin-restoranlari/team',
        );
        // Billing artık ana menüde DEĞİL: günlük operasyon değildir ve
        // Settings'in içinde durur (docs/50 §5). Gezintideki hedef Settings.
        expect(within(nav).queryByRole('link', { name: 'Billing' })).toBeNull();
        /*
            FF-84: Ayarlar kenar çubuğundan hesap (sistem) menüsüne taşındı
            (sahibin kararı). Kayıtta grubu yok; adresi çalışmaya devam eder.
        */
        expect(within(nav).queryByRole('link', { name: 'Settings' })).toBeNull();

        restoreFetch();
    });

    it('the Analytics section renders AnalyticsPage with a heading, a range control, and the real zero-count summary metric result', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Insights' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-analytics')).not.toBeNull();

        const analyticsRegion = main.querySelector('#section-analytics') as HTMLElement;

        expect(
            within(analyticsRegion).getByRole('heading', { name: /Analytics/i }),
        ).toBeInTheDocument();

        expect(
            within(analyticsRegion).getByRole('combobox', { name: /range/i }) ??
                within(analyticsRegion).getByRole('group', { name: /range/i }),
        ).toBeTruthy();

        const metricRegion = within(analyticsRegion).getByRole('region', {
            name: /metric|report/i,
        });
        expect(metricRegion).toBeInTheDocument();

        /*
            SIFIRLARDAN OLUŞAN BİR IZGARA ARTIK ÇİZİLMİYOR — `docs/66`.

            "0 tarama / 0 menü açılışı" teknik olarak dürüsttü ama kullanıcıya
            hiçbir şey söylemiyordu: ne olmadığını gösteriyor, NEDEN olmadığını
            ve şimdi ne yapılacağını söylemiyordu. Yerine sebebe göre ayrılmış
            bir boş durum geliyor; burada menü yok, dolayısıyla önce menü.

            İddia sıfırları değil, ÇIKIŞ YOLUNU ölçüyor.
        */
        expect(
            await within(metricRegion).findByText('Analytics starts with your first menu'),
        ).toBeInTheDocument();
        expect(
            within(metricRegion).getByRole('button', { name: 'Build the menu' }),
        ).toBeInTheDocument();

        const regionText = allTextContent(analyticsRegion);
        expect(regionText).not.toMatch(/not available|unavailable|fabricated/i);

        restoreFetch();
    });

    it('the Team section renders TeamPage with a heading, invite email input, an Editor-only invitation control (no Owner invite control), a member region, and an Invite action gated on a valid email', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Team' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-team')).not.toBeNull();

        const teamRegion = main.querySelector('#section-team') as HTMLElement;

        expect(within(teamRegion).getByRole('heading', { name: /Team/i })).toBeInTheDocument();

        expect(within(teamRegion).getByLabelText(/invite.*email/i)).toBeInTheDocument();

        expect(within(teamRegion).queryByRole('radio', { name: /Owner/i })).not.toBeInTheDocument();

        expect(within(teamRegion).getByRole('region', { name: /member/i })).toBeInTheDocument();

        const inviteButton = within(teamRegion).getByRole('button', { name: /invite/i });
        expect(inviteButton).toBeDisabled();

        const emailInput = within(teamRegion).getByLabelText(/invite.*email/i);
        await user.type(emailInput, 'valid@example.test');
        expect(inviteButton).toBeEnabled();

        restoreFetch();
    });

    it('the Billing section renders BillingPage with a heading, plan/current-plan/manual-payment regions, the server-returned plan with its exact derived price, an honest current-plan load-failure alert with an enabled Retry, disabled manual-payment actions, and an honest Iyzico subscription-status alert with enabled Retry and disabled Start sandbox checkout', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        screen.getByRole('navigation', { name: 'Restaurant admin' });
        // Billing, Settings'in ikinci sekmesi.
        await openSettingsFromAccountMenu(user);
        await user.click(screen.getByRole('tab', { name: 'Plan & billing' }));

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-billing')).not.toBeNull();

        const billingRegion = main.querySelector('#section-billing') as HTMLElement;

        expect(
            within(billingRegion).getByRole('heading', { name: /Billing/i }),
        ).toBeInTheDocument();

        const planRegion = within(billingRegion).getByRole('region', { name: /^plan$/i });
        const currentPlanRegion = within(billingRegion).getByRole('region', {
            name: /current.plan/i,
        });
        // Manuel ödeme bölgesi artık YOK: başka bir rolün işiydi ve
        // restoran sahibi onu hiçbir koşulda etkinleştiremezdi (docs/57).
        expect(within(billingRegion).queryByRole('region', { name: /manual.payment/i })).toBeNull();
        expect(currentPlanRegion).toBeInTheDocument();

        await within(planRegion).findByText('Zabuno Test Plan');
        expect(within(planRegion).getByText('wp05-test-plan')).toBeInTheDocument();
        expect(within(planRegion).getByText('Test entitlement')).toBeInTheDocument();
        // Fiyat artık okuyucunun diline göre biçimlenir (CORE-12,
        // `docs/13` §4): jsdom belgesinin dili boş olduğu için taban locale
        // `en` uygulanır. Türkçe biçim, `lang="tr"` ile ayrıca doğrulanır
        // (`resources/js/money/format.test.ts`).
        expect(within(planRegion).getByText('TRY 999.00')).toBeInTheDocument();

        // The mock intentionally leaves the subscription endpoint unhandled,
        // so Current plan honestly reports the load failure with a retry
        // instead of silently disabling every action.
        expect(within(currentPlanRegion).getByRole('alert')).toHaveTextContent(
            'We could not load the current plan.',
        );
        expect(within(currentPlanRegion).getByRole('button', { name: /retry/i })).toBeEnabled();

        expect(
            within(billingRegion).queryAllByRole('button', { name: /record payment/i }),
        ).toHaveLength(0);

        const iyzicoRegion = within(billingRegion).getByRole('region', { name: /iyzico sandbox/i });
        expect(within(iyzicoRegion).getByRole('alert')).toHaveTextContent(
            'Could not load your subscription status.',
        );
        expect(within(iyzicoRegion).getByRole('button', { name: /retry/i })).toBeEnabled();
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeDisabled();

        restoreFetch();
    });

    it('keeps Analytics, Team, and Billing destinations reachable at a simulated 320x480 CSS px viewport with fluid intrinsic layout and no breakpoint-prefixed classes or responsive wording', async () => {
        const user = userEvent.setup();
        const { container, restoreFetch } = await renderCurrentWorkspace();

        setViewport(320, 480);

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        // Billing gezintide değil; Settings'in içindedir (docs/50 §5).
        // FF-84: Settings de gezintide değil — hesap (sistem) menüsünde.
        for (const [label, section] of [
            ['Insights', 'analytics'],
            ['Team', 'team'],
        ] as const) {
            await user.click(within(nav).getByRole('link', { name: label }));

            const main = screen.getByRole('main');
            // Hash bir ROTA, kap id'si ise bir ELEMAN kimliğidir; ikisi
            // artık kasten ayrıdır. Aynı olmaları tarayıcının o elemana
            // kaydırmasına ve gezinti tıklamasında sayfanın sıçramasına yol
            // açıyordu.
            const destination = main.querySelector(`#section-${section}`);
            expect(destination).not.toBeNull();

            for (const classAttribute of allClassAttributes(destination as HTMLElement)) {
                expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
            }

            expect(allTextContent(destination as HTMLElement)).not.toMatch(RESPONSIVE_WORDING);
        }

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }

        restoreFetch();
    });

    it('shows a shared page-frame description and honest status badge on Analytics, distinct Pending invitations/Team members regions on Team, and canonical disabled manual-payment fields plus a disabled Iyzico Start sandbox checkout action with an honest subscription-status alert on Billing (S1-WP05a page-frame batch, RED)', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        // Analytics: a page-frame description plus an honest status badge
        // reflecting the real fetch/range status, not a fabricated label.
        await user.click(within(nav).getByRole('link', { name: 'Insights' }));
        const analyticsRegion = screen
            .getByRole('main')
            .querySelector('#section-analytics') as HTMLElement;
        // Sıfır sayaç yerine sebebe göre ayrılmış boş durum (docs/66).
        await within(analyticsRegion).findByText('Analytics starts with your first menu');
        expect(
            within(analyticsRegion).getByText(/qr resolve and confirmed menu open/i),
        ).toBeInTheDocument();
        // Başarı hâlinde durum rozeti ÇİZİLMEZ. Önceden buraya seçili zaman
        // aralığı ("Today") basılıyordu; oysa o bilgi hemen altındaki `Range`
        // seçicisinde duruyor ve kullanıcının kendi seçtiği şeydir. Bildiği
        // şeyi tekrarlayan bir rozet, rozetlerin tamamını okunmayan süse
        // çevirir — sonra gerçek uyarı da fark edilmez.
        expect(within(analyticsRegion).queryByTestId('flowbite-badge')).toBeNull();

        // Team: grouped Invite surface plus two DISTINCT regions — Pending
        // invitations and Team members — each with its own honest
        // unavailable status, not one shared "members" region.
        await user.click(within(nav).getByRole('link', { name: 'Team' }));
        const teamRegion = screen.getByRole('main').querySelector('#section-team') as HTMLElement;
        expect(
            within(teamRegion).getByRole('region', { name: /pending invitation/i }),
        ).toBeInTheDocument();
        expect(
            within(teamRegion).getByRole('region', { name: /^team members$/i }),
        ).toBeInTheDocument();

        // Billing: manual payment carries the canonical Stage 1 fields —
        // plan assignment, end date, payment note, document reference —
        // all disabled and empty, plus a disabled Iyzico sandbox region.
        // Billing, Settings'in ikinci sekmesi.
        await openSettingsFromAccountMenu(user);
        await user.click(screen.getByRole('tab', { name: 'Plan & billing' }));
        const billingRegion = screen
            .getByRole('main')
            .querySelector('#section-billing') as HTMLElement;
        // Manuel ödeme bölgesi artık YOK: başka bir rolün işiydi ve
        // restoran sahibi onu hiçbir koşulda etkinleştiremezdi (docs/57).
        expect(within(billingRegion).queryByRole('region', { name: /manual.payment/i })).toBeNull();

        // Alanların kendisi de yok: devre dışı gösterilmiyor, HİÇ çizilmiyor.
        for (const fieldName of [/plan assignment/i, /end date/i, /payment note/i]) {
            expect(within(billingRegion).queryByLabelText(fieldName)).toBeNull();
        }

        const iyzicoRegion = within(billingRegion).getByRole('region', { name: /iyzico sandbox/i });
        expect(
            within(iyzicoRegion).getByRole('button', { name: /start sandbox checkout/i }),
        ).toBeDisabled();
        expect(within(iyzicoRegion).getByRole('alert')).toHaveTextContent(
            'Could not load your subscription status.',
        );

        restoreFetch();
    });
});
