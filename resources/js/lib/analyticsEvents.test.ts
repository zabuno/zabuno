import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import {
    FORBIDDEN_KEYS,
    resetAnalyticsContext,
    setAnalyticsContext,
    trackEvent,
} from './analytics';
import {
    ANALYTICS_EVENTS,
    GA4_LIMITS,
    minutesSinceSignup,
    resetSignupAge,
    setSignupAgeMinutes,
    sizeBucket,
} from './analyticsEvents';

/**
 * ÖLÇÜM KAPISI (`docs/112` §7).
 *
 * Bu dosya taksonomiyi bir belge olmaktan çıkarıp zorlayıcı hâle getirir.
 * Kapının varlık sebebi tek cümledir: ölçümün en sık ölme biçimi yanlış ölçüm
 * değil, **tutarsız** ölçümdür. `menu_pubished` yazan bir satır hiçbir yerde
 * hata vermez; GTM sessizce ikinci bir olay açar ve o günden sonra iki
 * grafiğin hangisinin doğru olduğu bir daha söylenemez.
 *
 * Kullanıcı yolculuğu: bir restoran sahibi menüsünü yayınlar. Kapı olmasaydı,
 * "kaç sahip menüsünü yayınlıyor?" sorusunun cevabı, geliştiricinin o gün
 * hangi dizeyi yazdığına bağlı olurdu.
 */
