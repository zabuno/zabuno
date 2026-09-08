/**
 * MEDYA KÜTÜPHANESİNİN MASAÜSTÜ DİZELERİ — `docs/151` §B.
 *
 * Bu cümlelerin telefonda karşılığı YOKTUR ve olmamalı: dokunmada ok tuşu,
 * Shift ile aralık seçimi, sağ tık ve kalıcı ayrıntı bölmesi yok. "Ok
 * tuşlarıyla gezin" yazan bir telefon ekranı, olmayan bir yeteneği vaat
 * eder.
 *
 * Sayıya bağlı çoğul YOK (`docs/86`).
 */
export const mediaDesktop = {
    /*
        KISAYOLLAR YAZILI DURUR. Klavyeyle çalışan bir ızgara, varlığı
        SÖYLENMEDİKÇE yoktur: kimse Enter'a basmayı denemez.
    */
    'workspace.media.library.desktop.shortcuts':
        'Arrow keys move · Space selects · Shift+arrow extends · Delete removes',
    'workspace.media.library.desktop.grid': 'Media files',
    'workspace.media.library.desktop.menu': 'File actions',
    'workspace.media.library.desktop.bulkRegion': 'Actions for the selected files',
    'workspace.media.library.desktop.detail.region': 'Selected file',
    'workspace.media.library.desktop.detail.empty':
        'Pick a file from the grid to read its details here.',
    /*
        AYRINTI BÖLMESİ SİLMEYİ DE TAŞIR, çünkü seçili dosyanın karşısında
        sorulan ikinci soru odur. Etiket kısadır: bölmenin başlığı zaten
        hangi dosyaya bakıldığını söylüyor.
    */
    'workspace.media.library.desktop.detail.usage': 'Used in {count} place(s)',
    /*
        DOSYA SAYISI İKİ SAYIYLA: kaç tanesi görünüyor VE kaç tanesi var.
        Tek sayı, açık bir süzgeci görünmez kılardı — sahip "yüklediğim
        fotoğraf nerede" diye ararken listenin süzülmüş olduğunu bilmeli.
    */
    'workspace.media.library.desktop.count': '{shown} of {total} files',
} as const;

declare module '../workspace-desktop' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceDesktopTranslationCatalog extends Record<
        keyof typeof mediaDesktop,
        string
    > {}
}
