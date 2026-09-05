export const menu = {
    'workspace.menu.loading': 'Loading your menu…',
    'workspace.menu.heading': 'Menu',
    'workspace.menu.operational.description':
        'Build and edit the categories and items on your live menu catalog for the selected location.',
    'workspace.menu.error': 'Your menu could not be loaded. Refresh the page to try again.',
    'workspace.menu.empty.location.body':
        'A menu belongs to a location, and this workspace does not have one yet. Add a location first and the menu catalog opens here.',
    'workspace.menu.empty.location.cta': 'Add a location',
    'workspace.menu.empty.brand.body':
        'A menu belongs to a location, and a location belongs to a brand. Set up your brand first, then add a location.',
    'workspace.menu.empty.brand.cta': 'Set up brand',

    /*
        MENÜ DEĞİŞİKLİK GEÇMİŞİ (FF-163) — "dün kebabın fiyatını kim
        değiştirdi?"

        `notRecorded` bu kümenin en önemli anahtarıdır ve kolayca
        atlanabilirdi: sıralama, "bugün tükendi" ve yayınlama bilerek ize
        yazılmıyor. Bunu SÖYLEMEYEN bir liste, olmayan bir kaydı "olmadı"
        diye okutur — sahip yayını göremeyince "menü hiç yayına çıkmamış"
        der. Eksik bir denetim izi TAM görünür.

        `menuAiImported` ile `menuImported` AYRI cümlelerdir: CSV'deki
        sayıyı insan yazdı, fotoğraftakini bir model OKUDU. Sahip yanlış bir
        fiyat bulduğunda ihtiyacı olan ayrım tam budur ve ayrım renkle
        değil KELİMEYLE yapılır.

        Tahmini süre, "yakında", sayaç ya da rozet diyen bir anahtar YOK.
        Kayıt yoksa yazılan tek şey `empty`dir.
    */
    'workspace.menu.audit.heading': 'Change history',
    'workspace.menu.audit.region': 'Menu change history',
    'workspace.menu.audit.help':
        'Who changed a price, a name, visibility or allergens on this menu, and what the value was before.',
    'workspace.menu.audit.notRecorded':
        'Not recorded here: reordering, sold-out marks, and publishing. Publishing keeps its own history on the publish screen.',
    'workspace.menu.audit.loading': 'Loading the change history…',
    'workspace.menu.audit.error': 'The change history could not be loaded.',
    'workspace.menu.audit.retry': 'Try again',
    'workspace.menu.audit.empty': 'No change has been recorded yet.',
    'workspace.menu.audit.previous': 'Previous',
    'workspace.menu.audit.next': 'Next',
    'workspace.menu.audit.page': 'Page {page} / {total}',
    'workspace.menu.audit.change': '{before} → {after}',
    'workspace.menu.audit.actor.unknown': 'Unknown person',
    'workspace.menu.audit.subject.unknown': 'Name not recorded',
    'workspace.menu.audit.at.unknown': 'Time not recorded',
    'workspace.menu.audit.visibility.visible': 'Visible',
    'workspace.menu.audit.visibility.hidden': 'Hidden',
    'workspace.menu.audit.action.unknown': 'Change of an unknown kind',
    'workspace.menu.audit.action.menuCreated': 'Menu created',
    'workspace.menu.audit.action.menuRenamed': 'Menu renamed',
    'workspace.menu.audit.action.menuDeleted': 'Menu deleted',
    'workspace.menu.audit.action.menuImported': 'Imported from a CSV file',
    'workspace.menu.audit.action.menuAiImported': 'Applied from a photo reading',
    'workspace.menu.audit.action.categoryAdded': 'Category added',
    'workspace.menu.audit.action.categoryRenamed': 'Category renamed',
    'workspace.menu.audit.action.categoryRemoved': 'Category removed',
    'workspace.menu.audit.action.itemAdded': 'Item added',
    'workspace.menu.audit.action.itemRenamed': 'Item renamed',
    'workspace.menu.audit.action.itemPriceChanged': 'Price changed',
    'workspace.menu.audit.action.itemVisibilityChanged': 'Visibility changed',
    'workspace.menu.audit.action.itemAllergensChanged': 'Allergens changed',
    'workspace.menu.audit.action.itemRemoved': 'Item removed',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof menu, string> {}
}
