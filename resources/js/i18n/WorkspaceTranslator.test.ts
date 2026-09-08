import { afterEach, describe, expect, it } from 'vitest';

import { loadLocaleOverrides } from './generated-overrides';
import { t } from './workspace';

/**
 * MEDIA-C2 — çalışma alanı kataloğu çeviri zincirine BAĞLI mı?
 *
 * `workspace.ts` tek başına on üç modülü birleştirip anahtar çakışmasını
 * yakalıyordu, ama `t()` yalnız İngilizce tabanı okuyordu: üretilmiş
 * `generated/workspace.<locale>.json` projeksiyonları hiç sorulmuyordu.
 * Öteki alanlar (`menu`, `dashboard`, `auth`, `platform`, `theme`) çoktan
 * `createTranslator(en, overridesFor(alan))` kullanıyordu; çalışma alanı
 * kalmıştı.
 *
 * SAHİBİN GÖRDÜĞÜ: paneli Türkçeye alan restoran sahibi menüde, faturada,
 * kontrol panelinde Türkçe okuyordu — ama çalışma alanı ekranlarının
 * TAMAMI İngilizce kalıyordu. Çeviri eksik değildi; `workspace.tr.json`
 * bin sekiz yüzden fazla satırla diskte duruyordu ve hiç okunmuyordu.
 *
 * Üç uç dondurulur: İngilizce taban davranışı değişmez, var olan bir
 * çalışma alanı anahtarı Türkçeye döner, ve MEDIA-C2'de köprüden kataloğa
 * TAŞINAN sihirbaz anahtarı hem Türkçeye döner hem yer tutucusunu doldurur
 * (taşınan metnin çeviri boru hattına gerçekten girdiğinin kanıtı).
 */
afterEach(() => {
    document.documentElement.lang = '';
});

describe('workspace t() çeviri zinciri', () => {
    it('kaynak locale değişmez: İngilizce taban aynen döner', async () => {
        await loadLocaleOverrides('tr');
        document.documentElement.lang = 'en';

        expect(t('workspace.loading')).toBe('Loading your workspace…');
        expect(t('workspace.media.upload.optimize.savedNote', { before: '4 MB', after: '600 kB' })).toBe(
            '600 kB will be sent instead of 4 MB. That is data you do not pay for twice.',
        );
    });

    it('var olan bir çalışma alanı anahtarı Türkçe panelde Türkçe okunur', async () => {
        await loadLocaleOverrides('tr');
        document.documentElement.lang = 'tr';

        expect(t('workspace.loading')).toBe('Çalışma alanınız yükleniyor…');
    });

    it('MEDIA-C2 ile taşınan sihirbaz anahtarı Türkçeye döner ve yer tutucusunu doldurur', async () => {
        await loadLocaleOverrides('tr');
        document.documentElement.lang = 'tr';

        expect(t('workspace.media.upload.step.pick')).toBe('Seç');
        expect(t('workspace.media.upload.step.position', { step: '2', total: '4' })).toBe(
            'Adım 2 / 4',
        );
        expect(
            t('workspace.media.upload.optimize.savedNote', { before: '4 MB', after: '600 kB' }),
        ).toBe('4 MB yerine 600 kB gönderilecek. Bu kadar veriyi ikinci kez ödemek zorunda kalmazsınız.');
    });
});
