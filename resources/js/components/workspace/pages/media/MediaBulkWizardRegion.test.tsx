import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaBulkWizardRegion } from './MediaBulkWizardRegion';

/**
 * TOPLU İŞLEM SİHİRBAZI — kanonik kaynak `docs/reference/panel-v3/
 * MedyaModulu.dc.html`, `data-screen-label="Toplu işlem"`
 * (plan `docs/109-PANEL-V3.md` §2).
 *
 * Kaynağın beş adımı: Kapsam → Eylem → Ayar → Etki → Sonuç.
 *
 * Restoran sahibinin yolculuğu: "Paşa Döner"in kütüphanesinde telefondan
 * çıkmış bin sekiz yüz fotoğraf var. Sahip hepsini AVIF'e çevirmek
 * istiyor ama iki korkusu var — "aslını kaybeder miyim?" ve "yanlış
 * dosyalar da girer mi?". Bu sihirbaz iki korkuyu da adım adım
 * cevaplamalı ve HİÇBİR ŞEYE dokunmadan önce ne olacağını göstermeli.
 *
 * Bu dosya altı davranışı korur:
 *
 * 1. **Kapsam adımı dondurma kuralını YAZAR.** Kaynağın kendi cümlesi
 *    ekranda birebir durur; "iş çalışırken yüklediğim dosya ne olacak?"
 *    sorusu sorulmadan cevaplanır.
 * 2. **Kilitli eylem GİZLENMEZ, sebebi yazılır.** Editör "Kalıcı sil"
 *    kartını görür ve neden kapalı olduğunu okur.
 * 3. **Kuru çalışma sunucudan gelir ve ATLANANI sebebiyle gösterir.**
 *    Ekran hiçbir sayıyı kendisi hesaplamaz.
 * 4. **Yıkıcı işte onay kutusu yoktur; kelime YAZILIR.** Kelime tam
 *    değilken başlatma düğmesi kapalıdır.
 * 5. **Sonuç dosya dosyadır ve yalnız hatalılar yeniden denenir.**
 * 6. **Çalıştırma DONDURULMUŞ listeyi gönderir** — ekranda gösterilen
 *    kimlikleri, o an sunucuda ne varsa onu değil.
 */
const PLAN_OPTIMIZE = {
    action: 'optimize',
    allowed: true,
    requiredPermission: null,
    scope: { kind: 'workspace', count: 3, totalBytes: 3_145_728 },
    snapshot: { assetIds: [11, 12, 13] },
    applyCount: 1,
    batchLimit: 25,
    remaining: 0,
    skips: [
        { reason: 'quarantine', count: 1 },
        { reason: 'legal-hold', count: 1 },
    ],
    skippedAssets: [
        { id: 12, name: 'karantina.jpg', reason: 'quarantine' },
        { id: 13, name: 'yasal.jpg', reason: 'legal-hold' },
    ],
    impact: {
        reversible: true,
        undoWindowDays: 30,
        newVersion: true,
        quotaBytesUsed: 3_145_728,
        quotaBytesLimit: 1_073_741_824,
        quotaBytesFreed: null,
    },
    confirmation: { required: false, word: null },
};

const PLAN_PURGE = {
    ...PLAN_OPTIMIZE,
    action: 'purge',
    applyCount: 2,
    skips: [],
    skippedAssets: [],
    impact: { ...PLAN_OPTIMIZE.impact, reversible: false, undoWindowDays: null, newVersion: false },
    confirmation: { required: true, word: 'KALICI SİL' },
};

const RUN_REPORT = {
    operationKey: 'bulk_test',
    action: 'optimize',
    replayed: false,
    applied: 1,
    skipped: 1,
    failed: 1,
    remaining: 0,
    results: [
        { id: 11, name: 'adana-kebap.jpg', status: 'ok', reason: null },
        { id: 12, name: 'karantina.jpg', status: 'skip', reason: 'quarantine' },
        { id: 14, name: 'ramazan-afis.png', status: 'error', reason: 'reprocess-failed' },
    ],
};

function stubFetch(plan: unknown = PLAN_OPTIMIZE, run: unknown = RUN_REPORT) {
    // İkinci parametre TİPTE de duruyor: testin asıl iddiası gövdenin
    // İÇİNDEKİ dondurulmuş kimlik listesidir ve o gövdeye ancak buradan
    // bakılabilir.
    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        void init;
        if (String(url).endsWith('/bulk/plan')) {
            return { ok: true, status: 200, json: async () => plan };
        }

        return { ok: true, status: 200, json: async () => run };
    });

    vi.stubGlobal('fetch', fetchMock as unknown as typeof fetch);

    return fetchMock;
}

