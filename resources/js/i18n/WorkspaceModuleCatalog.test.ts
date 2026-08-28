import { describe, it, expect } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { t } from './workspace';

/**
 * RED - S1 workspace i18n modular catalog contract.
 *
 * resources/js/i18n/workspace.ts (legacy flat literal catalog) is being
 * replaced by an aggregator of the same path that automatically discovers
 * module catalogs under resources/js/i18n/workspace/ via import.meta.glob,
 * merges them, and preserves all 413 workspace.* key/value pairs (media.ts
 * intentionally replaces one legacy status key with eight status-specific
 * keys) and t() runtime/type behavior exactly.
 */

const WORKSPACE_DIR = path.dirname(fileURLToPath(import.meta.url));
const CATALOG_DIR = path.join(WORKSPACE_DIR, 'workspace');
const AGGREGATOR_FILE = path.join(WORKSPACE_DIR, 'workspace.ts');

const FROZEN_MODULE_FILENAMES = [
    'analytics.ts',
    'billing.ts',
    'brand-locations.ts',
    'dashboard.ts',
    'media.ts',
    'menu.ts',
    'publication.ts',
    'shell.ts',
    'team.ts',
];

const FROZEN_LEGACY_KEY_COUNT = 488;

// Frozen from the brand form rewrite: sha256 of sorted "key=value" lines
// joined by "\n" over all 451 entries. The brand onboarding form used to ask
// for four free-text values — name, timezone, currency and locale — which
// meant asking a restaurant owner to type `Europe/Istanbul`, `TRY` and
// `tr_TR`. Those are column values, not anyone's language; the owner typed
// "istantul" and the journey ended there.
//
// The form now asks what the user knows (name, country), derives what can be
// derived (time zone, currency) and defers what can be deferred (menu content
// language). That needed twelve keys: an intro, help text for every field,
// the market picker, the regional section, a busy label, and three specific
// failure messages in place of one generic retry. Entry count grew from 439
// to 451. Additive only: no existing key or value changed.
//
// 2026-08-27, form standardı (`docs/47`) — 451 → 458:
//   - Konum formu tek bir "şunlar zorunludur" cümlesi yerine ALAN BAŞINA
//     hata veriyor (+4) ve isteğe bağlı alanları etiketinde söylüyor (+1).
//   - Medya formu iki kalıcı devre dışı alanı KALDIRDI (−2: haklar/lisans,
//     son kullanma) — bir kontrol yalnız ileride yapılacak diye devre dışı
//     gösterilmez.
//   - Medya formu üç eksik-alan mesajı ve bir alternatif-metin ipucu
//     kazandı (+4).
// Ayrıca iki DEĞER değişti: "Asset slot" ve onun yer tutucusu kullanıcı
// diline çekildi ("Where will this image be used?"). İç kavram kullanıcıya
// taşınmaz.
//
// 2026-08-27, yüzey ayrımı — 458 → 432:
//   - "Launch readiness" ekranı restoran panelinden GELİŞTİRİCİ paneline
//     taşındı (25 anahtar) ve gezinti etiketi düştü (1). Commit hash'i,
//     test süresi, tenant izolasyonu ve yedek tatbikatı restoran sahibinin
//     işi değildir (UX raporu §4.3, §9.10).
//   Anahtarlar silinmedi; `platform.releaseReadiness.*` adıyla platform
//   kataloğunda yaşıyorlar.
//
// 2026-08-27, plan kısıtı — 432 → 435:
//   Analytics ekranı 402'yi "hata" sanıyordu ve işe yaramayacak bir Retry
//   düğmesi gösteriyordu. Plan cevabı ayrı bir durum oldu: rozet, açıklama
//   ve çıkış yolu (+3).
//
// 2026-08-27, boş AI kartı — 435 → 426:
//   Altı sayfada duran "No real AI is connected yet" kartı kaldırıldı; onun
//   dokuz anahtarı da ölü kaldığı için silindi. Ölü anahtar, çeviri
//   dosyalarının en sessiz çürüme biçimidir.
//
// 2026-08-27, medya yükleme ekranı — 426 → 436:
//   Sürükle-bırak alanı kendi metnini taşır (tarayıcı ham `<input
//   type=file>`i işletim sisteminin dilinde çiziyordu), önizleme ölçü
//   gösterir, ve slot GEREKSİNİMLERİ yüklemeden önce görünür.
//
// EKRAN ŞEMA DEĞİLDİR (docs/53). Panelde bulunan hâl: sekiz sayfanın beşinde
// durum rozeti ya markanın VERİTABANI BİRİNCİL ANAHTARINI (`#3`) ya da `#` ile
// kimlik kılığına sokulmuş bir SAYACI gösteriyordu — Lokasyonlar'da rozet `#1`
// diyor, hemen altında zaten "1 locations" yazıyordu. Metinlerde ise sistemin
// doğru çalıştığını kanıtlamak için yazılmış cümleler duruyordu: iç izin
// anahtarı adı (`menu.publish permission`), iç yol haritası aşaması ("Stage
// 1"), uygulama detayı ("immutable snapshot"), ve faturalandırmada üç kez "no
// billing API has been queried".
//
// Bunlar yanlış değildi — yanlış YERDEYDİ: geliştiricinin "bu gerçekten bağlı"
// kanıtı, restoran sahibinin ekranında kalıcı hâle gelmişti. Değerler kullanıcı
// dilinde yeniden yazıldı ve bir anahtar eklendi (`status.published`), çünkü
// yayının veritabanı kimliği yerine gösterilecek gerçek bir durum gerekiyordu.
// Ayrıca `status.draft`: yayın durumu ham sunucu değeri olarak basılıyordu
// ("published"), kullanıcının dili ne olursa olsun. Alan ATILMADI — "menüm
// yayında mı" sorusunun cevabı — çevrildi.
//
// Son olarak medya kütüphanesi: kullanıcının yüklediği fotoğraf, kendi yazdığı
// alt metinle DEĞİL varlığın veritabanı kimliğiyle (`#7`) listeleniyordu ve her
// satırdaki silme düğmesinin adı aynıydı ("Delete") — ekran okuyucu kullanan
// biri, geri alınamaz bir eylemde hangi görseli sildiğini ayırt edemiyordu.
// İki anahtar eklendi: adsız görsel için dürüst bir yedek ve satıra özgü silme
// adı. 436 → 440.
//
// KABUK (FF-01b): kenar çubuğu dokuz maddelik tek ve ADSIZ bir yığındı; üç
// grup başlığı eklendi ("Your restaurant" / "Your menu" / "Your business") ve
// dokuz eşit seçenek bir SIRAYA dönüştü. Hesap işleri kenar çubuğunun dibinden
// kimlik alanındaki hesap menüsüne taşındı; menünün erişilebilir adı için bir
// anahtar daha. 440 → 444.
//
// BİLGİ MİMARİSİ (FF-01c, docs/50 §5): dokuz düz madde `Operations /
// Management / Settings` olarak yeniden düzenlendi. Bölüm adları yolculuğa
// göre değişti (Dashboard→Home, Menu→Menus, Analytics→Insights), QR kodları
// yayın sayfasının içinden çıkarılıp kendi hedefi oldu, Brand ve Billing
// Settings sekmelerine taşındı ve yayınlama menünün yanına geçti. Günlük
// operasyon olmayan işlerin ana menüde kalıcı yer işgal etmesi, her gün
// gidilen hedeflerin arasına gürültü koymaktı. 444 → 457.
//
// FORM STANDARDI (FF-04a): marka düzenleme formu dil, saat dilimi ve para
// birimini SERBEST METİN olarak soruyordu — kullanıcıdan `Europe/Istanbul`,
// `TRY` ve `tr` yazmasını bekliyordu. Bunlar kullanıcı dili değil geliştirici
// kodudur; sunucu haklı olarak reddediyor, kullanıcı ne yazacağını hiçbir
// yerden öğrenemiyordu. Alanlar seçeneğe çevrildi, her birine ne işe yaradığını
// söyleyen yardımcı metin eklendi, "Locale" → "Menu language" ve "Slug" →
// "Menu web address" oldu, ve arıza sınıfları (yetki/çakışma/ağ/sunucu) ayrı
// mesajlar aldı. 457 → 468.
//
// SAYFA DURUMLARI (FF-02c): hata ekranları ÇIKIŞ YOLU olmadan sunuluyordu —
// menü yüklenemediğinde kullanıcı "yüklenemedi" görüyor ve yapabileceği
// hiçbir şey bulunmuyordu. Boş durum artık dört soruyu birden cevaplıyor (ne
// yok, neden yok, anlamı ne, şimdi ne yapabilir) ve hataya yeniden deneme
// eklendi. 468 → 471.
//
// BAĞLAM PANELİ (FF-03a): sağ panel planlarda defalarca geçiyordu (docs/50
// §3.4, §4, §13, §21, §25) ama hiçbir ekranda yoktu — yuvası açılmış, hiç
// doldurulmamıştı. Menü editörü artık gerçek bağlam gösteriyor: yayın durumu
// ve sürüm, lokasyon, kategori ve ürün sayısı. Şablondaki tema, desteklenen
// diller ve yayın zamanlaması EKLENMEDİ: ürün onları henüz tutmuyor ve
// olmayan bir ayarı göstermek olmayan bir yetenek vaat etmek olurdu.
// 471 → 477.
//
// PANEL ÜÇ EKRANDA (FF-03c): marka ve şube formlarına da bağlam paneli
// geldi. Marka paneli markanın KAPSAMINI gösterir (kaç şubede görünüyor,
// hangi şehirlerde); şube paneli o şubenin markasını, şehrini ve — yalnız
// yüklü menü ağacı GERÇEKTEN o şubeye aitse — menü özetini gösterir.
// 477 → 488.
const FROZEN_LEGACY_NORMALIZED_SHA256 =
    '848b807c993d52526df4ba54604790baa200fc8e63fb16de33a16d99be705388';

