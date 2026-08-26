/**
 * PO okuma/yazma ve MO derleme — CORE-08.
 *
 * Neden PO? Çeviri, kaynak koddan ayrı bir üründür: çevirmen kod deposunu
 * açmadan çalışabilmeli, eksik ve şüpheli (fuzzy) satırlar sayılabilmeli,
 * ve aynı dize iki yerde farklı çevrilmemeli. PO bunun otuz yıllık, araç
 * desteği olan standardıdır — kendi JSON formatımızı icat etmek, o araç
 * ekosistemini kaybetmek demektir.
 *
 * MO ise PO'nun çalışma zamanı projeksiyonudur: PHP tarafı metni ayrıştırmaz,
 * derlenmiş ikili tabloyu okur.
 */

/** PO kaçışlarını çözer: "\n", "\t", "\"" ve "\\". */
function unescapePo(value) {
    return value.replace(/\\(n|t|r|"|\\)/g, (_, char) => {
        switch (char) {
            case 'n':
                return '\n';
            case 't':
                return '\t';
            case 'r':
                return '\r';
            default:
                return char;
        }
    });
}

function escapePo(value) {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\t/g, '\\t');
}

/**
 * Bir PO metnini { msgid: { msgstr, fuzzy, references } } tablosuna çevirir.
 * Başlık girdisi (boş msgid) ayrı döner; çeviri sayımına karışmaz.
 */
export function parsePo(source) {
    const entries = new Map();
    let header = '';

    let current = null;
    let lastField = null;

    const flush = () => {
        if (current === null) {
            return;
        }

        if (current.msgid === '') {
            header = current.msgstr;
        } else {
            entries.set(current.msgid, {
                msgstr: current.msgstr,
                fuzzy: current.fuzzy,
                references: current.references,
            });
        }

        current = null;
        lastField = null;
    };

    for (const rawLine of source.split('\n')) {
        const line = rawLine.trim();

        if (line === '') {
            flush();
            continue;
        }

        if (line.startsWith('#')) {
            current ??= { msgid: null, msgstr: '', fuzzy: false, references: [] };

            if (line.startsWith('#,') && line.includes('fuzzy')) {
                current.fuzzy = true;
            } else if (line.startsWith('#:')) {
                current.references.push(line.slice(2).trim());
            }

            continue;
        }

        if (line.startsWith('msgid ')) {
            if (current !== null && current.msgid !== null) {
                flush();
            }

            current ??= { msgid: null, msgstr: '', fuzzy: false, references: [] };
            current.msgid = unescapePo(line.slice(7, -1));
            lastField = 'msgid';
            continue;
        }

        if (line.startsWith('msgstr ')) {
            if (current === null) {
                continue;
            }

            current.msgstr = unescapePo(line.slice(8, -1));
            lastField = 'msgstr';
            continue;
        }

        if (line.startsWith('"') && current !== null && lastField !== null) {
            const chunk = unescapePo(line.slice(1, -1));
            current[lastField] += chunk;
        }
    }

    flush();

    return { header, entries };
}

/**
 * Tabloyu PO metnine çevirir. Anahtarlar sıralanır: üretilen dosya
 * deterministik olmalı, aksi hâlde her derleme sahte bir diff üretir.
 */
export function formatPo({ headerFields, entries }) {
    const lines = ['msgid ""', 'msgstr ""'];

    for (const [name, value] of Object.entries(headerFields)) {
        lines.push(`"${name}: ${value}\\n"`);
    }

    lines.push('');

    for (const msgid of [...entries.keys()].sort()) {
        const entry = entries.get(msgid);

        for (const reference of entry.references ?? []) {
            lines.push(`#: ${reference}`);
        }

        if (entry.fuzzy) {
            lines.push('#, fuzzy');
        }

        lines.push(`msgid "${escapePo(msgid)}"`);
        lines.push(`msgstr "${escapePo(entry.msgstr ?? '')}"`);
        lines.push('');
    }

    return lines.join('\n');
}

/**
 * Çevrilmiş girdilerden GNU MO ikili dosyası üretir.
 *
 * Format (little-endian): sihirli sayı, revizyon, girdi sayısı, orijinal ve
 * çeviri tablolarının konumları, ardından her dizenin uzunluk+ofset çifti ve
 * NUL ile sonlandırılmış gövdeler. Boş çeviriler dışarıda bırakılır —
 * çevrilmemiş bir dizeyi MO'ya yazmak, onu "çevrilmiş" göstermek olurdu.
 */
export function compileMo(translations) {
    const pairs = Object.keys(translations)
        .filter((key) => translations[key] !== '')
        .sort()
        .map((key) => [Buffer.from(key, 'utf8'), Buffer.from(translations[key], 'utf8')]);

    const count = pairs.length;
    const headerSize = 28;
    const tableSize = count * 8;
    let offset = headerSize + tableSize * 2;

    const originalTable = Buffer.alloc(tableSize);
    const translationTable = Buffer.alloc(tableSize);
    const body = [];

    pairs.forEach(([original], index) => {
        originalTable.writeUInt32LE(original.length, index * 8);
        originalTable.writeUInt32LE(offset, index * 8 + 4);
        body.push(original, Buffer.from([0]));
        offset += original.length + 1;
    });

    pairs.forEach(([, translation], index) => {
        translationTable.writeUInt32LE(translation.length, index * 8);
        translationTable.writeUInt32LE(offset, index * 8 + 4);
        body.push(translation, Buffer.from([0]));
        offset += translation.length + 1;
    });

    const header = Buffer.alloc(headerSize);
    header.writeUInt32LE(0x950412de, 0); // sihirli sayı
    header.writeUInt32LE(0, 4); // revizyon
    header.writeUInt32LE(count, 8);
    header.writeUInt32LE(headerSize, 12); // orijinal tablo konumu
    header.writeUInt32LE(headerSize + tableSize, 16); // çeviri tablosu konumu
    header.writeUInt32LE(0, 20); // hash tablosu boyutu (kullanılmıyor)
    header.writeUInt32LE(headerSize + tableSize * 2, 24);

    return Buffer.concat([header, originalTable, translationTable, ...body]);
}
