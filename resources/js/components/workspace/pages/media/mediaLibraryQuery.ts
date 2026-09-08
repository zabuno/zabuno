import type { MediaAsset } from '../MediaPage';
import type { MediaFolderId } from './MediaFolderRail';
import { displayName } from './mediaFormat';
import type { MediaSortKey } from './mediaSort';

/**
 * KÜTÜPHANEYİ SÜZME VE SIRALAMA — BAŞSIZ (`docs/151` §4, `docs/153` §4).
 *
 * Bu kural `docs/153`'ün en pahalı maddesidir: cihaza göre ayrışan şey
 * SUNUM ve ETKİLEŞİMdir, gerçeğin kendisi değil. "Bu görsel listede
 * görünür mü" bir sunum sorusu DEĞİL, bir ürün kararıdır — hangi klasör,
 * hangi yuva, hangi durum, kullanılmayanlar.
 *
 * İki kopya yazılsaydı arıza sessiz olurdu: aynı arama, aynı akşam,
 * telefonda dört, masaüstünde beş sonuç verirdi ve hangisinin doğru
 * olduğunu kimse söyleyemezdi. Bir ekranın "yanlış çizmesi" görülür;
 * "farklı SÜZMESİ" görülmez.
 *
 * Kod bu paketle YAZILMADI, TAŞINDI: `MediaLibraryRegion` içindeki
 * karşılaştırıcı ve süzgeç buraya alındı ve o bölge artık buradan okuyor.
 * Yani masaüstü sürümü ikinci bir doğruluk kaynağı doğurmuyor.
 */

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

export type MediaLibraryQuery = {
    /** Serbest arama: alt metin ya da dosya adı. */
    query: string;
    /** Yuva süzgeci; boş dize = hepsi. */
    slot: string;
    /** Durum süzgeci; boş dize = hepsi. */
    status: string;
    /** Yalnız hiçbir yerde kullanılmayanlar. */
    unusedOnly: boolean;
    folderId: MediaFolderId | null;
    sort: MediaSortKey;
};

export const EMPTY_MEDIA_LIBRARY_QUERY: MediaLibraryQuery = {
    query: '',
    slot: '',
    status: '',
    unusedOnly: false,
    folderId: null,
    sort: 'newest',
};

/** Görünen liste: süzülmüş ve sıralanmış. Girdi dizisi DEĞİŞMEZ. */
export function selectVisibleAssets(
    assets: MediaAsset[],
    { query, slot, status, unusedOnly, folderId, sort }: MediaLibraryQuery,
): MediaAsset[] {
    const needle = query.trim().toLocaleLowerCase();

    const matched = assets.filter((asset) => {
        if (folderId !== null && asset.folderId !== folderId) return false;
        if (slot !== '' && asset.slot !== slot) return false;
        if (status !== '' && asset.status !== status) return false;
        if (unusedOnly && (asset.usageCount ?? 0) > 0) return false;
        if (needle === '') return true;

        return (
            asset.altText.toLocaleLowerCase().includes(needle) ||
            (asset.originalName ?? '').toLocaleLowerCase().includes(needle)
        );
    });

    return [...matched].sort((a, b) => compareAssets(a, b, sort));
}