function normalizedHash(entries: Record<string, string>): string {
    const sortedKeys = Object.keys(entries).sort();
    const normalized = sortedKeys.map((key) => `${key}=${entries[key]}`).join('\n');
    return createHash('sha256').update(normalized, 'utf8').digest('hex');
}

describe('workspace i18n modular catalog contract', () => {
    it('discovers exactly the nine frozen module catalog filenames under resources/js/i18n/workspace/', () => {
        const actualFilenames = readdirSync(CATALOG_DIR)
            .filter((name) => name.endsWith('.ts'))
            .sort();

        expect(actualFilenames).toEqual(FROZEN_MODULE_FILENAMES);
    });

    it('aggregates via automatic eager glob discovery with no static per-module import list and no inline translation literals', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/import\.meta\.glob/);

        for (const filename of FROZEN_MODULE_FILENAMES) {
            const moduleName = filename.replace(/\.ts$/, '');
            const staticImportPattern = new RegExp(
                `from\\s+["'\`]\\./workspace/${moduleName}["'\`]`,
            );
            expect(aggregatorSource).not.toMatch(staticImportPattern);
        }

        expect(aggregatorSource).not.toMatch(/['"]workspace\.[a-zA-Z]+['"]\s*:/);
    });

    it('rejects a duplicate key across catalogs with a deterministic error naming the key and source module', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/throw/);
        expect(aggregatorSource).toMatch(/duplicate/i);
    });

    it('composes exactly 413 workspace translation entries matching the frozen normalized legacy SHA-256', async () => {
        const workspaceModule: typeof import('./workspace') = await import('./workspace');
        const composed = workspaceModule.workspaceTranslations as Record<string, string>;

        expect(composed).toBeTruthy();
        expect(Object.keys(composed)).toHaveLength(FROZEN_LEGACY_KEY_COUNT);
        expect(normalizedHash(composed)).toBe(FROZEN_LEGACY_NORMALIZED_SHA256);
    });

    it('t() preserves representative values, supports {var} interpolation, rejects an unknown literal at compile time, and falls back to it at runtime', () => {
        expect(t('workspace.loading')).toBe('Loading your workspace…');
        expect(t('workspace.billing.currentPlan.version')).toBe('Version {version}');
        expect(t('workspace.billing.currentPlan.version', { version: '7' })).toBe('Version 7');
        expect(
            t('workspace.billing.iyzicoSandbox.amount', { amount: '120', currency: 'TRY' }),
        ).toBe('Amount 120 TRY');

        function assertRejectsUnknownLiteral(): void {
            // @ts-expect-error only literal WorkspaceTranslationKey values are accepted by t()
            t('workspace.__unknown_literal_not_in_catalog__');
        }
        void assertRejectsUnknownLiteral;

        const untypedT = t as (key: string, vars?: Record<string, string>) => string;
        const unknownKey = 'workspace.__unknown_literal_not_in_catalog__';
        expect(untypedT(unknownKey)).toBe(unknownKey);
    });
});
