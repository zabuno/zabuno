import type { MediaFolderId } from './MediaFolderRail';
import type { MediaSortKey } from './mediaSort';
import { displayName } from './mediaFormat';
import type { MediaAsset } from '../MediaPage';

/**
 * KÜTÜPHANENİN SORUSU — tek yerde (`docs/49` Faz 4, `docs/153` §4).
 *
 * "Hangi dosyalar görünsün ve hangi sırayla?" sorusunun cevabı, dosyaları
 * ÇİZEN yüzeyden bağımsızdır: dokunmatik liste de, işaretleyici ızgarası da
 * aynı cevabı vermek zorundadır. İki yüzey kendi süzgecini yazsaydı, sahip
 * telefonda "3 dosya", masaüstünde "4 dosya" görebilir ve hangisinin doğru
 * olduğunu bilemezdi — üstelik fark ancak birinde bir kusur düzeltilip
 * ötekinde unutulduğu gün ortaya çıkardı.
 *
 * Burada ÇİZİM yoktur ve olmayacak: bu dosya bir bileşen tanımaz, bir
 * `t()` çağırmaz. Sıralama etiketi `mediaSort.ts`de, biçimleme
 * `mediaFormat.ts`de kalır.
 */
export type MediaLibraryQuery = {
    /** Arama metni; kabuktan ya da bölgenin kendi kutusundan gelir. */
    text: string;
    /** Yuva süzgeci; boş dize = süzgeç yok. */
    slot: string;
    /** Durum süzgeci; boş dize = süzgeç yok. */
    status: string;
    /** Yalnız hiçbir yerde kullanılmayan dosyalar. */
    unusedOnly: boolean;
    /** Seçili klasör; `null` = tüm klasörler. */
    folderId: MediaFolderId | null;
    sort: MediaSortKey;
};

/**
 * Süzgeç kutularının doldurulması için: listede GERÇEKTEN bulunan durumlar.
 *
 * Sıra sabittir ve yaşam döngüsünün kendi sırasıdır — alfabetik olsaydı
 * "rejected" ile "ready" yan yana düşer, sahibin gözü ikisini karıştırırdı.
 */
export const MEDIA_STATUS_ORDER = [
    'ready',
    'processing',
    'accepted',
    'scanning',
    'quarantined',
    'failed',
    'rejected',
] as const;

export function availableSlots(assets: MediaAsset[]): string[] {
    return Array.from(new Set(assets.map((asset) => asset.slot))).sort();
}

/**
 * Dönüş türü `string[]` DEĞİL, sabit listenin kendi birleşimidir.
 *
 * Süzgeç kutusunun etiketi `t('workspace.media.library.asset.status.' + s)`
 * ile kuruluyor ve `t()` yalnız KATALOGDA GERÇEKTEN OLAN bir anahtarı kabul
 * eder. Tür `string`e genişletilseydi derleyici o kontrolü yapamaz, katalogda
 * karşılığı olmayan bir durum kutuya ham anahtar adıyla düşerdi — ve bu
 * ancak o durumdaki bir dosya yüklendiği gün görünürdü.
 */
export type MediaStatusKey = (typeof MEDIA_STATUS_ORDER)[number];

export function availableStatuses(assets: MediaAsset[]): MediaStatusKey[] {
    const present = new Set(assets.map((asset) => asset.status));

    return MEDIA_STATUS_ORDER.filter((status) => present.has(status));
}

/**
 * Sıralama karşılaştırıcıları.
 *
 * Elimizde OLMAYAN alana göre sıralamayız: `createdAt` ya da `sizeBytes`
 * gelmediğinde satır sırası KORUNUR (kararlı sıralama), uydurma bir sıraya
 * itilmez.
 */
export function compareAssets(a: MediaAsset, b: MediaAsset, sort: MediaSortKey): number {
    if (sort === 'name') {
        return displayName(a).localeCompare(displayName(b));
    }

    if (sort === 'largest') {
        return (b.sizeBytes ?? 0) - (a.sizeBytes ?? 0);
    }

    const left = a.createdAt ? Date.parse(a.createdAt) : Number.NaN;
    const right = b.createdAt ? Date.parse(b.createdAt) : Number.NaN;

    if (Number.isNaN(left) && Number.isNaN(right)) return 0;
    if (Number.isNaN(left)) return 1;
    if (Number.isNaN(right)) return -1;

    return right - left;
}

/** Görünür dosyalar: süzülmüş ve sıralanmış. Girdi dizisi DEĞİŞMEZ. */
export function selectVisibleAssets(assets: MediaAsset[], query: MediaLibraryQuery): MediaAsset[] {
    const needle = query.text.trim().toLocaleLowerCase();
    const matched = assets.filter((asset) => {
        if (query.folderId !== null && asset.folderId !== query.folderId) return false;
        if (query.slot !== '' && asset.slot !== query.slot) return false;
        if (query.status !== '' && asset.status !== query.status) return false;
        if (query.unusedOnly && (asset.usageCount ?? 0) > 0) return false;
        if (needle === '') return true;
        return (
            asset.altText.toLocaleLowerCase().includes(needle) ||
            (asset.originalName ?? '').toLocaleLowerCase().includes(needle)
        );
    });

    return [...matched].sort((a, b) => compareAssets(a, b, query.sort));
}

/**
 * Süzgeç düğmesinin rozetindeki sayı: KAÇ süzgeç açık.
 *
 * Arama ve klasör bu sayıya GİRMEZ — ikisinin de ekranda kendi görünür
 * kontrolü var (arama kutusu, klasör hapı) ve rozete de eklenselerdi aynı
 * kısıt iki kez sayılırdı.
 */
export function activeFilterCount(query: MediaLibraryQuery): number {
    return (query.slot !== '' ? 1 : 0) + (query.status !== '' ? 1 : 0) + (query.unusedOnly ? 1 : 0);
}

/** Sahibe "hepsini mi görüyorum?" diye sorduran her kısıt — arama ve klasör dâhil. */
export function anyFilterActive(query: MediaLibraryQuery): boolean {
    return query.text !== '' || activeFilterCount(query) > 0 || query.folderId !== null;
}
