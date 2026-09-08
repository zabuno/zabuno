/**
 * EKİP TABLOSUNUN MASAÜSTÜ DİZELERİ — `docs/151` §B.
 *
 * Telefonda bu cümlelerin karşılığı YOKTUR: orada sütun başlığı olan bir
 * tablo yok, çoklu seçim yok, toplu rol değişimi yok. Sütun başlığını
 * telefona da indirmek, çizilmeyen bir tabloyu her açılışta indirmek
 * olurdu.
 *
 * Sayıya bağlı çoğul YOK (`docs/86`).
 */
export const teamDesktop = {
    'workspace.team.members.desktop.shortcuts':
        'Arrow keys move · Space selects · Shift+arrow extends',
    'workspace.team.members.desktop.column.name': 'Name',
    'workspace.team.members.desktop.column.email': 'Email',
    'workspace.team.members.desktop.column.role': 'Role',
    'workspace.team.members.desktop.menu': 'Member actions',
    /*
        ERİŞİLEBİLİR AD KATALOGDAN GELİR, ARAYÜZDE BİRLEŞTİRİLMEZ (FF-213).

        Satırın adını kodda `ad + ' · ' + eposta` diye kurmak, hem ayıracı
        çeviriden kaçırır hem de sağdan sola yazılan dillerde parçaların
        sırasını dondurur. Tek anahtar, yer tutuculu.
    */
    'workspace.team.members.desktop.row.label': '{name}, {email}, {role}',
    'workspace.team.members.desktop.remove.named': 'Remove {name}',
    'workspace.team.members.desktop.bulkRegion': 'Actions for the selected members',
    'workspace.team.members.desktop.selected': '{count} selected',
    'workspace.team.members.desktop.clearSelection': 'Clear selection',
    /*
        TOPLU ROL DEĞİŞİMİ, MASAÜSTÜNÜN ASIL KAZANCI.

        Sekiz kişiyi tek tek açıp rolünü değiştirmek, bir restoranın sezon
        başında saatini alıyor. Etiket ne yapılacağını TAM söyler: hangi rol
        ve kaç kişi.
    */
    'workspace.team.members.desktop.bulkRole': 'Set role for {count}',
    /*
        TOPLU İŞLEMİN SONUCU TEK CÜMLEDE ve başarısızlık AYRI sayılır.
        "8 updated" deyip ikisinin tutmadığını yutmak, bitmemiş bir işi
        bitmiş göstermek olurdu.
    */
    'workspace.team.members.desktop.outcome': '{ok} updated, {failed} did not go through.',
    /*
        SAHİP SATIRI TOPLU İŞLEMİN DIŞINDADIR ve bu sessizce yapılmaz.
        Sahiplik silinmez, DEVREDİLİR; seçime giren sahip satırı atlanır ve
        atlandığı yazılır — yoksa sahip kendi satırını da değişmiş sanar.
    */
    'workspace.team.members.desktop.kept':
        '{count} row(s) were left as they are: ownership is transferred, not changed here.',
} as const;

declare module '../workspace-desktop' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceDesktopTranslationCatalog extends Record<keyof typeof teamDesktop, string> {}
}
