import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { AuditTrailRegion } from './AuditTrailRegion';

/**
 * KİRACI BUNU NEREDE GÖRÜYOR — `docs/122` Y7 ve §5, `docs/133` §3.
 *
 * `docs/122` §5, platform ekibinin bakışının "kiracının GÖREBİLECEĞİ
 * biçimde" yazılmasını şart koşuyor. Bu dosya o şartın EKRAN tarafını
 * dondurur: kayıt yazılmış olmak yetmez, restoran sahibinin kendi
 * panelinde, kendi diliyle, gözüne çarpacak biçimde görünmesi gerekir.
 *
 * Sahibin göremediği bir denetim kaydı, denetim değil bir günlük dosyasıdır.
 */

const BODY = {
    data: [
        {
            source: 'support-access',
            action: 'support_access_opened',
            subject: 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).',
            actor: 'destek@zabuno.com',
            at: '2026-09-07 10:00:00',
        },
        {
            source: 'publication',
            action: 'published',
            subject: 'Moda Caddesi · v12',
            actor: 'sahip@ornek.test',
            at: '2026-09-05 18:20:11',
        },
    ],
    supportAccess: [
        {
            id: 9,
            actor: 'destek@zabuno.com',
            reason: 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).',
            startedAt: '2026-09-07 10:00:00',
            expiresAt: '2026-09-07 10:15:00',
            active: true,
        },
    ],
};

function stubFetch(body: unknown) {
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                ({
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => body,
                }) as Response,
        ),
    );
}

describe('AuditTrailRegion', () => {
    beforeEach(() => {
        stubFetch(BODY);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('tells the owner, in their own panel, that someone from the platform looked', async () => {
        render(<AuditTrailRegion workspaceId={42} />);

        const notice = await screen.findByRole('region', {
            name: 'Someone from Zabuno looked at your account',
        });

        // Sebep, süre ve kim — üçü de sahibin okuduğu cümlede.
        expect(
            within(notice).getByText(/Sahip aradı: karekod boş sayfa açıyor/),
        ).toBeInTheDocument();
        expect(
            within(notice).getByText(/ends automatically at 2026-09-07 10:15:00/),
        ).toBeInTheDocument();
        expect(within(notice).getByText(/destek@zabuno.com/)).toBeInTheDocument();
        // Ve ne YAPILAMADIĞI: sahibi rahatlatan yarısı budur.
        expect(
            within(notice).getByText(/Nothing can be changed, paid, published, invited or deleted/),
        ).toBeInTheDocument();
    });

    it('also names the visit inside the timeline, not only in the notice', async () => {
        render(<AuditTrailRegion workspaceId={42} />);

        const trail = await screen.findByRole('region', { name: 'What happened here' });
        const items = within(trail).getAllByRole('listitem');
        const row = items.find((item) => item.textContent?.includes('Account access'));

        expect(row).toBeDefined();
        expect(row?.textContent).toContain('Sahip aradı');
    });

    it('draws no notice at all when nobody has looked', async () => {
        stubFetch({ data: BODY.data.slice(1), supportAccess: [] });

        render(<AuditTrailRegion workspaceId={42} />);

        await screen.findByRole('region', { name: 'What happened here' });

        expect(
            screen.queryByRole('region', { name: 'Someone from Zabuno looked at your account' }),
        ).toBeNull();
    });

    it('says a finished session finished, with the window it ran in', async () => {
        stubFetch({
            data: [],
            supportAccess: [{ ...BODY.supportAccess[0], active: false }],
        });

        render(<AuditTrailRegion workspaceId={42} />);

        const notice = await screen.findByRole('region', {
            name: 'Someone from Zabuno looked at your account',
        });

        expect(
            within(notice).getByText(/From 2026-09-07 10:00:00 until 2026-09-07 10:15:00/),
        ).toBeInTheDocument();
    });
});
