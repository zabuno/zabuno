import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Durum rozetine KİMLİK veya SAYAÇ konmaz — `docs/53`.
 *
 * Panelde bulunan hâl: sekiz sayfanın beşinde rozet ya veritabanı birincil
 * anahtarını (`#3`) ya da bir sayacı `#` ile kimlik kılığında gösteriyordu.
 * Lokasyonlar sayfasında rozet `#1` diyordu ve hemen altında zaten "1
 * locations" yazıyordu — yani daha kötü biçimde tekrar.
 *
 * Bunlar kötü niyetle konmamıştı: "veri gerçekten yüklendi mi" sorusunu
 * gözle doğrulamak için konmuştu. Ama geliştiricinin kanıt ihtiyacı, restoran
 * sahibinin ekranında kalıcı bir öğeye dönüştü.
 *
 * Kural: rozet, kullanıcının BİLMEDİĞİ ve hakkında bir şey YAPABİLECEĞİ bir
 * durumu bildirir. "Yüklendi" bunun ikisi de değildir — sayfanın içeriği zaten
 * kanıtıdır. Her sayfada bir "her şey yolunda" rozeti bulunması rozetlerin
 * tamamını okunmayan süse çevirir; asıl bedel o noktadan sonra GERÇEK uyarının
 * da fark edilmemesidir.
 */

const PAGES_DIR = path.dirname(fileURLToPath(import.meta.url));

const PAGE_FILES = globSync('*.tsx', { cwd: PAGES_DIR })
    .filter((file) => !file.includes('.test.'))
    .map((file) => path.join(PAGES_DIR, file));

/** `label: \`#${...}\`` — kimlik ya da sayaç, ikisi de yasak. */
const IDENTIFIER_LABEL = /label:\s*`#\$\{/;

/** `label: x.state` gibi çevrilmemiş ham alan. */
const RAW_FIELD_LABEL = /label:\s*[a-zA-Z]+\.[a-zA-Z]+,/;

describe('durum rozetleri (docs/53)', () => {
    it('sayfa bulunmadan geçmez', () => {
        expect(PAGE_FILES.length).toBeGreaterThan(5);
    });

    it('hiçbir rozet veritabanı kimliği ya da sayaç göstermez', () => {
        const offenders: string[] = [];

        for (const file of PAGE_FILES) {
            const source = readFileSync(file, 'utf8');

            for (const line of source.split('\n')) {
                if (IDENTIFIER_LABEL.test(line)) {
                    offenders.push(`${path.basename(file)}: ${line.trim().slice(0, 60)}`);
                }
            }
        }

        expect(
            offenders,
            'ROZET KURALI: rozet etiketine kimlik ya da sayaç konmuş. Bir ' +
                'veritabanı anahtarı kullanıcı için anlamsızdır ve sayaç zaten ' +
                'sayfanın içeriğinde durur. Rozet yalnız anormal ve eyleme çağıran ' +
                'durum içindir.',
        ).toEqual([]);
    });

    it('hiçbir rozet çevrilmemiş ham alan değeri göstermez', () => {
        const offenders: string[] = [];

        for (const file of PAGE_FILES) {
            for (const line of readFileSync(file, 'utf8').split('\n')) {
                if (RAW_FIELD_LABEL.test(line) && !line.includes('t(')) {
                    offenders.push(`${path.basename(file)}: ${line.trim().slice(0, 60)}`);
                }
            }
        }

        expect(
            offenders,
            'ROZET KURALI: rozet etiketi çevrilmemiş bir ham alan değeri ' +
                '(örneğin `draft`). Kullanıcının dili ne olursa olsun İngilizce bir ' +
                'enum görür.',
        ).toEqual([]);
    });
});