function renderWizard() {
    return render(
        <MediaBulkWizardRegion
            workspaceId={7}
            folders={[{ id: 3, name: 'Ürünler' }]}
            activeFolderId={null}
            assets={[
                { id: 11, altText: 'adana-kebap.jpg', slot: 'menu', status: 'ready' },
                { id: 12, altText: 'karantina.jpg', slot: 'menu', status: 'quarantined' },
            ]}
        />,
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('MediaBulkWizardRegion', () => {
    it('kapsam adımı dondurma kuralını ekranda yazar', () => {
        stubFetch();
        renderWizard();

        expect(screen.getByText(/frozen the moment the job starts/i)).toBeInTheDocument();
    });

    it('kilitli eylemi gizlemez, gereken izni yazar', async () => {
        const user = userEvent.setup();
        // Editörün planı: kalıcı silme kapalı ve sebebi sunucudan gelir.
        stubFetch({ ...PLAN_PURGE, allowed: false, requiredPermission: 'workspace.manage' });
        renderWizard();

        await user.click(screen.getByRole('button', { name: /choose an action/i }));
        await user.click(screen.getByRole('radio', { name: /delete permanently/i }));

        await waitFor(() => {
            expect(screen.getByText(/workspace\.manage/)).toBeInTheDocument();
        });
    });

    it('kuru çalışmanın atlananlarını sebebiyle gösterir ve hiçbir sayıyı kendi hesaplamaz', async () => {
        const user = userEvent.setup();
        const fetchMock = stubFetch();
        renderWizard();

        await user.click(screen.getByRole('button', { name: /choose an action/i }));
        await user.click(screen.getByRole('radio', { name: /re-render derivatives/i }));
        await user.click(screen.getByRole('button', { name: /set up/i }));
        await user.click(screen.getByRole('button', { name: /show the impact/i }));

        await waitFor(() => {
            expect(screen.getByText(/No file was touched/i)).toBeInTheDocument();
        });

        // Sunucunun sayıları: 1 uygulanacak, 2 atlanacak. Sayı ETİKETİYLE
        // birlikte aranır — ekranda yalnız "1" aramak, hangi sayıyı
        // doğruladığını söylemeyen bir iddia olurdu.
        expect(screen.getByText(/Will be applied/i).closest('div')).toHaveTextContent('1');
        expect(screen.getByText(/Will be skipped/i).closest('div')).toHaveTextContent('2');
        expect(screen.getByText(/not through the security scan/i)).toBeInTheDocument();
        expect(screen.getByText(/under a legal hold/i)).toBeInTheDocument();

        const planCall = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/bulk/plan'));
        expect(planCall).toBeDefined();
    });

    it('yıkıcı işte kelime yazılmadan başlatma düğmesi kapalıdır', async () => {
        const user = userEvent.setup();
        stubFetch(PLAN_PURGE);
        renderWizard();

        await user.click(screen.getByRole('button', { name: /choose an action/i }));
        await user.click(screen.getByRole('radio', { name: /delete permanently/i }));
        await user.click(screen.getByRole('button', { name: /set up/i }));
        await user.click(screen.getByRole('button', { name: /show the impact/i }));

        const start = await screen.findByRole('button', { name: /delete · 2 files/i });
        expect(start).toBeDisabled();

        // Yanlış kelime açmaz.
        await user.type(screen.getByLabelText(/type KALICI SİL/i), 'kalıcı sil');
        expect(start).toBeDisabled();

        await user.clear(screen.getByLabelText(/type KALICI SİL/i));
        await user.type(screen.getByLabelText(/type KALICI SİL/i), 'KALICI SİL');
        expect(start).toBeEnabled();
    });

    it('çalıştırmada DONDURULMUŞ listeyi gönderir ve sonucu dosya dosya çizer', async () => {
        const user = userEvent.setup();
        const fetchMock = stubFetch();
        renderWizard();

        await user.click(screen.getByRole('button', { name: /choose an action/i }));
        await user.click(screen.getByRole('radio', { name: /re-render derivatives/i }));
        await user.click(screen.getByRole('button', { name: /set up/i }));
        await user.click(screen.getByRole('button', { name: /show the impact/i }));
        await user.click(await screen.findByRole('button', { name: /start · 1 file/i }));

        await waitFor(() => {
            expect(screen.getByText('adana-kebap.jpg')).toBeInTheDocument();
        });

        const runCall = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/bulk/run'));
        expect(runCall).toBeDefined();
        const body = JSON.parse(String((runCall?.[1] as RequestInit).body));
        // Ekranda gösterilen kimlikler gönderilir — sunucuda o an ne varsa
        // o değil.
        expect(body.assetIds).toEqual([11, 12, 13]);
        expect(typeof body.operationKey).toBe('string');

        // Hatalı dosya sebebiyle görünür ve yalnız o yeniden denenir.
        expect(screen.getByText(/Re-rendering failed/i)).toBeInTheDocument();
        const retry = screen.getByRole('button', { name: /retry the 1 failed files only/i });
        expect(retry).toBeEnabled();
    });

    it('yalnız hatalıları yeniden denerken başarılı dosyaya dokunmaz', async () => {
        const user = userEvent.setup();
        const fetchMock = stubFetch();
        renderWizard();

        await user.click(screen.getByRole('button', { name: /choose an action/i }));
        await user.click(screen.getByRole('radio', { name: /re-render derivatives/i }));
        await user.click(screen.getByRole('button', { name: /set up/i }));
        await user.click(screen.getByRole('button', { name: /show the impact/i }));
        await user.click(await screen.findByRole('button', { name: /start · 1 file/i }));
        await screen.findByRole('button', { name: /retry the 1 failed files only/i });

        await user.click(screen.getByRole('button', { name: /retry the 1 failed files only/i }));

        await waitFor(() => {
            const runCalls = fetchMock.mock.calls.filter(([url]) =>
                String(url).endsWith('/bulk/run'),
            );
            expect(runCalls).toHaveLength(2);
            const body = JSON.parse(String((runCalls[1][1] as RequestInit).body));
            expect(body.assetIds).toEqual([14]);
        });
    });

    it('adım şeridi ileriye atlamayı kilitler', async () => {
        stubFetch();
        renderWizard();

        const steps = within(screen.getByRole('list', { name: /bulk actions/i }));
        // "Sonuç" adımına kapsam adımından atlanamaz: atlanan bir adım,
        // görmeden onaylanan bir etki demektir.
        expect(steps.getByRole('button', { name: /result/i })).toBeDisabled();
    });
});
