import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * ÇEVİRİ, YER TUTUCUYU DÜŞÜREMEZ — `DS-I18N-PLACEHOLDER-PARITY-14`.
 *
 * Bu kapı bir çeviri turundan ÖNCE kondu, sonra değil. Sebebi ölçülebilir:
 * Türkçe katalog altı yüzden fazla boş satır taşıyordu ve o satırlar
 * doldurulurken en sessiz kusur, cümlenin doğru ama yer tutucusunun eksik
 * olmasıdır.
 *
 * "Merhaba {name}" karşılığına "Merhaba" yazmak bir çeviri hatası gibi
 * görünmez — cümle akıcıdır, dilbilgisi doğrudur, kod çalışır. Yalnız
 * ekranda kimsenin adı yazmaz. Aynı kusurun daha kötü hâli sayıdır:
 * "{count} ürün silinecek" yerine "Ürünler silinecek" yazan bir onay
 * kutusu, sahibe KAÇ ürün sildiğini söylemez ve geri dönüşü yoktur.
 *
 * Ters yönü de yasak: msgstr'de olup msgid'de olmayan bir yer tutucu, hiç
 * doldurulmayacağı için ekrana ham `{gun}` diye çıkar.
 *
 * TEKRAR SAYISI DEĞİL, KÜME karşılaştırılır. Türkçe'nin sözdizimi aynı
 * değişkeni iki kez istemeyebilir ya da iki kez isteyebilir ("{name}'in
 * {name} adlı menüsü" gibi bir cümle kurmak gerekmez); dilin kendi
 * kurallarına karışmadan, ANLAMIN taşıdığı değişkenlerin hepsinin
 * bulunduğunu sınamak yeterlidir.
 */
const PO_DIR = path.join(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
    '..',
    'lang',
    'po',
);

/** `{name}`, `{count}`, `{clock}` — boru hattının tek yer tutucu biçimi. */
const PLACEHOLDER = /\{[a-zA-Z][a-zA-Z0-9_]*\}/g;

type Entry = { msgid: string; msgstr: string };

/**
 * PO okuyucu — yalnız bu kapının ihtiyacı kadarı.
 *
 * Tam bir PO ayrıştırıcısı getirmek, tek bir düzenli ifadeyle cevaplanan
 * bir soru için üçüncü taraf bir bağımlılık eklemek olurdu. Çok satırlı
 * `msgstr ""` + devam dizeleri destekleniyor, çünkü uzun cümleler boru
 * hattından o biçimde çıkıyor.
 */
function parse(source: string): Entry[] {
    const entries: Entry[] = [];
    let current: { key: 'msgid' | 'msgstr' | null; msgid: string; msgstr: string } = {
        key: null,
        msgid: '',
        msgstr: '',
    };

    const flush = () => {
        if (current.msgid !== '') {
            entries.push({ msgid: current.msgid, msgstr: current.msgstr });
        }

        current = { key: null, msgid: '', msgstr: '' };
    };

    for (const line of source.split('\n')) {
        const trimmed = line.trim();

        if (trimmed === '' || trimmed.startsWith('#')) {
            if (trimmed === '') flush();

            continue;
        }

        const msgid = trimmed.match(/^msgid\s+"(.*)"$/);
        if (msgid) {
            flush();
            current.key = 'msgid';
            current.msgid = msgid[1];

            continue;
        }

        const msgstr = trimmed.match(/^msgstr\s+"(.*)"$/);
        if (msgstr) {
            current.key = 'msgstr';
            current.msgstr = msgstr[1];

            continue;
        }

        const continuation = trimmed.match(/^"(.*)"$/);
        if (continuation && current.key !== null) {
            current[current.key] += continuation[1];
        }
    }

    flush();

    return entries;
}

function placeholders(text: string): Set<string> {
    return new Set(text.match(PLACEHOLDER) ?? []);
}

describe('DS-I18N-PLACEHOLDER-PARITY-14 — çeviri yer tutucuyu düşürmez', () => {
    const files = readdirSync(PO_DIR).filter(
        // `.pot` şablonu ve `en` kaynağı hariç: ikisinde msgstr zaten
        // koddaki metnin kendisidir, karşılaştıracak bir çeviri yoktur.
        (name) => name.endsWith('.po') && !name.includes('.en.'),
    );

    it('en az bir çeviri kataloğu bulur', () => {
        // Kapının kendisi sessizce boşa düşmesin: dizin adı değişirse ya da
        // katalog düzeni taşınırsa, sıfır dosya üzerinde "her şey geçti"
        // demek en tehlikeli yeşildir.
        expect(files.length).toBeGreaterThan(0);
    });

    for (const file of files) {
        it(`${file} — her çeviri kaynağın yer tutucularını taşır`, () => {
            /*
                KARŞILAŞTIRMA KAYNAĞI İNGİLİZCE KATALOGDUR, msgid DEĞİL.

                Bu boru hattında `msgid` cümlenin kendisi değil ANAHTARIDIR
                (`menu.item.price.edit.button`). Yer tutucular yalnız metnin
                içinde yaşar, yani kaynak dilin msgstr'inde. Kapı ilk hâlinde
                anahtarı metin sanıp her çeviriyi "fazla yer tutucu" diye
                suçladı — ölçtüğü şey yanlıştı.
            */
            const domain = file.replace(/\.[a-z-]+\.po$/, '');
            const source = new Map(
                parse(readFileSync(path.join(PO_DIR, `${domain}.en.po`), 'utf8')).map((entry) => [
                    entry.msgid,
                    entry.msgstr,
                ]),
            );

            const entries = parse(readFileSync(path.join(PO_DIR, file), 'utf8'));
            const broken: string[] = [];

            for (const entry of entries) {
                // Boş msgstr bir çeviri DEĞİL, çevrilmemiş bir satırdır;
                // boru hattı onu kaynağa düşürür. Bu kapı çeviri eksikliğini
                // değil, YANLIŞ çeviriyi kovalar.
                if (entry.msgstr === '') continue;

                const english = source.get(entry.msgid);

                // Kaynakta karşılığı olmayan anahtarı bu kapı sahiplenmez;
                // onu `pipeline.guard` zaten sayıyor.
                if (english === undefined) continue;

                const expected = placeholders(english);
                const actual = placeholders(entry.msgstr);

                const missing = [...expected].filter((token) => !actual.has(token));
                const extra = [...actual].filter((token) => !expected.has(token));

                if (missing.length > 0 || extra.length > 0) {
                    broken.push(
                        `${entry.msgid}: eksik ${missing.join(', ') || '—'} · fazla ${
                            extra.join(', ') || '—'
                        }`,
                    );
                }
            }

            expect(
                broken,
                `DS-I18N-PLACEHOLDER-PARITY-14: ${file} içinde yer tutucusu sapmış çeviri var.\n${broken.join('\n')}`,
            ).toEqual([]);
        });
    }
});
