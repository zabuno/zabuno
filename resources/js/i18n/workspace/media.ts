export const media = {
    'workspace.media.heading': 'Media',
    'workspace.media.operational.description':
        'Upload images and manage the media library that feeds your published menu — every asset is scanned before it can be used.',
    'workspace.media.upload.region': 'Media upload',
    'workspace.media.upload.heading': 'Add photos',
    'workspace.media.upload.button': 'Upload',
    'workspace.media.upload.uploading': 'Uploading media…',
    // FF-68 (`docs/49` Faz 2): ilerleme görünür, yeniden deneme aynı anahtarla.
    'workspace.media.upload.uploading.progress': 'Uploading… {percent}%',
    'workspace.media.upload.retry': 'Try again',
    'workspace.media.upload.error.tooLarge':
        'This file is {size} MB; the limit is {max} MB. Export a smaller copy and try again.',
    'workspace.media.upload.error.tooManyPixels':
        'This image is {width} × {height} pixels — more than {max} megapixels. Export a smaller copy and try again.',
    'workspace.media.upload.failed': 'Media upload failed. Your selection was kept.',
    'workspace.media.upload.complete': 'Media upload complete.',
    'workspace.media.security.explanation':
        'Every image is scanned and checked by a person before it can appear on your menu.',
    'workspace.media.upload.field.file': 'File',
    // Sürükle-bırak alanı. Öncesinde ham bir `<input type=file>` vardı ve
    // tarayıcı onu İŞLETİM SİSTEMİNİN dilinde çiziyordu: uygulama İngilizce
    // iken düğmede "Dosya Seç" yazıyordu. Kendi alanımızı çizince metin de
    // katalogdan gelir.
    'workspace.media.upload.dropzone.label': 'Drop an image here, or choose a file',
    'workspace.media.upload.dropzone.hint': 'JPEG, PNG or WebP',
    'workspace.media.upload.dropzone.choose': 'Choose a file',
    'workspace.media.upload.dropzone.active': 'Release to add this image',
    'workspace.media.upload.selected.replace': 'Choose a different file',
    'workspace.media.upload.selected.dimensions': '{width} × {height} pixels',
    // Slot gereksinimleri YÜKLEMEDEN ÖNCE görünür. Öncesinde kullanıcı
    // bulanık görseli ancak yayınladıktan sonra fark ediyordu.
    'workspace.media.upload.requirement.minimum': 'At least {width} × {height} pixels',
    'workspace.media.upload.requirement.aspect': 'Aspect ratio {aspect}',
    'workspace.media.upload.requirement.formats': 'Formats: {formats}',
    'workspace.media.upload.error.tooSmall':
        'This image is {width} × {height}. {slot} needs at least {min}. A smaller image would look blurred, because it is never enlarged.',
    // Alternatif metin bir erişilebilirlik yükümlülüğüdür; ne olduğunu
    // bilmeyen kullanıcı boş bırakır ya da dosya adını yazar.
    'workspace.media.upload.field.altText': 'Alt text',
    'workspace.media.upload.field.altText.hint':
        'Describe the image for people who cannot see it, for example "grilled lamb chops on a wooden board".',
    'workspace.media.upload.error.file.required': 'Choose a file to upload.',
    'workspace.media.upload.error.altText.required': 'Enter alt text for this image.',
    'workspace.media.upload.error.assetSlot.required': 'Choose where this image will be used.',
    // "Asset slot" bir İÇ KAVRAMDIR. Restoran sahibi bir görsel yüklerken
    // "slot" seçmez; o görselin NEREDE görüneceğini söyler. Etiket
    // kullanıcının dilinde yazılır, kod tarafındaki anahtarlar aynı kalır
    // (`docs/47` Kural 4 ve `docs/44` — iç kelime dağarcığı kullanıcıya
    // taşınmaz).
    'workspace.media.upload.field.assetSlot': 'Where will this image be used?',
    'workspace.media.upload.field.assetSlot.placeholder': 'Choose a place',
    'workspace.media.upload.field.assetSlot.hero': 'Hero',
    'workspace.media.upload.field.assetSlot.cards': 'Cards',
    'workspace.media.upload.field.assetSlot.pricing': 'Pricing',
    'workspace.media.upload.field.assetSlot.features': 'Features',
    'workspace.media.upload.field.assetSlot.testimonial': 'Testimonial',
    'workspace.media.upload.field.assetSlot.avatar': 'Avatar',
    'workspace.media.upload.field.assetSlot.logo': 'Logo',
    'workspace.media.upload.field.assetSlot.cover': 'Cover',
    'workspace.media.upload.field.assetSlot.favicon': 'Favicon',
    'workspace.media.upload.field.assetSlot.ogImage': 'OG image',
    'workspace.media.upload.field.assetSlot.appIcon': 'App icon',
    'workspace.media.upload.field.assetSlot.profileAvatar': 'Profile/avatar',
    'workspace.media.upload.field.assetSlot.categoryHero': 'Category hero',
    'workspace.media.upload.field.assetSlot.itemImage': 'List/card/detail item',
    'workspace.media.upload.field.assetSlot.gallery': 'Gallery',
    'workspace.media.upload.field.assetSlot.printLogo': 'Print logo',
    'workspace.media.upload.field.assetSlot.emailHeader': 'Header/splash/push',
    'workspace.media.library.region': 'Media library',
    'workspace.media.library.heading': 'Your photos',
    'workspace.media.library.unavailable': 'No media assets yet.',
    'workspace.media.library.loading': 'Loading media library…',
    'workspace.media.library.error': 'Media library could not be loaded.',
    'workspace.media.lifecycle.heading': 'Lifecycle',
    'workspace.media.lifecycle.quarantine': 'Quarantine',
    'workspace.media.lifecycle.validation': 'Validation',
    'workspace.media.lifecycle.securityScan': 'Security scan',
    'workspace.media.lifecycle.derivatives': 'Derivatives',
    'workspace.media.library.slots.heading': 'Slot categories',
    'workspace.media.library.slots.category.corporateSite': 'Corporate site',
    'workspace.media.library.slots.category.restaurant': 'Restaurant',
    'workspace.media.library.slots.category.menu': 'Menu',
    'workspace.media.library.slots.category.product': 'Product',
    'workspace.media.library.slots.category.qr': 'QR',
    'workspace.media.library.slots.category.email': 'Email',
    'workspace.media.library.slots.status.noAssetsYet': '{category}: no assets yet.',
    'workspace.media.library.assets.heading': 'Assets',
    'workspace.media.library.asset.status.quarantined': 'Scan pending (quarantined)',
    'workspace.media.library.asset.status.scanning': 'Scanning in progress',
    'workspace.media.library.asset.status.rejected': 'Rejected — failed security scan',
    'workspace.media.library.asset.status.accepted': 'Accepted — awaiting processing',
    'workspace.media.library.asset.status.processing': 'Processing',
    'workspace.media.library.asset.status.ready': 'Ready',
    'workspace.media.library.asset.status.failed': 'Processing failed',
    'workspace.media.library.asset.status.unknown': 'Status unavailable',
    'workspace.media.library.asset.delete': 'Delete',
    // Her satırdaki silme düğmesinin adı aynıydı ("Delete"). Ekran okuyucu
    // kullanan biri, üç düğme arasında hangisinin hangi görseli sildiğini
    // ayırt edemiyordu — ve bu geri alınamaz bir eylem.
    'workspace.media.library.asset.delete.named': 'Delete {name}',
    // Kullanıcı alt metni boş bıraktıysa bile satırın bir adı olmalı.
    // Alternatif, veritabanı kimliğini göstermekti; o bir ad değildir.
    'workspace.media.library.asset.untitled': 'Untitled image',
    'workspace.media.library.asset.delete.failed':
        'Media asset deletion failed. Your item was kept.',
    'workspace.media.library.asset.delete.complete': 'Media asset deleted.',
    // Silme artık ÇÖPE atar (`docs/49` Faz 5): bildirim geri yolu söyler.
    'workspace.media.library.asset.delete.trashed':
        'Moved to trash. You can restore it from the Trash tab.',
    'workspace.media.library.asset.details.named': 'Open details for {name}',
    'workspace.media.library.asset.usageCount': 'used in {count}',
    'workspace.media.library.tabs.label': 'Media library sections',
    'workspace.media.library.tabs.library': 'Library',
    'workspace.media.library.tabs.trash': 'Trash',
    'workspace.media.library.filters.label': 'Filter assets',
    'workspace.media.library.filters.search': 'Search',
    'workspace.media.library.filters.searchPlaceholder': 'Alt text or file name',
    'workspace.media.library.filters.slot': 'Slot',
    'workspace.media.library.filters.status': 'Status',
    'workspace.media.library.filters.any': 'Any',
    'workspace.media.library.filters.unusedOnly': 'Unused only',
    'workspace.media.library.filters.noMatch': 'No assets match these filters.',
    'workspace.media.library.filters.count': 'Showing {shown} of {total}.',
    'workspace.media.library.view.label': 'View',
    'workspace.media.library.view.list': 'List',
    'workspace.media.library.view.grid': 'Grid',
    'workspace.media.library.detail.noPreview': 'No preview yet',
    'workspace.media.library.detail.file': 'File',
    'workspace.media.library.detail.size': 'Size',
    'workspace.media.library.detail.uploaded': 'Uploaded',
    'workspace.media.library.detail.slot': 'Slot',
    'workspace.media.library.detail.duplicate': 'Same file as asset #{id} in this workspace.',
    'workspace.media.library.detail.reprocess': 'Regenerate sizes',
    'workspace.media.library.detail.reprocessed':
        'Sizes regenerated as a new version. The original is untouched.',
    'workspace.media.library.detail.actionFailed': 'That did not work. Nothing was changed.',
    'workspace.media.library.usages.heading': 'Used in',
    'workspace.media.library.usages.loading': 'Checking where this image is used…',
    'workspace.media.library.usages.failed': 'Could not check where this image is used.',
    'workspace.media.library.usages.none': 'Not used anywhere yet.',
    'workspace.media.library.usages.live': 'live menu',
    'workspace.media.library.usages.draft': 'draft',
    'workspace.media.library.versions.heading': 'Versions',
    'workspace.media.library.versions.loading': 'Loading versions…',
    'workspace.media.library.versions.failed': 'Could not load versions.',
    'workspace.media.library.versions.none': 'No processed versions yet.',
    'workspace.media.library.versions.row': 'v{number} · {by} · {renditions} sizes',
    'workspace.media.library.versions.current': 'current',
    'workspace.media.library.versions.restore': 'Restore v{number}',
    'workspace.media.library.versions.restored': 'v{number} restored as a new version.',
    'workspace.media.library.impact.title': 'Delete {name}?',
    'workspace.media.library.impact.loading': 'Checking where this image is used…',
    'workspace.media.library.impact.failed': 'Could not check usage. Nothing was deleted.',
    'workspace.media.library.impact.lead': 'This image is used in {count} place(s):',
    'workspace.media.library.impact.blocked':
        'It appears in a live menu, so it cannot be deleted. Publish a menu without it first.',
    'workspace.media.library.impact.trashNote':
        'Those items will show a placeholder. The image goes to trash and can be restored.',
    'workspace.media.library.impact.confirm': 'Detach from {count} and move to trash',
    'workspace.media.library.impact.cancel': 'Keep it',
    'workspace.media.library.trash.heading': 'Trash',
    'workspace.media.library.trash.lead':
        'Deleted images stay here for {days} days, then are removed for good.',
    'workspace.media.library.trash.loading': 'Loading trash…',
    'workspace.media.library.trash.failed': 'Trash could not be loaded.',
    'workspace.media.library.trash.empty': 'Trash is empty.',
    'workspace.media.library.trash.restore': 'Restore',
    'workspace.media.library.trash.restore.named': 'Restore {name}',
    'workspace.media.library.trash.restored': 'Restored. It is back in the library.',
    'workspace.media.library.trash.restoreFailed': 'Restore failed. It is still in the trash.',
    // FF-71: asıl indirme + kota göstergesi (`docs/49` Faz 6-7).
    'workspace.media.library.detail.download': 'Download original',
    'workspace.media.library.detail.downloadReady':
        'Download link opened. It stays valid for 10 minutes.',
    'workspace.media.quota.region': 'Media quota',
    'workspace.media.quota.heading': 'Storage',
    'workspace.media.quota.plan': 'Plan: {plan}',
    'workspace.media.quota.storage': 'Originals',
    'workspace.media.quota.assets': 'Images',
    'workspace.media.quota.monthly': 'Uploads this month',
    'workspace.media.quota.ratio': '{used} of {limit}',
    'workspace.media.quota.unlimited': 'no limit',
    'workspace.media.quota.note':
        'Deleted images stay in trash for {days} days and still count until then. Generated sizes are free.',
    // FF-76 (`docs/101` A5/A8): çoklu yükleme, ad düzeltme, gürültü katlanır.
    'workspace.media.upload.more.label': 'More photos to upload',
    'workspace.media.upload.more.lead':
        '{count} more photo(s) will be uploaded with the same place. Name each one:',
    'workspace.media.upload.more.altFor': 'Name for {name}',
    'workspace.media.upload.more.progress': 'Uploading photo {done} of {total}…',
    'workspace.media.library.empty.hint':
        'Drop your first photo on the left. It shows up here as soon as it is checked.',
    'workspace.media.library.how.summary': 'How photos are handled (places and checks)',
    'workspace.media.library.detail.altText': 'Name (alt text)',
    'workspace.media.library.detail.rename': 'Save name',
    'workspace.media.library.detail.renamed': 'Name saved.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof media, string> {}
}
