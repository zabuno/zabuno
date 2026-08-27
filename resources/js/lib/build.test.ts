import { afterEach, describe, expect, it, vi } from 'vitest';
import { detectDivergence, isBannerEnabled, shortRevision } from './build';

/**
 * Preview Truth'un istemci yarısı (docs/52).
 *
 * Buradaki testlerin ağırlığı "ayrışmayı yakalıyor mu"dan çok "yakalamadığı
 * yerde SUSUYOR mu" üzerinde. Sebebi şu: sürekli yanlış alarm veren bir uyarı
 * kapatılan bir uyarıdır, ve kapatıldığı andan itibaren gerçek ayrışmayı da
 * göstermez. Yani yanlış alarm, dedektörü kaybetmenin yoludur.
 */
function setMeta(name: string, content: string): void {
    const element = document.createElement('meta');
    element.setAttribute('name', name);
    element.setAttribute('content', content);
    document.head.append(element);
}

afterEach(() => {
    document.head.querySelectorAll('meta[name^="zabuno-build"]').forEach((node) => node.remove());
    vi.unstubAllGlobals();
});

describe('build divergence (docs/52)', () => {
    it('sunucu ve paket aynı sürümdeyse hiçbir iddiada bulunmaz', () => {
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'false');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'a'.repeat(40));

        expect(detectDivergence()).toEqual({ kind: 'fresh' });
    });

    it('sürümler farklıysa hangi ikisinin farklı olduğunu söyler', () => {
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'false');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'b'.repeat(40));

        expect(detectDivergence()).toEqual({
            kind: 'revision-mismatch',
            served: 'a'.repeat(40),
            running: 'b'.repeat(40),
        });
    });

    /**
     * Bu, sürüm karşılaştırmasının YAPISAL olarak göremediği durum: kaynak
     * düzenlenmiş ama derlenmemiştir. Commit oluşmadığı için iki taraf da
     * aynı SHA'yı söyler ve sürüm kontrolü "temiz" der. Ayrı bir sinyal
     * olması bu yüzden şart.
     */
    it('sürümler EŞİTKEN bile derlemenin bayat olduğunu bildirir', () => {
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'true');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'a'.repeat(40));

        expect(detectDivergence()).toEqual({ kind: 'stale-build' });
    });

    /**
     * Taraflardan biri bilinmiyorsa karşılaştırma yapılmaz. Bilinmeyeni
     * "farklı" saymak her kurulumda alarm verirdi.
     */
    it('paket sürümü bilinmiyorken ayrışma iddia etmez', () => {
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'false');
        // Boş dize = git yoktu, sürüm gömülemedi. Uydurulmuş bir değer
        // değil, bilinmediğinin açık ifadesi (vite.config.ts).
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', '');

        expect(detectDivergence()).toEqual({ kind: 'fresh' });
    });

    it('sunucu sürümü boşken ayrışma iddia etmez', () => {
        setMeta('zabuno-build-revision', '');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'b'.repeat(40));

        expect(detectDivergence()).toEqual({ kind: 'fresh' });
    });

    it('şerit yalnız sunucu açıkça izin verdiğinde etkindir', () => {
        expect(isBannerEnabled()).toBe(false);

        setMeta('zabuno-build-banner', 'true');
        expect(isBannerEnabled()).toBe(true);
    });

    it('sürümü git ile aynı uzunlukta kısaltır', () => {
        expect(shortRevision('0123456789abcdef')).toBe('0123456');
    });
});
