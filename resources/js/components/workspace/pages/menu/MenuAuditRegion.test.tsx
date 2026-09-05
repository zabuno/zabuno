import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

import { MenuAuditRegion } from './MenuAuditRegion';

/**
 * FF-163 — menü değişiklik geçmişi ekranı.
 *
 * Bu dosyanın sorduğu tek soru: *"dün kebabın fiyatını kim değiştirdi?"*
 * sorusunun cevabı EKRANDA OKUNUYOR MU? Yazma yolunun ve okuma ucunun kendi
 * sözleşmeleri PHP tarafında donduruldu; burada donan şey, satırın dört
 * parçasının (kim · ne zaman · neyi · neyden neye) sahibin gözüne nasıl
 * geldiğidir.
 *
 * Gereksinim: MENU-AUDIT-SCREEN-READABLE-01, MENU-AUDIT-SCREEN-DELETED-02,
 * MENU-AUDIT-SCREEN-AI-03, MENU-AUDIT-SCREEN-HONEST-04,
 * MENU-AUDIT-SCREEN-APPEND-ONLY-05, MENU-AUDIT-SCREEN-PAGE-06.
 */

const WORKSPACE_ID = 7;

type Row = {
    id: number;
    action: string;
    subjectType: string;
    subjectId: number;
    subjectLabel: string | null;
    before: string | null;
    after: string | null;
    actor: string | null;
    at: string | null;
    timeZone: string | null;
};

function row(overrides: Partial<Row> = {}): Row {
    return {
        id: 1,
        action: 'item_price_changed',
        subjectType: 'menu_item',
        subjectId: 42,
        subjectLabel: 'Adana Kebap',
        before: '380.00 TRY',
        after: '420.00 TRY',
        actor: 'mehmet@zeytin.example',
        at: '2026-09-04T15:41:00Z',
        timeZone: 'Europe/Istanbul',
        ...overrides,
    };
}

function respondWith(body: { data: Row[]; page: number; pageCount: number }) {
    return vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(body),
    } as unknown as Response);
}

