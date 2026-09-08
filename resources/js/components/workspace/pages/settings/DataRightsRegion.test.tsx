import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import { DataRightsRegion } from './DataRightsRegion';

/**
 * VERİ HAKLARI — Ayarlar > Çalışma alanı, tehlikeli bölge (FF-226,
 * `docs/138`).
 *
 * Bir zincirin hukuk birimi üç soru sorar: veri nerede tutuluyor, kim
 * erişebiliyor, silmek istersek ne oluyor. Bu bölüm birinci ve üçüncü
 * sorunun ekrandaki cevabıdır.
 *
 * Dört davranış korunur:
 *
 * 1. **"Her şey silinir" DENMEZ.** Saklananlar sunucudan gelir ve adıyla,
 *    sebebiyle listelenir. Ekran kendi listesini yazsaydı, bir gün bir
 *    tablo saklananlar arasına girer ve ekran yalan söylemeye devam
 *    ederdi.
 * 2. **Süre sunucudan gelir.** Ekranda sabit bir gün sayısı yoktur.
 *    Yapılandırma değişince ekran da değişir.
 * 3. **Onay çalışma alanının ADIDIR.** Yanlış ad sunucuya hiç gitmez.
 * 4. **Posta yapılandırılmamışsa arşiv yine ekranda durur** ve kullanıcı
 *    e-posta beklemesin diye bunu söyler (`docs/93`).
 */
const BODY = {
    workspaceName: 'Zeytin Restoranları',
    graceDays: 14,
    exportedSections: ['workspaces', 'products', 'menus'],
    retained: {
        invoices: 'Kesilmiş fatura yasal bir belgedir.',
        consent_records: 'Onayın kanıtı.',
    },
    outOfScope: { platform_audits: 'Platformun kendi kaydı.' },
    assetsUnderLegalHold: 0,
    hosting: { provider: 'netcup GmbH', country: 'Germany' },
    requests: [],
};

function stubFetch(body: unknown = BODY, ok = true) {
    const fetchMock = vi.fn(async () => ({
        ok,
        status: ok ? 200 : 500,
        json: async () => body,
    }));
    vi.stubGlobal('fetch', fetchMock as unknown as typeof fetch);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('DataRightsRegion', () => {
    it('verinin nerede durduğunu ekranın en üstünde söyler', async () => {
        stubFetch();
        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText(/netcup GmbH/)).toBeInTheDocument();
        expect(screen.getByText(/Germany/)).toBeInTheDocument();
    });

    it('silinmeyenleri adıyla ve sebebiyle sayar, "her şey silinir" demez', async () => {
        stubFetch();
        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText('invoices')).toBeInTheDocument();
        expect(screen.getByText('Kesilmiş fatura yasal bir belgedir.')).toBeInTheDocument();
        expect(screen.getByText('consent_records')).toBeInTheDocument();
    });

    it('pencere süresini sunucudan okur, kendi rakamını yazmaz', async () => {
        stubFetch();
        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText(/14 days/)).toBeInTheDocument();
    });

    it('yanlış yazılmış bir ad sunucuya hiç gitmez', async () => {
        const fetchMock = stubFetch();
        render(<DataRightsRegion workspaceId={7} />);

        await screen.findByText(/netcup GmbH/);
        const callsAfterLoad = fetchMock.mock.calls.length;

        fireEvent.change(screen.getByLabelText(/type the workspace name/i), {
            target: { value: 'baska bir yer' },
        });
        fireEvent.click(screen.getByRole('button', { name: /ask for erasure/i }));

        expect(await screen.findByRole('alert')).toHaveTextContent(/not this workspace name/i);
        expect(fetchMock.mock.calls.length).toBe(callsAfterLoad);
    });

    it('hazır arşiv için indirme bağlantısı verir ve postanın hâlini söyler', async () => {
        stubFetch({
            ...BODY,
            requests: [
                {
                    id: 3,
                    kind: 'export',
                    state: 'ready',
                    requestedBy: 'sahip@zeytin.example',
                    requestedAt: '2026-09-08 09:00:00',
                    sectionCount: 3,
                    bytes: 4096,
                    availableUntil: '2026-09-15 09:00:00',
                    scheduledFor: null,
                    completedAt: '2026-09-08 09:01:00',
                    cancelledAt: null,
                    failed: false,
                    deletedRowTotal: 0,
                    // Posta YAPILANDIRILMAMIŞ: damga yok.
                    notifiedAt: null,
                    notificationFailed: false,
                    downloadUrl: 'https://example.test/signed',
                },
            ],
        });

        render(<DataRightsRegion workspaceId={7} />);

        const link = await screen.findByRole('link', { name: /download the archive/i });
        expect(link).toHaveAttribute('href', 'https://example.test/signed');
        expect(screen.getByText(/no e-mail was sent/i)).toBeInTheDocument();
    });

    it('planlanmış silmede vazgeçme düğmesi gösterir, onay kutusunu değil', async () => {
        stubFetch({
            ...BODY,
            requests: [
                {
                    id: 4,
                    kind: 'erasure',
                    state: 'scheduled',
                    requestedBy: 'sahip@zeytin.example',
                    requestedAt: '2026-09-08 09:00:00',
                    sectionCount: 40,
                    bytes: null,
                    availableUntil: null,
                    scheduledFor: '2026-09-22 09:00:00',
                    completedAt: null,
                    cancelledAt: null,
                    failed: false,
                    deletedRowTotal: 0,
                    notifiedAt: null,
                    notificationFailed: false,
                    downloadUrl: null,
                },
            ],
        });

        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText(/2026-09-22 09:00:00/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /take the request back/i })).toBeInTheDocument();
        expect(screen.queryByLabelText(/type the workspace name/i)).not.toBeInTheDocument();
    });

    it('yetkisi olmayana arıza değil, iznin kapalı olduğu söylenir', async () => {
        const fetchMock = vi.fn(async () => ({
            ok: false,
            status: 403,
            json: async () => ({ message: 'Forbidden.' }),
        }));
        vi.stubGlobal('fetch', fetchMock as unknown as typeof fetch);

        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText(/only the workspace owner/i)).toBeInTheDocument();
        // Kırmızı bir "yüklenemedi" satırı YOK: olmayan bir arıza
        // bildirilmez.
        expect(screen.queryByRole('button', { name: /try again/i })).not.toBeInTheDocument();
    });

    it('sunucu susarsa bölüm sessizce boş kalmaz', async () => {
        stubFetch(null, false);
        render(<DataRightsRegion workspaceId={7} />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
        });
    });

    it('geçmiş listesi talebin durumunu ve failini gösterir', async () => {
        stubFetch({
            ...BODY,
            requests: [
                {
                    id: 5,
                    kind: 'erasure',
                    state: 'completed',
                    requestedBy: 'sahip@zeytin.example',
                    requestedAt: '2026-09-01 09:00:00',
                    sectionCount: 40,
                    bytes: null,
                    availableUntil: null,
                    scheduledFor: '2026-09-01 09:00:00',
                    completedAt: '2026-09-01 09:05:00',
                    cancelledAt: null,
                    failed: false,
                    deletedRowTotal: 1240,
                    notifiedAt: null,
                    notificationFailed: false,
                    downloadUrl: null,
                },
            ],
        });

        render(<DataRightsRegion workspaceId={7} />);

        expect(await screen.findByText('erasure.completed')).toBeInTheDocument();
        // Silinen satır sayısı ekranda: "tamamlandı" damgası kontrol
        // edilebilir olmalı.
        expect(screen.getByText('1240')).toBeInTheDocument();
    });
});
