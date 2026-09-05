import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import {
    markAnalyticsSurfaceAnonymous,
    resetAnalyticsContext,
    setAnalyticsContext,
    trackEvent,
    trackPageView,
} from './analytics';

/**
 * ANALYTICS-TENANT-SEAM (istemci tarafı).
 *
 * Sahibin kilit kuralı: her şey tenant bazında analiz edilebilmeli. Burada
 * dondurulan şey, o kuralın sessizce kırıldığı üç durumdur.
 */
describe('the dataLayer seam', () => {
    function layer(): Array<Record<string, unknown>> {
        return (window as unknown as { dataLayer: Array<Record<string, unknown>> }).dataLayer;
    }

    beforeEach(() => {
        resetAnalyticsContext();
        (window as unknown as { dataLayer?: unknown[] }).dataLayer = [];
    });

    afterEach(() => {
        resetAnalyticsContext();
        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
    });

    it('drops events silently when measurement is switched off', () => {
        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;

        // Yerel geliştirme ve test bu durumdadır. Ölçüm kapalı olduğu için
        // patlamak yanlış olurdu: ürünün çalışması ölçüme bağlı değildir.
        expect(() => trackPageView('/app/acme/menu', 'menu')).not.toThrow();
    });

    it('holds events until the tenant is known, then releases them with the tenant attached', () => {
        // Panele girişte workspace henüz API'den dönmemiştir. Olayı o an
        // göndermek, ziyaretin İLK sayfasını hiçbir restorana ait olmayan
        // bir satır yapardı — ve o satır her zaman aynı sayfadır.
        trackPageView('/app/acme/dashboard', 'dashboard');

        expect(layer()).toHaveLength(0);

        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });

        expect(layer()).toHaveLength(1);
        expect(layer()[0]).toMatchObject({
            event: 'page_view',
            page_path: '/app/acme/dashboard',
            zabuno_tenant_id: '42',
            zabuno_tenant_slug: 'acme',
        });
    });

    it('attaches the tenant to every later event without the caller repeating it', () => {
        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });

        trackEvent('menu_item_added', { item_count: 7 });
        trackPageView('/app/acme/menu', 'menu');

        expect(layer()).toHaveLength(2);
        for (const entry of layer()) {
            expect(entry.zabuno_tenant_id).toBe('42');
            expect(entry.zabuno_tenant_slug).toBe('acme');
        }
    });

    it('refuses to carry personal data into the dataLayer', () => {
        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });

        // dataLayer'ın içeriği GTM üzerinden üçüncü taraflara akar; oraya
        // giren veri geri alınamaz. Bu yüzden hata geliştirme sırasında
        // ÇIKAR — üretimde sessizce düşürmek yerine burada durdurulur.
        // Kişisel veri kontrolü taksonomi kontrolünden ÖNCE koşar: bir yükün
        // ilk suçu taksonomi değil, mahremiyet ihlalidir ve mesaj bunu
        // söylemelidir. Tip düzeyinde yazılamayan çağrı burada zorlanır.
        const trackUnchecked = trackEvent as unknown as (
            name: string,
            payload: Record<string, unknown>,
        ) => void;

        expect(() => trackUnchecked('team_invited', { email: 'ada@example.com' })).toThrow(
            /personal data/i,
        );
        expect(() => trackUnchecked('team_invited', { full_name: 'Ada' })).toThrow(
            /personal data/i,
        );

        expect(layer()).toHaveLength(0);
    });

    it('never lets the waiting queue grow without a bound', () => {
        // Tenant hiç gelmeyebilir (kullanıcının workspace'i yoksa). Ölçüm
        // hiçbir koşulda belleği büyütmemeli.
        for (let index = 0; index < 200; index += 1) {
            trackEvent('empty_state_seen', { screen: `screen-${index}` });
        }

        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });

        expect(layer().length).toBeLessThanOrEqual(50);
    });

    it('reports the same path the server sees, so the three sources can be reconciled', () => {
        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });

        trackPageView('/app/acme/publication', 'publication');

        // Fragment DEĞİL: sunucu günlüğü, GA4 raporu ve Metabase sorgusu
        // aynı satırı gösterebilmeli.
        expect(layer()[0].page_path).toBe('/app/acme/publication');
        expect(String(layer()[0].page_path)).not.toContain('#');
    });
});

describe('anonim yüzey — kiracı yokken olay DÜŞMEZ', () => {
    /*
        Kusur "bağlam gelmedi" değil, VARSAYIMDI: olay basmanın kiracı
        gerektirdiği varsayılmıştı. Kayıt ekranında kiracı hiç gelmez, ve
        eskiden orada basılan her olay kuyrukta bekleyip sayfa değişince
        sessizce düşerdi — sürtünme ölçümünün en değerli noktası, insanların
        ürüne girmeden vazgeçtiği yer, hiç ölçülmüyordu.
    */
    function anonLayer(): Array<Record<string, unknown>> {
        return (window as unknown as { dataLayer: Array<Record<string, unknown>> }).dataLayer;
    }

    beforeEach(() => {
        resetAnalyticsContext();
        (window as unknown as { dataLayer?: unknown[] }).dataLayer = [];
    });

    afterEach(() => {
        resetAnalyticsContext();
        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
    });

    it('anonim işaretinden sonra olay dataLayer’a ULAŞIR', () => {
        markAnalyticsSurfaceAnonymous();
        trackEvent('empty_state_seen', { screen: 'register' });

        expect(anonLayer()).toHaveLength(1);
    });

    it('uydurma kiracı kimliği BASMAZ', () => {
        markAnalyticsSurfaceAnonymous();
        trackEvent('empty_state_seen', { screen: 'register' });

        // Var olmayan bir kiracı yaratmaktansa alanı hiç göndermemek.
        expect(anonLayer()[0].zabuno_tenant_id).toBeUndefined();
        expect(anonLayer()[0].zabuno_tenant_slug).toBeUndefined();
    });

    it('işaret KONMADAN olay hâlâ kuyrukta bekler', () => {
        trackEvent('empty_state_seen', { screen: 'register' });

        // "Bağlam henüz gelmedi" ile "gelmeyecek" farklı iki durumdur.
        expect(anonLayer()).toHaveLength(0);
    });
});
