#!/usr/bin/env node
/**
 * CORE-08 çeviri boru hattı: kaynak → PO → MO + JSON.
 *
 *   node scripts/i18n extract   İngilizce kaynaktan POT ve altı locale PO'su
 *                               üretir/günceller (var olan çeviriyi korur).
 *   node scripts/i18n build     PO'lardan MO (PHP) ve JSON (React) üretir.
 *   node scripts/i18n check     Üretilmiş dosyalar PO ile aynı mı — CI kapısı.
 *
 * Tasarım kararı: kaynak dizeler **kodda** yaşar (TS katalogları), çeviriler
 * **PO'da** yaşar, çalışma zamanı ise yalnız projeksiyonları okur. Bu ayrım
 * olmasaydı bir çevirmenin tek kelime düzeltmesi kod değişikliği olurdu.
 */
import { build as viteBuild } from 'vite';
import { mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import { compileMo, formatPo, parsePo } from './po.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const PO_DIR = path.join(ROOT, 'lang', 'po');
const MO_DIR = path.join(ROOT, 'lang', 'mo');
const JSON_DIR = path.join(ROOT, 'resources', 'js', 'i18n', 'generated');
const SOURCE_ENTRY = path.join(ROOT, 'resources', 'js', 'i18n', 'domains.ts');
const LOCALES_ENTRY = path.join(ROOT, 'resources', 'js', 'i18n', 'locales.ts');

/**
 * Kaynak katalogları TS'ten okur: liste tahmin edilmez, koddan gelir.
 *
 * Derleme Vite ile yapılır çünkü `workspace.ts` katalogları `import.meta.glob`
 * ile keşfeder — bu Vite'a özgü bir yetenektir ve düz bir bundler onu
 * çözemez. Aynı keşif mekanizmasını burada da kullanmak, "script'in gördüğü
 * katalog listesi" ile "uygulamanın gördüğü katalog listesi" nin ayrışmasını
 * imkânsız kılar.
 */
async function loadSources() {
    const outDir = path.join(ROOT, 'storage', 'framework', 'i18n-sources');
    rmSync(outDir, { recursive: true, force: true });
    mkdirSync(outDir, { recursive: true });

    await viteBuild({
        configFile: false,
        logLevel: 'silent',
        build: {
            outDir,
            emptyOutDir: true,
            ssr: true,
            minify: false,
            rollupOptions: {
                input: { sources: SOURCE_ENTRY, locales: LOCALES_ENTRY },
                output: { format: 'es', entryFileNames: '[name].mjs' },
            },
        },
    });

    const stamp = Date.now();
    const { DOMAIN_CATALOGS } = await import(
        `${pathToFileURL(path.join(outDir, 'sources.mjs')).href}?t=${stamp}`
    );
    const { LOCALES, FALLBACK_LOCALE } = await import(
        `${pathToFileURL(path.join(outDir, 'locales.mjs')).href}?t=${stamp}`
    );

    rmSync(outDir, { recursive: true, force: true });

    return { catalogs: DOMAIN_CATALOGS, locales: LOCALES, fallback: FALLBACK_LOCALE };
}

function poPath(domain, locale) {
    return path.join(PO_DIR, `${domain}.${locale}.po`);
}

function potPath(domain) {
    return path.join(PO_DIR, `${domain}.pot`);
}

function headerFields(locale, fallback) {
    return {
        'Project-Id-Version': 'zabuno',
        'MIME-Version': '1.0',
        'Content-Type': 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding': '8bit',
        Language: locale ?? '',
        'X-Source-Locale': fallback,
        'X-Generator': 'scripts/i18n',
    };
}

async function extract() {
    const { catalogs, locales, fallback } = await loadSources();
    mkdirSync(PO_DIR, { recursive: true });

    let written = 0;

    for (const domain of Object.keys(catalogs).sort()) {
        const source = catalogs[domain];

        // Kaynağı boş olan anahtar kataloğa girmez: çevrilecek bir metin
        // yoktur. Bu bir eksiklik değil, bir arayüz durumudur (örneğin
        // "henüz bir şey olmadı" satırı). PO'ya boş bir msgstr olarak
        // yazılsaydı, sonsuza dek "çevrilmemiş" görünür ve eksik sayımını
        // kalıcı olarak yanıltırdı.
        const keys = Object.keys(source)
            .filter((key) => source[key] !== '')
            .sort();

        // POT: çevrilecek dizelerin şablonu. msgstr her zaman boştur.
        const template = new Map(
            keys.map((key) => [
                key,
                { msgstr: '', fuzzy: false, references: [`${domain}:${key}`] },
            ]),
        );
        writeFileSync(
            potPath(domain),
            formatPo({ headerFields: headerFields(null, fallback), entries: template }),
            'utf8',
        );
        written++;

        for (const locale of Object.keys(locales)) {
            const target = poPath(domain, locale);
            const existing = existsSync(target)
                ? parsePo(readFileSync(target, 'utf8')).entries
                : new Map();

            const entries = new Map();

            for (const key of keys) {
                // Kaynak locale kendi kendisinin çevirisidir; elle
                // doldurulmaz, kaynaktan gelir.
                const msgstr =
                    locale === fallback ? source[key] : (existing.get(key)?.msgstr ?? '');

                entries.set(key, {
                    msgstr,
                    fuzzy: existing.get(key)?.fuzzy ?? false,
                    references: [`${domain}:${key}`],
                });
            }

            // Kaynakta artık olmayan anahtarlar düşer: ölü çeviri, eksik
            // çeviriden daha tehlikelidir çünkü sayımı yanıltır.
            writeFileSync(
                target,
                formatPo({ headerFields: headerFields(locale, fallback), entries }),
                'utf8',
            );
            written++;
        }
    }

    return { written };
}

/** PO'lardan MO ve JSON projeksiyonlarını üretir; içerikleri döner. */
async function project() {
    const { catalogs, locales, fallback } = await loadSources();
    const artifacts = new Map();

    for (const domain of Object.keys(catalogs).sort()) {
        for (const locale of Object.keys(locales)) {
            const target = poPath(domain, locale);

            if (!existsSync(target)) {
                throw new Error(
                    `Missing PO file for ${domain}/${locale}. Run "node scripts/i18n extract" first.`,
                );
            }

            const { entries } = parsePo(readFileSync(target, 'utf8'));
            const translations = {};

            for (const [msgid, entry] of entries) {
                // Şüpheli (fuzzy) satır projeksiyona girmez: onaylanmamış bir
                // çeviriyi kullanıcıya göstermek, İngilizce göstermekten kötüdür.
                if (entry.msgstr !== '' && !entry.fuzzy) {
                    translations[msgid] = entry.msgstr;
                }
            }

            artifacts.set(path.join(MO_DIR, locale, `${domain}.mo`), compileMo(translations));

            // Kaynak locale JSON'a yazılmaz: React tabanı zaten koddadır,
            // aynı metni ikinci kez göndermek boşuna yük olurdu.
            if (locale !== fallback) {
                artifacts.set(
                    path.join(JSON_DIR, `${domain}.${locale}.json`),
                    Buffer.from(`${JSON.stringify(translations, null, 4)}\n`, 'utf8'),
                );
            }
        }
    }

    return artifacts;
}

async function buildProjections() {
    const artifacts = await project();

    for (const dir of [MO_DIR, JSON_DIR]) {
        rmSync(dir, { recursive: true, force: true });
    }

    for (const [target, content] of artifacts) {
        mkdirSync(path.dirname(target), { recursive: true });
        writeFileSync(target, content);
    }

    return { written: artifacts.size };
}

async function check() {
    const artifacts = await project();
    const problems = [];

    for (const [target, expected] of artifacts) {
        if (!existsSync(target)) {
            problems.push(`missing projection: ${path.relative(ROOT, target)}`);
            continue;
        }

        if (!readFileSync(target).equals(expected)) {
            problems.push(`stale projection: ${path.relative(ROOT, target)}`);
        }
    }

    const known = new Set([...artifacts.keys()]);

    for (const dir of [MO_DIR, JSON_DIR]) {
        if (!existsSync(dir)) {
            continue;
        }

        const walk = (current) => {
            for (const entry of readdirSync(current, { withFileTypes: true })) {
                const full = path.join(current, entry.name);

                if (entry.isDirectory()) {
                    walk(full);
                } else if (!known.has(full)) {
                    problems.push(`orphan projection: ${path.relative(ROOT, full)}`);
                }
            }
        };

        walk(dir);
    }

    return problems;
}

const command = process.argv[2] ?? 'check';

try {
    if (command === 'extract') {
        const { written } = await extract();
        console.log(`i18n extract: ${written} PO/POT files written.`);
    } else if (command === 'build') {
        const { written } = await buildProjections();
        console.log(`i18n build: ${written} projections written.`);
    } else if (command === 'check') {
        const problems = await check();

        if (problems.length > 0) {
            console.error('i18n check FAILED:');
            for (const problem of problems) {
                console.error(`  - ${problem}`);
            }
            console.error('Run "node scripts/i18n build" and commit the result.');
            process.exit(1);
        }

        console.log('i18n check: projections match the PO catalogs.');
    } else {
        console.error(`Unknown command "${command}". Use extract, build or check.`);
        process.exit(2);
    }
} catch (error) {
    console.error(`i18n ${command} failed: ${error.message}`);
    process.exit(1);
}
