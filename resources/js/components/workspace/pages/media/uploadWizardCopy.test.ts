import { describe, expect, it } from 'vitest';

import { workspaceTranslations } from '../../../../i18n/workspace';
import { pendingCopyKeys, wizardText } from './uploadWizardCopy';

/**
 * METİN KÖPRÜSÜ — kendi kendini silen bir geçiş.
 *
 * `resources/js/i18n/workspace/media.ts` bu pakette başka bir yazarın
 * dosyasıdır; sihirbazın yeni anahtarları oraya ayrıca bildirildi. O gelene
 * kadar iki kötü seçenek vardı: olmayan anahtarı `t()` ile çağırıp ekranda
 * `workspace.media.upload.step.pick` göstermek, ya da metni bileşene gömüp
 * çeviri zincirini koparmak.
 *
 * Bu testler köprünün İKİ ucunu birden donduruyor: bugün ekranda gerçek
 * cümle görünüyor, yarın anahtar kataloğa eklendiğinde çeviri sessizce
 * devralıyor. İkincisi olmasaydı, eklenen çeviri hiç görünmeden ölürdü ve
 * bunu kimse fark etmezdi.
 */
describe('wizardText', () => {
    it('anahtar KATALOGDA varsa katalog kazanır', () => {
        expect('workspace.media.upload.button' in workspaceTranslations).toBe(true);
        expect(wizardText('workspace.media.upload.button')).toBe(
            workspaceTranslations['workspace.media.upload.button'],
        );
    });

    it('katalogda olmayan anahtar için ANAHTARI değil cümleyi verir', () => {
        // Ekranda iç anahtar adı görmek, bu deponun kelime dağarcığı
        // muhafızının tam olarak kapattığı arızadır.
        for (const key of pendingCopyKeys()) {
            const text = wizardText(key);

            expect(text, `"${key}" ham anahtar olarak ekrana çıkıyor`).not.toBe(key);
            expect(text.startsWith('workspace.')).toBe(false);
        }
    });

    it('değişkenleri doldurur', () => {
        expect(wizardText('workspace.media.upload.optimize.saved', { percent: '86' })).toBe(
            '86% smaller',
        );
    });

    it('hiç tanımadığı anahtarı uydurmaz', () => {
        // Son çare olarak anahtarın kendisi döner: sessizce boş bir satır
        // çizmek, eksikliği görünmez yapardı.
        expect(wizardText('workspace.media.upload.nothing.here')).toBe(
            'workspace.media.upload.nothing.here',
        );
    });
});