describe('the measurement gate', () => {
    /** Kapının reddetmesi GEREKEN çağrılar tip düzeyinde zaten yazılamaz. */
    const trackUnchecked = trackEvent as unknown as (
        name: string,
        payload?: Record<string, unknown>,
    ) => void;

    function layer(): Array<Record<string, unknown>> {
        return (window as unknown as { dataLayer: Array<Record<string, unknown>> }).dataLayer;
    }

    beforeEach(() => {
        resetAnalyticsContext();
        resetSignupAge();
        (window as unknown as { dataLayer?: unknown[] }).dataLayer = [];
        setAnalyticsContext({ tenantId: '42', tenantSlug: 'acme' });
    });

    afterEach(() => {
        resetAnalyticsContext();
        resetSignupAge();
        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
    });

    describe('the taxonomy itself', () => {
        it('keeps every event name inside GA4 limits and in snake_case', () => {
            for (const name of Object.keys(ANALYTICS_EVENTS)) {
                expect(name.length, name).toBeLessThanOrEqual(GA4_LIMITS.eventName);
                expect(name, name).toMatch(/^[a-z][a-z0-9_]*$/);
            }
        });

        it('keeps every field name inside GA4 limits and in snake_case', () => {
            for (const [name, fields] of Object.entries(ANALYTICS_EVENTS)) {
                for (const field of fields) {
                    expect(field.length, `${name}.${field}`).toBeLessThanOrEqual(
                        GA4_LIMITS.fieldName,
                    );
                    expect(field, `${name}.${field}`).toMatch(/^[a-z][a-z0-9_]*$/);
                }
            }
        });

        /**
         * Yasak alanlar taksonomiye HİÇ giremez.
         *
         * Çalışma zamanı süzgeci son savunmadır; ilk savunma, kişisel veri
         * taşıyan bir alanın tabloda hiç yazılamamasıdır. `docs/112` §3.1–3.2
         * ve §5: misafirin arama terimi de bu listededir ve sunucuda kalır.
         */
        it('never lists a field name that the personal-data filter forbids', () => {
            for (const [name, fields] of Object.entries(ANALYTICS_EVENTS)) {
                for (const field of fields) {
                    for (const forbidden of FORBIDDEN_KEYS) {
                        expect(
                            field.includes(forbidden),
                            `${name}.${field} contains the forbidden token "${forbidden}"`,
                        ).toBe(false);
                    }
                }
            }
        });

        it('carries the friction and activation events docs/112 §6 puts first', () => {
            for (const required of [
                'form_validation_failed',
                'action_blocked',
                'upload_rejected',
                'empty_state_seen',
                'retry_clicked',
                'first_publish_completed',
            ]) {
                expect(Object.keys(ANALYTICS_EVENTS)).toContain(required);
            }
        });
    });

    describe('what cannot reach the dataLayer', () => {
        it('refuses an event name that is not in the taxonomy', () => {
            // Yazım hatası GTM'de yeni bir olay yaratırdı; burada durur.
            expect(() => trackUnchecked('menu_pubished', { change_count: 3 })).toThrow(
                /unknown event name/i,
            );

            expect(layer()).toHaveLength(0);
        });

        it('refuses a field the event does not declare', () => {
            expect(() => trackUnchecked('empty_state_seen', { sceen: 'menu' })).toThrow(
                /unknown field/i,
            );

            expect(layer()).toHaveLength(0);
        });

        it('refuses forbidden field names on any payload', () => {
            for (const forbidden of ['email', 'phone', 'full_name', 'token', 'search_term']) {
                expect(() => trackUnchecked('empty_state_seen', { [forbidden]: 'x' })).toThrow(
                    /personal data/i,
                );
            }

            // Yasaklı alan ADIN da içine gizlenemez: süzgeç alt dize arar.
            expect(() => trackUnchecked('empty_state_seen', { owner_email_hint: 'x' })).toThrow(
                /personal data/i,
            );

            expect(layer()).toHaveLength(0);
        });

        it('refuses a value longer than GA4 can store', () => {
            expect(() =>
                trackUnchecked('empty_state_seen', {
                    screen: 'x'.repeat(GA4_LIMITS.fieldValue + 1),
                }),
            ).toThrow(/exceeds/i);

            expect(layer()).toHaveLength(0);
        });

        /**
         * `docs/112` §3.4 — sözleşmenin en sert maddesi.
         *
         * "0 dakikada yayınladı" ile "ne zaman yayınladığını bilmiyoruz" aynı
         * grafikte toplanırsa ortalama yalan söyler. Bu yüzden değeri olmayan
         * alan `null`/`""` ile DEĞİL, hiç gönderilmeyerek ifade edilir.
         */
        it('refuses a made-up stand-in where a real value is missing', () => {
            expect(() => trackUnchecked('empty_state_seen', { screen: null })).toThrow(
                /empty stand-in/i,
            );
            expect(() => trackUnchecked('empty_state_seen', { screen: '' })).toThrow(
                /empty stand-in/i,
            );

            expect(layer()).toHaveLength(0);
        });
    });

    describe('what a well-formed event looks like', () => {
        it('drops an unknown field value instead of inventing one', () => {
            trackEvent('first_publish_completed', {
                item_count: 12,
                minutes_since_signup: undefined,
            });

            expect(layer()).toHaveLength(1);
            expect(layer()[0]).toMatchObject({ event: 'first_publish_completed', item_count: 12 });
            expect(Object.keys(layer()[0])).not.toContain('minutes_since_signup');
        });

        it('still attaches the tenant to a taxonomy event', () => {
            trackEvent('empty_state_seen', { screen: 'menu' });

            expect(layer()[0]).toMatchObject({
                event: 'empty_state_seen',
                screen: 'menu',
                zabuno_tenant_id: '42',
                zabuno_tenant_slug: 'acme',
            });
        });
    });

    describe('minutes_since_signup', () => {
        /**
         * Sunucu SÜRE gönderir, saat değil — çünkü iki zaman damgasının farkı
         * tarayıcı saatinin doğru olmasını gerektirir ve o saat kullanıcının
         * kendi ayarıdır. Yanlış saatli tek bir dizüstü "-180 dakika" üretir.
         */
        it('is absent until the server has told us how old the account is', () => {
            expect(minutesSinceSignup()).toBeUndefined();
        });

        it('starts from the age the server measured', () => {
            setSignupAgeMinutes(7);

            // Sayfa yeni açıldığı için geçen süre ~0; sunucunun sayısı kalır.
            expect(minutesSinceSignup()).toBe(7);
        });

        it('ignores a nonsensical age instead of publishing it', () => {
            setSignupAgeMinutes(Number.NaN);
            expect(minutesSinceSignup()).toBeUndefined();

            setSignupAgeMinutes(-5);
            expect(minutesSinceSignup()).toBeUndefined();
        });
    });

    describe('size buckets', () => {
        // Ham bayt GA4'te raporlanamaz; kimse "7.318.402 bayt" okumaz.
        it('reports a readable bucket rather than the raw byte count', () => {
            expect(sizeBucket(500_000)).toBe('<1mb');
            expect(sizeBucket(3 * 1_048_576)).toBe('1-5mb');
            expect(sizeBucket(7_318_402)).toBe('5-15mb');
            expect(sizeBucket(40 * 1_048_576)).toBe('15mb+');
        });
    });
});
