import { describe, expect, it } from 'vitest';

import {
    BULK_ACTION_GROUPS,
    confirmSatisfied,
    groupResults,
    skipReasonKey,
    type MediaBulkPlan,
} from './mediaBulk';

/**
 * TOPLU İŞLEM SİHİRBAZININ SAF KURALLARI — kanonik kaynak
 * `docs/reference/panel-v3/MedyaModulu.dc.html`, "Toplu işlem".
 *
 * Bu kurallar bileşenden AYRI durur, çünkü hepsi bir karardır ve karar
 * çizimden bağımsız sınanmalı: "kelime doğru mu", "bu sebep hangi cümle",
 * "sonuç listesi nasıl süzülür". Bileşene gömülmüş olsalardı, her birini
 * sınamak için bir ekran çizmek gerekirdi.
 */
describe('mediaBulk', () => {
    function plan(overrides: Partial<MediaBulkPlan> = {}): MediaBulkPlan {
        return {
            action: 'purge',
            allowed: true,
            requiredPermission: null,
            scope: { kind: 'workspace', count: 3, totalBytes: 3072 },
            snapshot: { assetIds: [1, 2, 3] },
            applyCount: 3,
            batchLimit: 200,
            remaining: 0,
            skips: [],
            skippedAssets: [],
            impact: {
                reversible: false,
                undoWindowDays: null,
                newVersion: false,
                quotaBytesUsed: 0,
                quotaBytesLimit: 0,
                quotaBytesFreed: 3072,
            },
            confirmation: { required: true, word: 'KALICI SİL' },
            ...overrides,
        };
    }

    it('kelime TAM eşleşmedikçe yıkıcı işi açmaz', () => {
        /*
            Küçük/büyük harf esnekliği tanımak, "kalıcı sil" yazan birinin
            bin dosyayı kaybetmesi demek olurdu. Türkçede `toUpperCase`
            zaten `i` → `İ` vermez; esneklik denemek sessiz bir hataya
            dönerdi.
        */
        expect(confirmSatisfied(plan(), 'kalıcı sil')).toBe(false);
        expect(confirmSatisfied(plan(), 'KALICI SIL')).toBe(false);
        expect(confirmSatisfied(plan(), '  KALICI SİL  ')).toBe(true);
    });

    it('onay istenmeyen işte kelime aranmaz', () => {
        const noConfirm = plan({ confirmation: { required: false, word: null } });
        expect(confirmSatisfied(noConfirm, '')).toBe(true);
    });

    it('uygulanacak dosya yoksa iş hiç açılmaz', () => {
        // Sıfır dosyalık bir işi başlatmak, sahibi "çalıştı mı?" diye
        // ekranın önünde bekletirdi.
        expect(confirmSatisfied(plan({ applyCount: 0 }), 'KALICI SİL')).toBe(false);
    });

    it('her atlama sebebinin bir cümlesi vardır', () => {
        const reasons = [
            'quarantine',
            'legal-hold',
            'published-usage',
            'unsupported-format',
            'already-done',
            'not-in-trash',
        ];

        for (const reason of reasons) {
            expect(skipReasonKey(reason)).toContain('workspace.media.bulk.skip.');
        }

        // Tanınmayan bir sebep SESSİZ kalmaz: sunucu yeni bir sebep
        // eklediğinde ekran boş bir satır değil, dürüst bir yedek cümle
        // gösterir.
        expect(skipReasonKey('brand-new-reason')).toBe('workspace.media.bulk.skip.unknown');
    });

    it('sonuçları duruma göre sayar ve yalnız hatalıları ayırır', () => {
        const results = [
            { id: 1, name: 'a.jpg', status: 'ok' as const, reason: null },
            { id: 2, name: 'b.jpg', status: 'skip' as const, reason: 'legal-hold' },
            { id: 3, name: 'c.jpg', status: 'error' as const, reason: 'convert-failed' },
            { id: 4, name: 'd.jpg', status: 'error' as const, reason: 'convert-failed' },
        ];

        const counts = groupResults(results);

        expect(counts.all).toBe(4);
        expect(counts.ok).toBe(1);
        expect(counts.skip).toBe(1);
        expect(counts.error).toBe(2);
        // "Yalnız hatalıları yeniden dene" tam olarak bu kimliklerle çalışır:
        // başarılı olanlara ikinci kez dokunmak yeni sürüm açardı.
        expect(counts.errorIds).toEqual([3, 4]);
    });

    it('eylem grupları kaynağın üç başlığını korur ve yalnız gerçek eylemleri taşır', () => {
        expect(BULK_ACTION_GROUPS.map((group) => group.key)).toEqual([
            'improve',
            'organize',
            'remove',
        ]);

        const actions = BULK_ACTION_GROUPS.flatMap((group) =>
            group.actions.map((one) => one.action),
        );

        // Kaynakta olup üründe VERİSİ olmayanlar burada YOKTUR
        // (`MediaBulkAction` gerekçeleri): regen, alt, tag, access, archive.
        expect(actions).toEqual(['optimize', 'convert', 'move', 'trash', 'purge']);
    });
});
