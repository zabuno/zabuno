import {
    interpolate,
    mergeCatalogModules,
    workspaceTranslations,
    type WorkspaceTranslationKey,
} from './workspace';

/**
 * MASAÜSTÜNÜN KENDİ DİZELERİ — `docs/151`, `docs/153` §9 M1.
 *
 * ── Kapatılan borç ────────────────────────────────────────────────────
 *
 * `docs/153` §8 bunu açıkça yazmıştı: bileşen kapısı bileşenleri ayırıyor
 * ama DİZELERİ ayırmıyordu. `i18n/workspace/*.ts` katalogları eager bir
 * glob ile toplandığı için, yalnız masaüstünde çizilen on bir dize
 * telefonun paketine de iniyordu (833 B). Sayı küçüktü ama yön yanlıştı:
 * masaüstüne her yeni ekran taşındığında telefon paketi biraz daha
 * büyüyordu.
 *
 * Bu dosya o yönü tersine çevirir. Masaüstüne özgü dizeler AYRI bir
 * klasörde (`./workspace-desktop/`) ve AYRI bir glob'la toplanır; bu
 * modülü yalnız `pages/desktop/**` altındaki bileşenler içeri alır,
 * dolayısıyla mobil paket tek baytını görmez.
 * `scripts/adaptive-bundle-gate` bunu her koşuda kanıtlar.
 *
 * ── Neden ayrı bir `t` ────────────────────────────────────────────────
 *
 * Paylaşılan `t`'ye masaüstü anahtarlarını EKLETMEK (çalışma zamanında
 * ortak tabloya yazmak) denenmedi ve denenmemeli: o zaman paylaşılan
 * tablonun içeriği hangi modülün önce yüklendiğine bağlı olurdu ve
 * "katalogda kaç anahtar var" sorusunun cevabı ölçüldüğü ana göre
 * değişirdi. Burada tablolar ayrık; arama önce masaüstü tablosuna, sonra
 * paylaşılan tabloya bakar.
 *
 * Adı da bilerek `t`: masaüstü bileşenleri tek bir `t` çağırır ve hem
 * ortak hem masaüstü anahtarını aynı çağrıyla okur. Değişen tek şey
 * içeri alma yoludur (`i18n/workspace-desktop`, `i18n/workspace` değil) —
 * ve yanlış yoldan çağıran bir masaüstü bileşeni derlenmez, çünkü
 * anahtar ötekinin tür birliğinde yoktur.
 *
 * ── Çeviri ────────────────────────────────────────────────────────────
 *
 * ALAN ADI DEĞİŞMEDİ. Bu anahtarlar PO/MO zincirinde hâlâ `workspace`
 * alan adının parçasıdır (`i18n/domains.ts` iki tabloyu birleştirir):
 * cihaz ayrımı bir PAKETLEME kararıdır, bir çeviri kararı değil. Ayrı bir
 * alan adı açsaydık altı dilin katalog iskeleti ikiye bölünür ve çevirmen
 * aynı ekranın cümlelerini iki dosyada arardı.
 */
const modules = import.meta.glob<{ default?: never; [key: string]: unknown }>(
    './workspace-desktop/*.ts',
    { eager: true },
);

export const workspaceDesktopTranslations = mergeCatalogModules(modules, 'workspace-desktop');

// Her `./workspace-desktop/*.ts` modülü bu arayüzü kendi anahtarlarıyla
// genişletir. `WorkspaceTranslationCatalog` ile aynı desen ve aynı sebep:
// bu dosya hiçbir modülü adıyla anmaz, yeni bir katalog eklemek burada tek
// satır bile değiştirmez.
// eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging target
export interface WorkspaceDesktopTranslationCatalog {}

export type WorkspaceDesktopTranslationKey = keyof WorkspaceDesktopTranslationCatalog;

/**
 * Masaüstü paketinin çevirmeni: önce masaüstü tablosu, sonra ortak tablo.
 *
 * Sıra önemlidir ve tek yönlüdür — masaüstü bir ortak anahtarı EZEMEZ,
 * çünkü çakışma zaten `i18n/domains.ts` tarafında kırılır. Buradaki sıra
 * yalnız aramanın maliyetiyle ilgili: masaüstü bileşeninin okuduğu
 * anahtarların çoğu masaüstü anahtarıdır.
 */
export function t(
    key: WorkspaceDesktopTranslationKey | WorkspaceTranslationKey,
    vars?: Record<string, string>,
): string {
    const template: string = workspaceDesktopTranslations[key] ?? workspaceTranslations[key] ?? key;

    return interpolate(template, vars);
}