beforeEach(() => {
    vi.stubGlobal('fetch', respondWith({ data: [row()], page: 1, pageCount: 1 }));
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('menü değişiklik geçmişi', () => {
    it('bir fiyat değişikliğini kim · ne zaman · neyi · neyden neye olarak okutur', async () => {
        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        // NEYİ oldu ve NEYİN üstünde.
        expect(await screen.findByText('Price changed')).toBeInTheDocument();
        expect(screen.getByText('Adana Kebap')).toBeInTheDocument();

        // NEYDEN NEYE — "öncesi" olmadan satır sahibin sorusunu kapatmaz.
        expect(screen.getByText('380.00 TRY → 420.00 TRY')).toBeInTheDocument();

        // KİM — e-postayla, çünkü bir ekipte iki "Mehmet" olabilir.
        expect(screen.getByText('mehmet@zeytin.example')).toBeInTheDocument();
    });

    /**
     * ZAMAN ŞUBENİN SAATİYLE OKUNUR.
     *
     * Sunucu mutlak bir an gönderir (15:41 UTC); Berlin şubesinin duvar
     * saatinde bu 17:41'dir. Sabit bir dilime düşülseydi sahip yanlış
     * vardiyayı arardı — bu depoda aynı hata zamanlanmış yayında bir kez
     * çıktı (`docs/62`).
     */
    it('anı şubenin duvar saatiyle ve hangi saat olduğunu yazarak gösterir', async () => {
        vi.stubGlobal(
            'fetch',
            respondWith({
                data: [row({ timeZone: 'Europe/Berlin' })],
                page: 1,
                pageCount: 1,
            }),
        );

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        const stamp = await screen.findByText(/17:41/);

        expect(stamp).toBeInTheDocument();
        // Dilim satırın yanında yazılır: iki şubeli bir işletmede "17:41"
        // tek başına hangi şehrin saati olduğunu söylemez.
        expect(stamp.textContent).toMatch(/GMT|UTC/);
    });

    /**
     * SİLİNMİŞ ÜRÜNÜN SATIRI DA OKUNUR.
     *
     * İzin en değerli olduğu an, ürünün artık menüde olmadığı andır. Kayıt
     * olay anındaki adı saklıyor; ekran "137 numaralı ürün" demez.
     */
    it('silinmiş bir ürünü kimliğiyle değil adıyla okutur', async () => {
        vi.stubGlobal(
            'fetch',
            respondWith({
                data: [
                    row({
                        action: 'item_removed',
                        subjectLabel: 'Mercimek Çorbası',
                        subjectId: 137,
                        before: '90.00 TRY',
                        after: null,
                    }),
                ],
                page: 1,
                pageCount: 1,
            }),
        );

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('Item removed')).toBeInTheDocument();
        expect(screen.getByText('Mercimek Çorbası')).toBeInTheDocument();
        expect(screen.queryByText(/137/)).not.toBeInTheDocument();
    });

    /**
     * "BUNU BEN Mİ YAZDIM YOKSA MAKİNE Mİ OKUDU?"
     *
     * CSV'deki sayıyı insan yazdı, fotoğraftakini bir model OKUDU. Ayrım
     * RENKLE değil KELİMEYLE yapılır.
     */
    it('fotoğraftan gelen aktarımı CSV aktarımından kelimeyle ayırır', async () => {
        vi.stubGlobal(
            'fetch',
            respondWith({
                data: [
                    row({ id: 1, action: 'menu_ai_imported', before: null, after: '9 satır' }),
                    row({ id: 2, action: 'menu_imported', before: null, after: '12 satır' }),
                ],
                page: 1,
                pageCount: 1,
            }),
        );

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('Applied from a photo reading')).toBeInTheDocument();
        expect(screen.getByText('Imported from a CSV file')).toBeInTheDocument();
    });

    /**
     * KAYIT YOKSA UYDURULMAZ.
     *
     * Sıfır, sahte satır ya da tahmini süre yok: yazılan tek cümle "henüz
     * bir değişiklik kaydedilmedi".
     */
    it('kayıt yokken sahte satır değil, olmadığını yazar', async () => {
        vi.stubGlobal('fetch', respondWith({ data: [], page: 1, pageCount: 1 }));

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('No change has been recorded yet.')).toBeInTheDocument();
        expect(screen.queryByRole('listitem')).not.toBeInTheDocument();
    });

    /**
     * KAYDEDİLMEYENLER DE YAZILIR.
     *
     * Sıralama, "bugün tükendi" ve yayınlama bilerek ize yazılmıyor. Bunu
     * söylemeyen bir liste, olmayan bir kaydı "olmadı" diye okutur: sahip
     * yayını göremeyince "menü hiç yayına çıkmamış" der.
     */
    it('neyin kaydedilmediğini de söyler', async () => {
        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        await screen.findByText('Price changed');

        expect(
            screen.getByText(
                'Not recorded here: reordering, sold-out marks, and publishing. Publishing keeps its own history on the publish screen.',
            ),
        ).toBeInTheDocument();
    });

    /**
     * DURUM KELİMEYLE YAZILIR.
     *
     * Görünürlük kayıtta `visible`/`hidden` olarak durur. "1 → 0" ya da bir
     * renk noktası okunmaz; ekran kelimeyi yazar.
     */
    it('görünürlüğü kelimeyle yazar', async () => {
        vi.stubGlobal(
            'fetch',
            respondWith({
                data: [
                    row({
                        action: 'item_visibility_changed',
                        before: 'visible',
                        after: 'hidden',
                    }),
                ],
                page: 1,
                pageCount: 1,
            }),
        );

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('Visible → Hidden')).toBeInTheDocument();
    });

    /**
     * SAHİBİN KENDİ METNİ ÇEVRİLMEZ.
     *
     * Çeviri EYLEME bağlıdır, değere değil: adı "hidden" olan bir ürünün
     * yeniden adlandırma satırı, sistemin sözlüğüyle değiştirilmiş bir
     * kayda dönüşemez.
     */
    it('ürün adını görünürlük sözlüğüyle değiştirmez', async () => {
        vi.stubGlobal(
            'fetch',
            respondWith({
                data: [row({ action: 'item_renamed', before: 'hidden', after: 'Adana Kebap' })],
                page: 1,
                pageCount: 1,
            }),
        );

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(await screen.findByText('hidden → Adana Kebap')).toBeInTheDocument();
    });

    /**
     * GEÇMİŞ SİLİNMEZ VE DÜZENLENMEZ.
     *
     * Düzeltilebilen bir denetim izi denetim izi değildir; ekranda böyle bir
     * yol hiç olmamalı.
     */
    it('hiçbir silme ya da düzenleme yolu sunmaz', async () => {
        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        await screen.findByText('Price changed');

        for (const control of screen.queryAllByRole('button')) {
            expect(control.textContent ?? '').not.toMatch(
                /delete|remove|edit|clear|sil|düzenle|kaldır/i,
            );
        }
    });

    /**
     * İZ BÜYÜR — sayfalanır.
     *
     * Tek sayfalık bir listede "sonraki" düğmesi hiçbir yere götürmez, o
     * yüzden hiç çizilmez.
     */
    it('tek sayfada sayfa kontrolü çizmez, çok sayfada sonraki sayfayı ister', async () => {
        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);
        await screen.findByText('Price changed');
        expect(screen.queryByText('Next')).not.toBeInTheDocument();

        const many = respondWith({ data: [row()], page: 1, pageCount: 3 });
        vi.stubGlobal('fetch', many);

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        const next = await screen.findByText('Next');
        expect(screen.getByText('Page 1 / 3')).toBeInTheDocument();

        fireEvent.click(next);

        await waitFor(() => {
            expect(many).toHaveBeenCalledWith(
                `/api/workspaces/${String(WORKSPACE_ID)}/menu/audits?page=2`,
                expect.anything(),
            );
        });
    });

    /**
     * Uç okunamazsa bölüm SUSMAZ.
     *
     * Boş bir liste çizmek, kaydın tutulmadığını sanmaya yol açardı; hata
     * kendi cümlesini ve çalışan bir yeniden denemeyi taşır.
     */
    it('uç okunamazsa hatayı söyler ve yeniden dener', async () => {
        const failing = vi.fn().mockResolvedValue({ ok: false } as unknown as Response);
        vi.stubGlobal('fetch', failing);

        render(<MenuAuditRegion workspaceId={WORKSPACE_ID} />);

        expect(
            await screen.findByText('The change history could not be loaded.'),
        ).toBeInTheDocument();

        fireEvent.click(screen.getByText('Try again'));

        await waitFor(() => {
            expect(failing).toHaveBeenCalledTimes(2);
        });
    });
});
