/**
 * PUAN EKRANININ MASAÜSTÜ DİZELERİ — `docs/151` §B.
 *
 * Telefonda karşılığı YOK: orada liste sıralanmaz (sunucunun sırası
 * okunur), ok tuşu yoktur ve yanıt kutusu satırın İÇİNDEDİR — kalıcı bir
 * bölme yoktur, çünkü ikinci sütun yoktur.
 *
 * Sayıya bağlı çoğul YOK (`docs/86`).
 */
export const ratingsDesktop = {
    'workspace.ratings.desktop.shortcuts': 'Arrow keys move · Enter opens the reply box',
    'workspace.ratings.desktop.list': 'Rated products',
    'workspace.ratings.desktop.detail.region': 'Selected product',
    'workspace.ratings.desktop.detail.empty':
        'Pick a product from the list to read and answer it here.',
    /*
        SIRALAMA MASAÜSTÜNÜN ASIL KAZANCI: kırk ürünlük bir menüde sahibin
        sorusu "hangisi en düşük?"tür ve telefonda o soru ancak kaydırarak
        cevaplanır.

        "En düşük" sıralaması PUANI OLMAYAN satırı en sona atar ve bu bir
        tercih değil bir zorunluluk: eşiği geçmemiş bir ürünü "en kötü"
        diye listenin başına koymak, olmayan bir ölçümü bir yargıya
        çevirirdi.
    */
    'workspace.ratings.desktop.sort': 'Sort: {label}',
    'workspace.ratings.desktop.sort.lowest': 'Lowest score',
    'workspace.ratings.desktop.sort.most': 'Most votes',
    'workspace.ratings.desktop.sort.name': 'Name',
} as const;

declare module '../workspace-desktop' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceDesktopTranslationCatalog extends Record<
        keyof typeof ratingsDesktop,
        string
    > {}
}
