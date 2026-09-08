/**
 * SİPARİŞ KUYRUĞUNUN MASAÜSTÜ DİZELERİ — `docs/151`, `docs/153` §6.
 *
 * Bu dosya `docs/153` ile doğdu ama ORTAK katalogda (`workspace/ordering.ts`)
 * duruyordu; yani telefon paketi de indiriyordu. `docs/153` §8 bunu bir borç
 * olarak yazmıştı (833 B) ve burası o borcun kapandığı yerdir.
 *
 * Buraya YALNIZ işaretleyici/klavye yüzeyinde çizilen dizeler yazılır.
 * Ölçüt bir ekran adı değil: bir cümle dokunmalı yüzeyde de okunuyorsa
 * ortak katalogda kalır — iki tabloya birden yazılan bir dize, bir gün
 * yalnız birinde düzeltilir.
 */
export const orderingDesktop = {
    /*
        MASAÜSTÜ KUYRUĞU (`docs/153`) — yalnız işaretleyici/klavye
        yüzeyinde çizilen dizeler.

        Bu anahtarların telefonda bir karşılığı YOKTUR ve olmamalı: dokunmada
        `hover` yok, sağ tık yok, Enter yok. "Ok tuşlarıyla gezin" cümlesini
        telefonda göstermek, olmayan bir yeteneği vaat etmek olurdu.

        Sayıya bağlı çoğul YOK (`docs/86`): "{count} selected" hem 1 hem 9
        için doğrudur ve dillerin çoğul kuralları bu depoda taşınmıyor.
    */
    'workspace.orders.queue.desktop.shortcuts':
        'Arrow keys move · Space selects · Enter approves · R rejects',
    'workspace.orders.queue.desktop.selected': '{count} selected',
    'workspace.orders.queue.desktop.bulkRegion': 'Actions for the selected orders',
    'workspace.orders.queue.desktop.bulkConfirm': 'Approve {count}',
    'workspace.orders.queue.desktop.bulkReject': 'Reject {count}',
    'workspace.orders.queue.desktop.clearSelection': 'Clear selection',
    'workspace.orders.queue.desktop.bulkReason': 'Why are you rejecting these {count} orders?',
    /*
        TOPLU İŞLEMİN SONUCU TEK CÜMLEDE, ve başarısızlık AYRI sayılır:
        "8 approved" deyip iki tanesinin tutmadığını yutmak, kasadaki kişiye
        bitmiş bir işi bitmiş göstermek olurdu.
    */
    'workspace.orders.queue.desktop.outcome': '{ok} approved, {failed} did not go through.',
    'workspace.orders.queue.desktop.menu': 'Order actions',
    'workspace.orders.queue.desktop.detail.region': 'Selected order',
    'workspace.orders.queue.desktop.detail.empty':
        'Pick an order from the list to read its lines here.',
} as const;

declare module '../workspace-desktop' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceDesktopTranslationCatalog extends Record<
        keyof typeof orderingDesktop,
        string
    > {}
}
