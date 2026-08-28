import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * SAYFA GENİŞLİĞİ muhafızı — `docs/58`.
 *
 * Gözlenen hâl: hiçbir çalışma alanı sayfası genişlik SINIRI taşımıyordu.
 * 1920 piksellik bir ekranda marka formu 1900 piksel oluyor, dört sütun
 * ekranın iki ucuna dağılıyor ve "Edit" düğmesi içeriğin metrelerce uzağında
 * kalıyordu.
 *
 * Bu, akışkanlığın doğal sonucu değil, sınır konmamasının sonucudur.
 * Akışkanlık "dar ekranda kırılma" demektir; "geniş ekranda sonsuza kadar
 * gerilme" demek değil.
 *
 * Kusur sayfa EKLEME anında değil, SONRAKİ sayfa yazıldığında geri gelir:
 * yeni sayfayı yazan kişi genişlik bildirmeyi unutur, hiçbir test düşer (sayfa
 * zaten çalışmaktadır) ve o tek sayfa sessizce eski davranışa döner.
 */

const PAGES_DIR = path.dirname(fileURLToPath(import.meta.url));

const PAGE_FILES = globSync('*.tsx', { cwd: PAGES_DIR })
    .filter((file) => !file.includes('.test.') && !file.includes('.section.'))
    .map((file) => path.join(PAGES_DIR, file))
    .filter((file) => readFileSync(file, 'utf8').includes('<WorkspacePageFrame'));

describe('sayfa genişliği (docs/58)', () => {
    it('çerçeve kullanan sayfa bulunmadan geçmez', () => {
        expect(PAGE_FILES.length).toBeGreaterThanOrEqual(8);
    });

    it('çerçeveyi kullanan her sayfa genişliğini BİLDİRİR', () => {
        const offenders = PAGE_FILES.filter(
            (file) => !readFileSync(file, 'utf8').includes('measure='),
        ).map((file) => path.basename(file));

        expect(
            offenders,
            'SAYFA GENİŞLİĞİ: genişlik bildirmeyen sayfa bulundu. Varsayılan ' +
                '`standard` olsa bile bildirim AÇIK olmalı: bir tablo sayfasının ' +
                'standart genişlikte kalması bir karar olabilir, ama unutulmuş ' +
                'olması bir kusurdur ve ikisi kodda ayırt edilemez.',
        ).toEqual([]);
    });

    /**
     * Tipografik ölçü ile sayfa genişliği AYRI sorulardır.
     *
     * `--container-content` METİN içindir — satır başına 45–75 karakter
     * okunabilirlik kuralı, ölçüldüğünde 726 piksel. Bir paragrafı onunla
     * sınırlamak DOĞRUDUR ve bu muhafız onu engellemez.
     *
     * Yanlış olan, aynı jetonu sayfa KABUĞUNA vermektir: bir tablo sayfasını
     * 726 piksele sıkıştırmak sütunları ezer, bir formu sıkıştırmak ise
     * tesadüfen doğru görünür ve yazı tipi değiştiğinde sessizce kayar.
     *
     * Kural bu yüzden metin öğelerini muaf tutar; yalnız düzen kapsayıcısında
     * kullanımı yakalar.
     */
    it('sayfa KABUĞUNDA tipografik ölçü jetonu kullanılmaz', () => {
        const offenders: string[] = [];
        // Metin öğesi: kendi satırında bir tipografi sınıfı taşır.
        const TEXT_ELEMENT = /\btext-(body|meta|section|title|subsection|fg)/;

        for (const file of PAGE_FILES) {
            for (const line of readFileSync(file, 'utf8').split('\n')) {
                if (TEXT_ELEMENT.test(line)) continue;
                if (/max-w-(content|form|table)(?![a-z-])/.test(line)) {
                    offenders.push(`${path.basename(file)}: ${line.trim().slice(0, 60)}`);
                }
            }
        }

        expect(
            offenders,
            'SAYFA GENİŞLİĞİ: düzen kapsayıcısında tipografik ölçü jetonu ' +
                'kullanılmış. Metin ölçüsü (`ch`) ile sayfa genişliği (`rem`) ' +
                'ayrı sorulardır — sayfa genişliği için `measure` kullan.',
        ).toEqual([]);
    });
});
