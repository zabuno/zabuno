import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

import { MediaGovernanceRegion } from './MediaGovernanceRegion';

/**
 * YÖNETİŞİM — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Yönetişim"` (plan `docs/109-PANEL-V3.md` §2).
 *
 * Kaynağın cümlesi: "Kim ne yapabilir, dosyalar ne kadar saklanır, kim ne
 * yaptı."
 *
 * Restoran sahibinin yolculuğu: menüyü hazırlayan editör "kalıcı sil"
 * düğmesini arıyor, bulamıyor ve "ürün bunu yapamıyor" diye patronuna hiç
 * sormuyor. Ay sonunda kütüphane on bin dosyaya çıkıyor ve kotayı
 * dolduruyor. Bu ekranın tek işi o sessizliği bozmaktır.
 *
 * Dört davranış korunur:
 *
 * 1. **Kilitli satır GİZLENMEZ, sebebi yazılır.** Editör "Kalıcı sil"
 *    satırını görür ve hangi iznin gerektiğini okur.
 * 2. **Rol UYDURULMAZ.** Ekranda yazan rol sunucunun verdiği roldür;
 *    kaynağın dört kademeli kurgusu değil.
 * 3. **Yasal saklama SEBEBİYLE listelenir.** "Kilitli" tek başına, altı
 *    ay sonra kilidi kaldırmaya cesaret edemeyecek bir sahip bırakır.
 * 4. **Denetim izi iki kaynağı tek listede okur** ve SALT OKUNUR
 *    olduğunu söyler.
 */
const BODY = {
    role: 'editor',
    permissions: [
        {
            action: 'optimize',
            allowed: true,
            requiredPermission: 'media.manage',
            reversible: true,
        },
        { action: 'move', allowed: true, requiredPermission: 'media.manage', reversible: true },
        { action: 'trash', allowed: true, requiredPermission: 'media.manage', reversible: true },
        {
            action: 'purge',
            allowed: false,
            requiredPermission: 'workspace.manage',
            reversible: false,
        },
        {
            action: 'legal-hold',
            allowed: false,
            requiredPermission: 'workspace.manage',
            reversible: true,
        },
    ],
    retention: { trashRetentionDays: 30, legalHoldCount: 1 },
    legalHolds: [
        {
            id: 9,
            name: 'sozlesme-2026.pdf',
            reason: 'Uyuşmazlık kaydı 2026/14',
            at: '2026-09-01 10:00:00',
        },
    ],
    trail: [
        {
            kind: 'bulk',
            action: 'convert',
            actor: 'mehmet@pasadoner.example',
            at: '2026-09-05 01:12:00',
            scope: 'workspace',
            applied: 1831,
            skipped: 31,
            failed: 2,
            operationKey: 'bulk_01',
        },
        {
            kind: 'asset',
            action: 'trashed',
            actor: null,
            at: '2026-09-04 11:02:00',
            mediaAssetId: 12,
        },
    ],
};

function stubFetch(body: unknown = BODY, ok = true) {
    const fetchMock = vi.fn(async () => ({ ok, status: ok ? 200 : 500, json: async () => body }));
    vi.stubGlobal('fetch', fetchMock as unknown as typeof fetch);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('MediaGovernanceRegion', () => {
    it('kilitli satırı gizlemez ve gereken izni yazar', async () => {
        stubFetch();
        render(<MediaGovernanceRegion workspaceId={7} />);

        const matrix = within(
            await screen.findByRole('list', { name: /bulk action permissions/i }),
        );

        expect(matrix.getByText(/delete permanently/i)).toBeInTheDocument();
        expect(matrix.getAllByText(/needs “workspace\.manage”/i).length).toBe(2);
    });

    it('rolü sunucudan okur, kendi kurgusunu çizmez', async () => {
        stubFetch();
        render(<MediaGovernanceRegion workspaceId={7} />);

        expect(await screen.findByText(/as an editor/i)).toBeInTheDocument();
    });

    it('yasal saklamayı sebebiyle listeler', async () => {
        stubFetch();
        render(<MediaGovernanceRegion workspaceId={7} />);

        expect(await screen.findByText('sozlesme-2026.pdf')).toBeInTheDocument();
        expect(screen.getByText(/Uyuşmazlık kaydı 2026\/14/)).toBeInTheDocument();
    });

    it('saklama süresini sunucunun sayısıyla yazar', async () => {
        stubFetch();
        render(<MediaGovernanceRegion workspaceId={7} />);

        // 30 gün EKRANDA sabit değil: sunucudan gelir ve plan değişince
        // ekran da değişir.
        expect(await screen.findByText('30 days')).toBeInTheDocument();
    });

    it('denetim izinde toplu ve tek dosya kayıtlarını birlikte gösterir', async () => {
        stubFetch();
        render(<MediaGovernanceRegion workspaceId={7} />);

        const trail = within(await screen.findByRole('list', { name: /audit trail/i }));

        expect(trail.getByText(/1831 applied, 31 skipped, 2 failed/i)).toBeInTheDocument();
        expect(trail.getByText(/file #12/i)).toBeInTheDocument();
        // Aktörü bilinmeyen kayıt SİLİNMEZ: failin bilinmediğini söylemek
        // kaydı gizlemekten dürüsttür.
        expect(trail.getByText(/unknown person/i)).toBeInTheDocument();
    });

    it('okunamayan kaydı sessizce boş bırakmaz', async () => {
        stubFetch({}, false);
        render(<MediaGovernanceRegion workspaceId={7} />);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/could not be read/i);
        });
    });
});
