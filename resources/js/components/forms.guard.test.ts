import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { globSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * FORM-ONE-OUTCOME — `docs/47` form standardının zorlayıcı kapısı.
 *
 * Bu kapı olmadan standart bir belgede yaşar ve bir sonraki form onu
 * bilmeden yazılır. Aşağıdaki iki kural, gözle bakarak fark edilemeyen ve
 * bu depoda GERÇEKTEN yaşanmış iki kusuru yakalar:
 *
 *   1. Gönderim işleyicisinde koşulsuz `return;` — düğmeye basılır, hiçbir
 *      şey olmaz. `MediaUploadRegion` ve `ManualPaymentForm` tam olarak
 *      böyleydi; para hareketi kaydeden bir formda kullanıcı kaydın
 *      gittiğini sanabilirdi.
 *   2. Kalıcı `disabled` alan — yalnız ileride yapılacak diye devre dışı
 *      bırakılmış kontrol. Kullanıcı onu nasıl etkinleştireceğini bilemez,
 *      çünkü etkinleştirmenin bir yolu yoktur.
 *
 * Kapı kaynak metni okur, çalıştırmaz: amaç bir davranışı değil, bir
 * YAZIM BİÇİMİNİ yasaklamaktır.
 */

const THIS_DIR = path.dirname(fileURLToPath(import.meta.url));

const FORM_FILES = globSync('**/*.tsx', { cwd: THIS_DIR })
    .filter((file) => !file.includes('.test.'))
    .filter((file) => !file.includes('.stories.'))
    .filter((file) => !file.includes('storybook-demo'))
    .map((file) => path.join(THIS_DIR, file))
    .filter((file) => readFileSync(file, 'utf8').includes('<form'));

describe('form standardı (docs/47)', () => {
    it('bir form dosyası bile bulunmadan geçmez', () => {
        expect(FORM_FILES.length).toBeGreaterThan(5);
    });

    // --- Kural 5: sessiz reddetme yok ---------------------------------
    //
    // Yasaklanan ŞEKİL dar ve kasıtlı: bir metin alanının BOŞLUĞUNU sınayıp
    // hiçbir şey söylemeden geri dönmek.
    //
    // Daha geniş bir kural denendi ve işe yaramadı: "gövdesinde sessiz bir
    // erken dönüş olan işleyici" araması, aynı işleyicinin BAŞKA bir dalda
    // hata yazması yüzünden kusuru kaçırıyordu. Kapı, kusuru geri koyarak
    // sınandı ve yakalamadı — yakaladığını gösteremeyen bir kapı, kapı
    // değildir.
    //
    // `if (!tree) return;` gibi ÖN KOŞUL dalları bilerek kapsam dışıdır:
    // orada form zaten ekranda değildir, dolayısıyla söylenecek bir şey de
    // yoktur.
    it('boş bir alanı sınayıp sessizce vazgeçen hiçbir dal yok', () => {
        const offenders: string[] = [];

        // Önce sessiz dal bulunur, SONRA koşulu ayrıca sınanır.
        //
        // Tek bir regex denendi ve kaçırdı: koşulun içindeki `trim()`
        // parantezi `[^)]*` sınıfını kesiyordu, dolayısıyla
        // `endsAt.trim().length === 0` eşleşmiyordu. Kusur geri konarak
        // sınandığında ortaya çıktı.
        // Koşul içinde satır sonu ya da süslü parantez OLAMAZ.
        //
        // Serbest bırakıldığında motor koşulu satırlarca uzatıp uzaktaki bir
        // `return;`e bağlıyordu: `if (!response.ok) { … setError(…); return;`
        // sessiz sanılıyordu, oysa hemen üstünde kullanıcıya konuşuyordu.
        const SILENT_RETURN = /if\s*\(([^\n{}]*?)\)\s*\{?\s*\n?\s*return;/g;

        // Bir METİN ALANININ boşluğunu sınayan koşul biçimleri.
        const EMPTY_FIELD_CHECK = /===\s*''|\.length\s*===\s*0|!\s*[a-z][A-Za-z]*\.trim\(\)/;

        for (const file of FORM_FILES) {
            const source = readFileSync(file, 'utf8');

            for (const match of source.matchAll(SILENT_RETURN)) {
                const condition = match[1].trim();

                if (EMPTY_FIELD_CHECK.test(condition)) {
                    offenders.push(`${path.relative(THIS_DIR, file)}: if (${condition})`);
                }
            }
        }

        expect(
            offenders,
            'FORM-ONE-OUTCOME: bu dallar boş bir alan bulunca kullanıcıya hiçbir şey ' +
                'söylemeden vazgeçiyor. Düğmeye basılır, hiçbir şey olmaz — ve kullanıcı ' +
                'kaydın gittiğini sanabilir (docs/47 Kural 5).',
        ).toEqual([]);
    });

    // --- Kural 4: planlandığı için devre dışı bırakılmış kontrol yok ---
    it('hiçbir kontrol yalnız ileride yapılacağı için devre dışı gösterilmez', () => {
        const offenders: string[] = [];

        for (const file of FORM_FILES) {
            const source = readFileSync(file, 'utf8');

            // Koşulsuz `disabled` — bir duruma değil, hiçbir şeye bağlı.
            // `disabled={submitting}` gibi gerçek bir sebebi olanlar geçer.
            if (/<(TextInput|Textarea|Select)\b[^>]*\sdisabled\s*(\/>|>|\s[a-z-]+=)/.test(source)) {
                offenders.push(path.relative(THIS_DIR, file));
            }
        }

        expect(
            offenders,
            'FORM-ONE-OUTCOME: kalıcı devre dışı kontrol bulundu. Bir alan yalnız ' +
                'ileride yapılacak diye devre dışı gösterilmez; geldiğinde çalışır ' +
                'hâlde gelir (docs/44 devre dışı standardı, docs/47 Kural 4).',
        ).toEqual([]);
    });
});
