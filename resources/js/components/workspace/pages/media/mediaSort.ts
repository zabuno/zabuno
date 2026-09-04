/**
 * Kütüphane sıralaması.
 *
 * Sıralama YALNIZ gerçekten elimizde olan alanlar üzerinden yapılır: yükleme
 * zamanı, ad ve boyut. "En çok kullanılan" gibi bir sıra, sayacı olmayan
 * satırlarda uydurma bir düzen kurardı.
 *
 * Sabitler bileşen dosyasının DIŞINDA durur: bir dosya hem bileşen hem sabit
 * yayımladığında geliştirme sunucusunun sıcak yenilemesi o dosyayı her
 * değişiklikte baştan yükler.
 */
export type MediaSortKey = 'newest' | 'name' | 'largest';

export const MEDIA_SORT_ORDER: readonly MediaSortKey[] = ['newest', 'name', 'largest'];

export const MEDIA_SORT_LABEL_KEY = {
    newest: 'workspace.media.library.sort.newest',
    name: 'workspace.media.library.sort.name',
    largest: 'workspace.media.library.sort.largest',
} as const;
