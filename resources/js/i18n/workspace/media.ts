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
    /*
        FF-158. Cümle 2026-09-05'e kadar "the limit is {max} MB" diyordu ve
        sınır her tür için aynıydı. Artık değil: bir SVG'nin sınırı bir
        fotoğrafınkiyle aynı olamaz. Hangi türün sınırına takıldığını
        söylemeyen bir ret, doğru dosyayı yükleyen sahibi de şüpheye
        düşürürdü ("PDF'im 30 MB, sınır 25 MB mı?").

        Cümle sınırın SEBEBİNİ anlatmaz. Aktarım zinciri, virüs tarayıcısı
        ve gövde sınırları sahibin sorunu değildir; gerekçe kodda yazar
        (`config/media-slots.php`), ekranda değil.
    */
    'workspace.media.upload.error.tooLarge':
        'This file is {size} MB; the limit for {kind} is {max} MB. Export a smaller copy and try again.',
    /*
        Türün SAHİBİN kelimesiyle adı. `image`/`vector`/`document` iç
        sözlüktür ve ekrana çıkmaz.
    */
    'workspace.media.upload.limit.kind.image': 'images',
    'workspace.media.upload.limit.kind.vector': 'SVG files',
    'workspace.media.upload.limit.kind.document': 'PDF files',
    'workspace.media.upload.error.tooManyPixels':
        'This image is {width} × {height} pixels — more than {max} megapixels. Export a smaller copy and try again.',
    'workspace.media.upload.failed': 'Media upload failed. Your selection was kept.',
    'workspace.media.upload.complete': 'Media upload complete.',
    /*
        FF-150. "Tamamlandı" tek başına yanıltıcıydı: dosya gerçekten
        ulaşıyor, ama güvenlik kapısını geçemeyince karantinada bekliyor ve
        menüde kullanılamıyor. Sahip ekrandan ayrılıp bir hafta sonra
        fotoğrafın menüde olmadığını görüyor, yanlış bir şey yaptığını
        sanıyordu.

        Cümle YALNIZ durumu söyler. "Yakında düzelecek", tahmini süre ya da
        yüzde YAZILMAZ: bilinen tek şey dosyanın beklediğidir. Sebebin
        kendisi sunucunun kaydettiği cümledir ve hemen altında durur.
    */
    'workspace.media.upload.held':
        'Your file reached us, but it cannot be used in your menu yet. Here is the reason:',
    /*
        Kusur dosyada ya da sahipte DEĞİL, ortamda. Ayarlar ekranındaki
        "virüs taraması çalışmıyor" satırıyla aynı sesle konuşur; sahip aynı
        gerçeği iki yerde iki farklı şekilde okumamalı.
    */
    'workspace.media.upload.held.notYours':
        'You did not do anything wrong, and this is not something you can switch on from here.',
    'workspace.media.security.explanation':
        'Every image is scanned and checked by a person before it can appear on your menu.',
    /*
        FF-151. Yukarıdaki cümle bir VAATTİR ve sahip onu daha hiçbir şey
        yüklemeden okur — ilk fotoğrafını seçerken kararını ona göre verir.
        Tarayıcının bağlı olmadığı bir ortamda o cümle okunduğu ilk saniyede
        yanlıştır ve yüklenen her dosya sessizce karantinada bekler.

        Cümle ORTAMIN gerçeğini söyler, bir kusuru değil; ardından
        `upload.held.notYours` gelir ve bunun sahip hatası olmadığını,
        panelden açılacak bir anahtar bulunmadığını yazar. Ayarlar
        ekranındaki `settings.security.virusScan.unavailable` satırıyla aynı
        sesle konuşur: sahip aynı gerçeği iki yerde iki farklı şekilde
        okumamalı. Süre, yüzde ya da "yakında düzelecek" YOK.
    */
    'workspace.media.security.explanation.unavailable':
        'No virus scanner is connected in this environment, so images you upload are not checked and wait in quarantine instead of appearing on your menu.',
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
    /*
        FF-158. Liste en küçük ölçüyü, oranı ve biçimleri söylüyordu ama
        BOYUTU söylemiyordu: sahip "png kabul ediliyor" okuyup taramasını
        yüklüyor, sınırı ancak ret cümlesinde öğreniyordu. Bir slot birden
        çok tür kabul edebilir ve onların sınırları aynı değildir — bu
        yüzden metin tek bir sayı değil, hazır bir liste alır.
    */
    'workspace.media.upload.requirement.maxSize': 'Largest file size: {limits}',
    /*
        KIRPMA (FF-129). Sunucudaki işleyici slotun oranına göre MERKEZDEN
        kırpıyor ve bunu kullanıcıya hiç sormuyordu. Yemek fotoğrafında bu
        masum bir varsayım değil: tabak çoğu zaman merkezde durmaz ve
        sahibi sonucu ancak yayımladıktan sonra görür.
    */
    'workspace.media.crop.heading': 'Choose the part you want',
    'workspace.media.crop.help':
        'This slot uses a fixed shape, so part of the photo is cut off. Drag to move the frame and use the slider to zoom in.',
    'workspace.media.crop.zoom': 'Zoom',
    'workspace.media.crop.reset': 'Centre the frame',
    'workspace.media.crop.result': 'Will be uploaded as {width} × {height} pixels.',
    'workspace.media.crop.frame': 'Crop frame — drag to move',

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
    /*
        Denetim izi (FF-97, `docs/49` Faz 7 madde 4). "Bu fotoğrafı kim
        sildi?" bir restoranda gerçek bir sorudur ve cevabı hiçbir ekranda
        yoktu.
    */
    'workspace.media.audit.heading': 'Who did what',
    'workspace.media.audit.help':
        'Every upload, rename, deletion and restore is recorded here. Records are never edited or removed.',
    'workspace.media.audit.actor.unknown': 'unknown person',
    'workspace.media.audit.action.uploaded': 'Uploaded photo #{id}',
    'workspace.media.audit.action.renamed': 'Renamed photo #{id}',
    'workspace.media.audit.action.trashed': 'Moved photo #{id} to trash',
    'workspace.media.audit.action.restored': 'Restored photo #{id} from trash',
    'workspace.media.audit.action.reprocessed': 'Regenerated sizes for photo #{id}',
    'workspace.media.audit.action.versionRestored': 'Restored an older version of photo #{id}',
    'workspace.media.audit.action.downloaded': 'Requested the original of photo #{id}',
    'workspace.media.audit.action.unknown': 'Acted on photo #{id}',
    /*
        FF-131 — MEDYA KENDİ KABUĞU OLAN BİR UYGULAMA (kanonik kaynak:
        `docs/reference/media-manager/`, gerekçe `docs/108`). Kabuğun kendi
        adı, kendi araması, kendi bölüm gezintisi ve solda klasör şeridi
        var; kütüphanenin de kendi araç çubuğu.
    */
    'workspace.media.shell.sections': 'Media sections',
    'workspace.media.shell.search': 'Search media',
    'workspace.media.shell.search.placeholder': 'File name or description',
    'workspace.media.shell.search.clear': 'Clear search',
    'workspace.media.shell.queue': 'Queue',
    'workspace.media.folders.heading': 'Folders',
    'workspace.media.folders.all': 'All files',
    'workspace.media.folders.page': 'Page {page} of {total}',
    'workspace.media.folders.previous': 'Previous folders',
    'workspace.media.folders.next': 'More folders',
    'workspace.media.library.filters.toggle': 'Filter',
    'workspace.media.library.sort': 'Sort: {label}',
    'workspace.media.library.sort.newest': 'Newest',
    'workspace.media.library.sort.name': 'Name',
    'workspace.media.library.sort.largest': 'Largest',
    'workspace.media.library.result.count': '{count} file(s)',
    'workspace.media.library.select.all': 'Select all',
    'workspace.media.library.select.clear': 'Clear selection',
    'workspace.media.library.select.named': 'Select {name}',
    'workspace.media.library.select.count': '{count} selected',
    'workspace.media.library.select.delete': 'Delete selected',
    // Toplu silmede sessizce atlamak en kötüsüdür: hangi dosyanın neden
    // durduğu yazmazsa sahip onu silinmiş sanır.
    'workspace.media.library.select.kept':
        '{count} in use were kept. Open each one to see where it is used.',
    // Karantinadaki ya da henüz işlenmemiş dosyanın herkese açık adresi
    // yoktur; bu bir gecikme değil, bir güvenlik kararıdır.
    'workspace.media.library.access.private': 'Not publicly available yet',

    /*
        BOYUT MOTORU (kaynak ekranı "Boyut motoru", somut tablo `docs/108`
        §6.1). Kural bugün `config/media-slots.php` içinde bir SAYI
        LİSTESİYDİ; `320` bir sayıdır, `small · menü kartı · telefon` bir
        karardır. Kullanım metinleri kural ADINDAN türetilir, sunucudan
        gelmez: sunucu ölçüyü bilir, o ölçünün hangi ekranı beslediğini
        ürün bilir.
    */
    'workspace.media.engine.tab': 'Sizes',
    'workspace.media.engine.region': 'Size engine',
    'workspace.media.engine.lead':
        'Which sizes are produced from every image you upload? The rule is written once.',
    'workspace.media.engine.loading': 'Loading size rules…',
    'workspace.media.engine.failed': 'Size rules could not be loaded.',
    'workspace.media.engine.rule.width': '{width} px',
    // Yalnız sabit çerçeveli kural (paylaşım önizlemesi) iki ölçü taşır.
    'workspace.media.engine.rule.frame': '{width} × {height} px',
    'workspace.media.engine.fit.crop': 'Cropped',
    'workspace.media.engine.fit.contain': 'Fitted',
    'workspace.media.engine.rule.thumb.usage': 'List row',
    'workspace.media.engine.rule.small.usage': 'Menu card, phone',
    'workspace.media.engine.rule.medium.usage': 'Product detail',
    'workspace.media.engine.rule.large.usage': 'Full-screen image',
    'workspace.media.engine.rule.social.usage': 'Share preview',
    'workspace.media.engine.rule.print.usage': 'QR card, poster',
    'workspace.media.engine.rule.usage.unknown': 'No place assigned yet',
    /*
        DÜRÜSTLÜK. Kuralın adı olması, o ölçünün üretildiği anlamına
        gelmez: boru hattı bugün slot başına genişlik listesinden üretiyor.
        "print · 2480 px" yazıp o dosyanın hiç var olmadığını söylememek,
        sahibi olmayan bir yeteneğe güvendirirdi.
    */
    'workspace.media.engine.rule.notProduced': 'Not produced yet',
    'workspace.media.engine.rule.producedBy': 'Produced in {count} place(s)',
    'workspace.media.engine.regen.heading': 'I changed the rule — what about the old files?',
    'workspace.media.engine.regen.lead':
        'A new rule applies to new uploads only. To refresh older files a job is started; originals are kept and each file gains a new version.',
    'workspace.media.engine.regen.affected': 'Files that would be touched',
    'workspace.media.engine.regen.renditions': 'Sizes that exist today',
    'workspace.media.engine.regen.batch': 'Files per run',
    'workspace.media.engine.regen.start': 'Start regeneration',
    'workspace.media.engine.regen.running': 'Regenerating…',
    'workspace.media.engine.regen.done':
        '{count} file(s) regenerated as a new version. The originals are untouched.',
    'workspace.media.engine.regen.someFailed':
        '{count} file(s) could not be regenerated. Open the queue to see why.',
    // Senkron çalışıyor: tek çağrı hepsini bitirmeyebilir ve sahip bunu
    // sonradan değil, o anda öğrenmeli.
    'workspace.media.engine.regen.remaining': '{count} file(s) left. Press again to continue.',
    'workspace.media.engine.regen.failed': 'That did not work. Nothing was changed.',
    'workspace.media.engine.regen.nothing':
        'No processed file yet, so there is nothing to refresh.',
    /*
        ÖLÇÜLEN KAZANÇ — kaynak "AVIF ~%74 küçük" gibi rakamlar gösteriyor;
        onlar biçimlerin genel iddiasıdır, BU kiracının dosyalarının ölçümü
        değil. Ölçüm yoksa bölüm hiç çizilmez.
    */
    'workspace.media.engine.measured.heading': 'Measured saving',
    'workspace.media.engine.measured.lead':
        'Measured on your own {count} file(s): the original against the largest size actually served.',
    'workspace.media.engine.measured.originals': 'Originals',
    'workspace.media.engine.measured.served': 'Largest size served',
    'workspace.media.engine.measured.delta': '{percent}% smaller',

    /*
        KUYRUK (kaynak ekranı "Kuyruk", gerekçe `docs/108` §3 madde 5).
        "Takıldı mı, yoksa hâlâ çalışıyor mu?" — cevabı olmayan bu soru,
        sahibi aynı fotoğrafı tekrar tekrar yüklemeye ve kotasını kendi
        eliyle doldurmaya itiyordu.
    */
    'workspace.media.queue.region': 'Processing queue',
    'workspace.media.queue.lead':
        'Uploads and regeneration create jobs. This is where you see whether one is still running or stopped.',
    'workspace.media.queue.loading': 'Loading the queue…',
    'workspace.media.queue.failed': 'The queue could not be loaded.',
    'workspace.media.queue.empty': 'No jobs yet.',
    'workspace.media.queue.count.running': 'Running',
    'workspace.media.queue.count.succeeded': 'Done',
    'workspace.media.queue.count.failed': 'Failed',
    // `held` `failed`ten AYRI: dosyada sorun yok, tarayıcı konuşamadı.
    // İkisini aynı sayaçta toplamak "dosyalarım bozuk" dedirtirdi.
    'workspace.media.queue.count.held': 'On hold',
    'workspace.media.queue.kind.rendition': 'Generating sizes',
    'workspace.media.queue.kind.scan': 'Security scan',
    'workspace.media.queue.kind.unknown': 'Processing',
    'workspace.media.queue.state.pending': 'Waiting',
    'workspace.media.queue.state.running': 'Running',
    'workspace.media.queue.state.succeeded': 'Done',
    'workspace.media.queue.state.failed': 'Failed',
    'workspace.media.queue.state.held': 'On hold',
    'workspace.media.queue.state.unknown': 'Unknown',
    // Yüzde sütunu tabloda YOK; olmayan sayı uydurulmaz.
    'workspace.media.queue.progress.unknown': 'Still running — no progress figure is recorded.',
    'workspace.media.queue.attempts': 'attempt {count}',
    'workspace.media.queue.retry': 'Try again',
    'workspace.media.queue.retry.named': 'Try {name} again',
    'workspace.media.queue.retried': 'Started again. This row updates with the result.',
    'workspace.media.queue.retryFailed': 'Could not start it again. Nothing was changed.',
    'workspace.media.queue.refresh': 'Refresh the queue',

    /*
        DÖNÜŞTÜR (kaynak ekranı "Dönüştür", hedef listesi `docs/108` §6.3).
        Kaynağın kendi cümlesi: "Eski biçimleri modern biçime çevir. Aslı
        korunur, dönüşen dosya yeni sürüm olur."
    */
    'workspace.media.convert.tab': 'Convert',
    'workspace.media.convert.region': 'Convert formats',
    'workspace.media.convert.lead':
        'Turn old formats into modern ones. The original is kept; the converted file becomes a new version.',
    'workspace.media.convert.loading': 'Loading conversion targets…',
    'workspace.media.convert.failed': 'Conversion targets could not be loaded.',
    'workspace.media.convert.target.heading': 'Target format',
    // Her hedefin İŞİ: hangisini seçeceğine biçim adı değil, bu cümle
    // karar verdirir.
    'workspace.media.convert.target.avif.note': 'Images · smallest',
    'workspace.media.convert.target.webp.note': 'Images · widest support',
    'workspace.media.convert.target.webm.note': 'Video · VP9 / AV1',
    'workspace.media.convert.target.jpeg.note': 'A fallback that opens everywhere',
    /*
        "about" bilerek duruyor. Kaynaktaki yüzde biçimin GENEL iddiasıdır,
        bu kiracının ölçümü değil; kesin bir sayı gibi yazmak, sonradan
        tutmayacak bir rakama güvendirirdi.
    */
    'workspace.media.convert.target.claim': 'about {percent}% smaller',
    /*
        DÜRÜSTLÜK CÜMLELERİ. Kaynağın listesi TAM, ürünün yeteneği değil.
        Kartı gizlemek kaynağı sessizce kısaltmak, "yapılabilir" göstermek
        ise sahibi olmayan bir yeteneğe güvendirmek olurdu.
    */
    'workspace.media.convert.limitation.noVideoPipeline':
        'Not possible here: there is no video conversion pipeline.',
    'workspace.media.convert.limitation.encoderMissing':
        'Not possible here: this server cannot encode that format.',
    'workspace.media.convert.limitation.unknown': 'Not possible on this installation.',
    // Ölçülen kazanç, iddia edilenden AYRI bir cümledir ve yalnız gerçekten
    // tartılmış bayt varken yazılır.
    'workspace.media.convert.measured': 'Measured on your own {count} file(s): {percent}% smaller.',
    'workspace.media.convert.sources.heading': 'Files to convert',
    'workspace.media.convert.sources.empty': 'No file can be converted yet.',
    'workspace.media.convert.selectAll': 'Select all',
    'workspace.media.convert.clearAll': 'Clear',
    // Satırın ikinci cümlesi dönüşümün YÖNÜdür: "jpeg → AVIF".
    'workspace.media.convert.row.direction': '{from} → {to}',
    'workspace.media.convert.summary.selected': 'Selected files',
    'workspace.media.convert.summary.now': 'Selected size now',
    'workspace.media.convert.summary.batch': 'Files per run',
    'workspace.media.convert.start': 'Convert {count} file(s) to {format}',
    'workspace.media.convert.start.empty': 'Choose a file',
    'workspace.media.convert.running': 'Converting…',
    'workspace.media.convert.done':
        '{count} file(s) converted as a new version. The original is kept.',
    'workspace.media.convert.someFailed':
        '{count} file(s) could not be converted; their current version stayed valid.',
    'workspace.media.convert.remaining': '{count} file(s) left. Press again to continue.',
    'workspace.media.convert.runFailed': 'That did not work. Nothing was changed.',

    /*
        YER ve ÇÖP — `docs/108` §6.4 (kaynak ekranı "Kota ve çöp").

        Kota şeridi tek bir toplam söylüyordu ve sahip onu okuduğunda ne
        yapacağını bilmiyordu. Kategori adları SLOT ADI DEĞİLDİR: sahip
        `itemImage` değil "ürün fotoğrafları" okur. Eşlemenin gerekçesi
        sunucuda, `App\Domain\Media\StorageCategory` içindedir.
    */
    'workspace.media.storage.tab': 'Storage',
    'workspace.media.storage.region': 'Storage and trash',
    'workspace.media.storage.plan': 'Plan: {plan}',
    'workspace.media.storage.card.storage': 'Storage',
    'workspace.media.storage.card.assets': 'Files',
    'workspace.media.storage.card.ratio': '{used} of {limit}',
    'workspace.media.storage.card.free': '{percent}% used · {free} free',
    // Sınıra yaklaşınca not uyarı rengine döner: yükleme sessizce kesilmez.
    'workspace.media.storage.card.near': 'Close to the limit — only {free} left',
    'workspace.media.storage.breakdown.heading': 'What is filling it up?',
    'workspace.media.storage.breakdown.empty': 'Nothing is stored yet.',
    'workspace.media.storage.category.products': 'Product photos',
    'workspace.media.storage.category.promotion': 'Covers and promotion',
    'workspace.media.storage.category.brand': 'Logo and brand',
    'workspace.media.storage.category.documents': 'Documents and scans',
    'workspace.media.storage.category.other': 'Other files',
    'workspace.media.storage.share': '{bytes} · {percent}% of what is stored',
    'workspace.media.storage.trash': 'Trash',
    /*
        Çöp uyarı renginde ve AYRI, çünkü sahibin bugün geri kazanabileceği
        tek dilim odur. Silmek yer AÇMAZ — bu cümle bir şikâyeti önler:
        "sildim, hâlâ dolu diyor".
    */
    'workspace.media.storage.trash.note':
        'Deleting does not free space. The {bytes} in the trash still counts against your plan; it is released when the retention period ends.',

    /*
        MEDYA AYARLARI — `docs/108` §6.5-§6.6 (kaynak ekranı "Ayarlar").

        SALT OKUNUR ve bu bir eksiklik değil, ekranın sözüdür: bir ayar
        ekranındaki her kontrol, çevrildiğinde bir şeyin değişeceğini
        söyler. Bu depoda desen değiştirilemez, güvenlik önlemi
        kapatılamaz — o yüzden burada kaydetme kutusu yoktur.
    */
    'workspace.media.settings.tab': 'Settings',
    'workspace.media.settings.region': 'Media settings',
    'workspace.media.settings.patterns.heading': 'Folders, names and dates',
    'workspace.media.settings.patterns.lead':
        'These follow fixed rules today. Nothing here is a choice, so there is nothing to save.',
    'workspace.media.settings.pattern.directory': 'Folder structure',
    'workspace.media.settings.pattern.directory.workspaceFolder': 'One folder per workspace',
    'workspace.media.settings.pattern.directory.why':
        'Every file lives under its own workspace folder. The storage address is never rewritten — a published menu would lose its images the day it changed.',
    'workspace.media.settings.pattern.fileName': 'File name',
    'workspace.media.settings.pattern.fileName.opaqueKey':
        'A random key, kept apart from the name you see',
    'workspace.media.settings.pattern.fileName.why':
        'The library shows the name you typed; the stored file keeps a random key, so renaming a photo never moves a byte.',
    'workspace.media.settings.pattern.date': 'Date format',
    'workspace.media.settings.pattern.date.deviceLocale': 'The format of the device reading it',
    'workspace.media.settings.pattern.date.why':
        'Dates follow the language and time zone of the device you read them on. One fixed format is a separate decision and has not been made yet.',
    'workspace.media.settings.security.heading': 'Security and privacy',
    /*
        Sahibin kararı (2026-09-05): kapatılabilir bir güvenlik anahtarı,
        kapatıldığı gün bir güvenlik açığıdır. Anahtar GÖRÜNÜR — durumu
        okunsun diye — ama çevrilemez.
    */
    'workspace.media.settings.security.locked': 'Cannot be switched off',
    'workspace.media.settings.security.virusScan': 'Virus scan',
    'workspace.media.settings.security.virusScan.on':
        'Every file is scanned before it enters the library. Anything that does not come back clean is quarantined and nobody can download it.',
    // "Kapalı" bir KULLANICI kararıdır; bu bir ORTAM gerçeğidir.
    'workspace.media.settings.security.virusScan.unavailable':
        'No scanner is connected in this environment, so files are not being scanned. You did not switch this off, and it is not something you can switch on from here.',
    'workspace.media.settings.security.contentSignature': 'Content signature check',
    'workspace.media.settings.security.contentSignature.on':
        'The extension is never trusted. A file named “photo.jpg” that is really a script is rejected before anything is stored.',
    'workspace.media.settings.security.metadataStrip': 'Strip embedded data',
    // YARIM ve öyle söyleniyor: türev temiz, asıl dosya olduğu gibi durur.
    'workspace.media.settings.security.metadataStrip.partial':
        'Location, device and serial number are gone from the sizes your guests see, because those are re-encoded. The original file is kept exactly as you uploaded it.',
    'workspace.media.settings.security.signedLink': 'Signed link for private files',
    'workspace.media.settings.security.signedLink.on':
        'The original has no public address. Downloading it needs a signed link that stops working after ten minutes.',
    'workspace.media.settings.security.watermark': 'Watermark',
    // Bağlı olmayan anahtar ÇİZİLMEZ; olmayan şey "henüz yok" diye yazılır.
    'workspace.media.settings.security.watermark.missing': 'Not built yet.',

    /*
        GÖRÜNTÜLE (kaynak ekranı "Görüntüle", sıra `docs/108` §3 madde 8).

        Sahip bir belgenin içinde ne yazdığını öğrenmek için onu indirmek
        zorundaydı: dosya telefonun indirilenler klasörüne düşüyor, başka
        bir uygulamada açılıyor, panele dönüldüğünde hangi dosyaya
        bakıldığı unutuluyordu.

        Metinlerin tamamı İKİ dürüstlük kuralına uyar: sayfa sayısı
        bilinmiyorsa söylenmez, ve açılamayan dosya SEBEBİYLE birlikte
        söylenip bir sonraki adım verilir.
    */
    'workspace.media.viewer.tab': 'View',
    'workspace.media.viewer.region': 'File viewer',
    'workspace.media.viewer.lead':
        'Open a file inside the panel, instead of downloading it first to find out what is in it.',
    'workspace.media.viewer.empty': 'There is no file to open yet.',
    'workspace.media.viewer.files': 'Files',
    'workspace.media.viewer.none': 'Choose a file to open it here.',
    'workspace.media.viewer.loading': 'Opening the file…',
    'workspace.media.viewer.failed': 'This file could not be opened. Nothing was changed.',
    'workspace.media.viewer.close': 'Close the file',
    // Tarama bitmeden açmamak bir eksiklik değil, taramanın kendisidir.
    'workspace.media.viewer.blocked.scan':
        'This file has not cleared the security scan yet, so the panel does not open it. It opens here by itself once the scan comes back clean.',
    'workspace.media.viewer.blocked.type':
        'The panel does not open {type} files. Download it and open it on your device.',
    'workspace.media.viewer.download': 'Download the original',
    'workspace.media.viewer.download.failed':
        'The download link could not be created. Nothing was changed.',
    'workspace.media.viewer.pdf.frame': 'PDF reader — {name}',
    'workspace.media.viewer.pdf.page': 'Page {page} / {total}',
    'workspace.media.viewer.pdf.previous': 'Previous page',
    'workspace.media.viewer.pdf.next': 'Next page',
    // Sayfa sayısı okunamayan PDF: gezinti çizilmez, sebebi yazılır.
    'workspace.media.viewer.pdf.pagesUnknown':
        'The page count could not be read from this file, so the panel draws no page controls. The reader below has its own toolbar.',
    // Gömülü PDF bir SÖZ değildir; tarayıcı açmazsa ne yapılacağı yazılı.
    'workspace.media.viewer.pdf.embedNote':
        'Some browsers do not open a PDF inside a panel. If the frame stays empty, download the file and open it on your device.',
    'workspace.media.viewer.fact.type': 'Type',
    'workspace.media.viewer.fact.size': 'Size',
    'workspace.media.viewer.fact.pages': 'Pages',

    /*
        ASIL HER ZAMAN SAKLANIR — sahibin kararı (2026-09-05, açıkça soruldu).

        Kaynak burada bir "Aslını sakla" ANAHTARI gösteriyor. Yapılmadı ve
        sebebi bir tercih değil bir DEĞİŞMEZ: bu depoda dönüştürme ve
        yeniden üretim yeni SÜRÜM açar, hiçbir satır silinmez. Anahtarı
        kapatılabilir yapmak o değişmezi kırardı ve yanlış bir dönüştürmeden
        sonra aslı geri getirmenin hiçbir yolu kalmazdı.

        Bu yüzden ekranda bir anahtar değil, bir CÜMLE var: kullanıcı neyin
        garanti olduğunu okur ve kapatacak bir şey aramaz.
    */
    'workspace.media.settings.originals.heading': 'Your original files',
    'workspace.media.settings.originals.body':
        'The file you uploaded is always kept, byte for byte. Converting or re-rendering writes a new version next to it; nothing replaces the original and nothing is deleted.',

    /*
        TOPLU İŞLEM — kanonik kaynak `docs/reference/panel-v3/
        MedyaModulu.dc.html`, "Toplu işlem" (plan `docs/109-PANEL-V3.md` §2).

        Beş adım: Kapsam → Eylem → Ayar → Etki → Sonuç. Metinlerin tamamı
        ÜÇ dürüstlük kuralına uyar:

          1. Kuru çalışmanın sayıları GERÇEKTİR ve metin bunu söyler:
             "hiçbir dosyaya dokunulmadı, sunucu her dosyayı tek tek
             kontrol etti".
          2. Atlanan dosya SEBEBİYLE söylenir. "20 dosya atlandı" tek
             başına, hangi dosyayı arayacağını bilmeyen bir sahip bırakır.
          3. Yıkıcı işlemde metin geri dönüşü OLMADIĞINI söyler ve
             kullanıcıdan kelimeyi YAZMASINI ister — onay kutusu değil.
    */
    'workspace.media.bulk.tab': 'Bulk actions',
    'workspace.media.bulk.region': 'Bulk actions',
    'workspace.media.bulk.lead':
        'Apply one action to many files at once — see exactly what will happen before anything is touched.',
    'workspace.media.bulk.step.scope': 'Scope',
    'workspace.media.bulk.step.action': 'Action',
    'workspace.media.bulk.step.config': 'Settings',
    'workspace.media.bulk.step.preview': 'Impact',
    'workspace.media.bulk.step.run': 'Result',
    'workspace.media.bulk.back': 'Back',
    'workspace.media.bulk.loading': 'Working out what this would do…',
    'workspace.media.bulk.failed': 'The impact could not be worked out. Nothing was changed.',

    'workspace.media.bulk.scope.heading': 'Which files?',
    'workspace.media.bulk.scope.selected': 'The ones I pick',
    'workspace.media.bulk.scope.selected.description':
        'Tick the files below. Only what you tick goes into this job.',
    'workspace.media.bulk.scope.workspace': 'Everything in the library',
    'workspace.media.bulk.scope.workspace.description':
        'Every file in this workspace, not just the ones on this page.',
    'workspace.media.bulk.scope.folder': 'This folder and everything under it',
    'workspace.media.bulk.scope.folder.description':
        'The folder selected on the left, plus its sub-folders.',
    'workspace.media.bulk.scope.folder.none': 'Files that are not in any folder.',
    'workspace.media.bulk.scope.files': '{count} files',
    // Kaynağın DEĞİŞMEZ cümlesi — birebir korunur (`docs/109` §3).
    'workspace.media.bulk.scope.snapshot':
        'The list is frozen the moment the job starts. If someone uploads a new file while you work, that file is not part of this job — otherwise the job might never finish.',
    'workspace.media.bulk.scope.summary': 'Scope summary',
    'workspace.media.bulk.scope.summary.count': 'Files',
    'workspace.media.bulk.scope.summary.size': 'Total size',
    'workspace.media.bulk.scope.summary.batch': 'Handled per run',
    'workspace.media.bulk.scope.empty': 'There is no file in this scope yet.',
    'workspace.media.bulk.scope.next': 'Choose an action',

    'workspace.media.bulk.group.improve': 'Improve the file',
    'workspace.media.bulk.group.organize': 'Organise and move',
    'workspace.media.bulk.group.remove': 'Remove',
    'workspace.media.bulk.action.optimize': 'Re-render derivatives',
    'workspace.media.bulk.action.optimize.description':
        'Rebuilds every size from the original using today’s rules. The original is untouched and each file gets a new version.',
    'workspace.media.bulk.action.convert': 'Convert format',
    'workspace.media.bulk.action.convert.description':
        'Produces AVIF, WebP or JPEG. The converted file becomes a new version; the original is kept.',
    'workspace.media.bulk.action.move': 'Move to folder',
    'workspace.media.bulk.action.move.description':
        'Moves files to another folder. Addresses do not change and no new version is created.',
    'workspace.media.bulk.action.trash': 'Move to trash',
    'workspace.media.bulk.action.trash.description':
        'Waits {days} days in the trash, then is deleted for good. Can be restored at any point in that window.',
    'workspace.media.bulk.action.purge': 'Delete permanently',
    'workspace.media.bulk.action.purge.description':
        'Files already in the trash are erased along with every version. Only a nightly backup could bring them back.',
    'workspace.media.bulk.action.reversible': 'Can be undone',
    'workspace.media.bulk.action.irreversible': 'Cannot be undone',
    /*
        Kilitli kart GİZLENMEZ. Gizleseydik editör "ürün bunu yapamıyor"
        sanır ve yöneticisinden hiç istemezdi.
    */
    'workspace.media.bulk.action.locked':
        'Needs the “{permission}” permission — ask a workspace manager.',
    'workspace.media.bulk.action.unlocked': 'You have the permission for this',
    'workspace.media.bulk.action.next': 'Set up “{action}”',
    'workspace.media.bulk.action.none': 'Choose an action',

    'workspace.media.bulk.config.heading': 'Settings for “{action}”',
    'workspace.media.bulk.config.format': 'Target format',
    'workspace.media.bulk.config.format.help':
        'A file that is already in this format is skipped instead of re-encoded.',
    'workspace.media.bulk.config.folder': 'Destination folder',
    'workspace.media.bulk.config.folder.root': 'No folder',
    'workspace.media.bulk.config.folder.help': 'A file already in the destination is skipped.',
    // Ayarı olmayan eylemler için: boş bir kart çizmek, sahibi
    // "bir şeyi mi atladım?" diye geriye baktırırdı.
    'workspace.media.bulk.config.none':
        'This action has no settings. Go on to see what it would do.',
    'workspace.media.bulk.config.next': 'Show the impact',

    'workspace.media.bulk.preview.heading': 'Dry run result',
    'workspace.media.bulk.preview.lead':
        'No file was touched. The server checked every file one by one; the numbers below are real.',
    'workspace.media.bulk.preview.apply': 'Will be applied',
    'workspace.media.bulk.preview.skip': 'Will be skipped',
    'workspace.media.bulk.preview.remaining': 'Left for the next run',
    'workspace.media.bulk.preview.remaining.note':
        'One run handles {limit} files. Press the button again afterwards and it carries on where it stopped.',

    'workspace.media.bulk.skip.heading': 'Why are these skipped?',
    'workspace.media.bulk.skip.quarantine':
        'Not through the security scan yet — nothing is applied to these files.',
    'workspace.media.bulk.skip.legalHold':
        'Under a legal hold. Deleting and moving are locked until the hold is lifted.',
    'workspace.media.bulk.skip.publishedUsage':
        'In use on a published menu. Deleting one would leave a blank space where a guest looks.',
    'workspace.media.bulk.skip.unsupportedFormat':
        'This action does not apply to this file type. PDF and SVG files are not re-rendered.',
    'workspace.media.bulk.skip.alreadyDone': 'Already in that state — nothing to do.',
    'workspace.media.bulk.skip.notInTrash':
        'Not in the trash. Permanent deletion only works on files that are already in the trash.',
    // Sunucu bir gün yeni bir sebep eklerse ekran boş bir satır değil,
    // dürüst bir cümle gösterir.
    'workspace.media.bulk.skip.unknown':
        'The server gave a reason this version does not recognise yet. Nothing was applied to these files.',
    'workspace.media.bulk.skip.files': 'Files that will be skipped',
    'workspace.media.bulk.skip.show': 'List the skipped files',
    'workspace.media.bulk.skip.hide': 'Hide the skipped files',

    'workspace.media.bulk.impact.heading': 'What will the result be?',
    'workspace.media.bulk.impact.newVersion': 'A new version opens on',
    'workspace.media.bulk.impact.newVersion.value': '{count} files',
    'workspace.media.bulk.impact.originals': 'Original files',
    'workspace.media.bulk.impact.originals.kept': 'Unchanged',
    'workspace.media.bulk.impact.undo': 'Undo window',
    'workspace.media.bulk.impact.undo.days': '{days} days',
    'workspace.media.bulk.impact.undo.none': 'None',
    'workspace.media.bulk.impact.storage': 'Storage in use',
    'workspace.media.bulk.impact.storage.freed': '{used} now, {freed} freed',
    /*
        Kaynak burada "Sonra (tahmini)" yazıyor. O satır BİLEREK yok:
        yeniden üretimin ne kadar yer tutacağı ancak kodlamadan SONRA
        bilinir ve bir tahmin yazmak, bu ekranın tek sözünü ("sayılar
        gerçek") bozardı. Aynı karar `MediaConvertRegion` içinde de verildi.
    */
    'workspace.media.bulk.impact.storage.unknown':
        '{used} now. What the new versions will weigh is only known after they are produced, so no estimate is shown here.',

    'workspace.media.bulk.confirm.heading.purge': 'This cannot be undone',
    'workspace.media.bulk.confirm.heading.large': 'Large scope',
    'workspace.media.bulk.confirm.body.purge':
        '{count} files and every version of them will be erased. They do not go to the trash; only a nightly backup could bring them back.',
    'workspace.media.bulk.confirm.body.large':
        '{count} files will change. A job that touches more than a hundred files asks you to confirm in writing.',
    'workspace.media.bulk.confirm.label': 'Type {word} to confirm',
    'workspace.media.bulk.confirm.mismatch':
        'The word does not match yet, so the job stays closed.',

    'workspace.media.bulk.run.start': 'Start · {count} files',
    'workspace.media.bulk.run.start.destructive': 'Delete · {count} files',
    'workspace.media.bulk.run.start.empty': 'No file to apply this to',
    'workspace.media.bulk.run.running': 'Running…',
    'workspace.media.bulk.run.failed': 'The job could not be started. Nothing was changed.',

    'workspace.media.bulk.result.heading': 'File-by-file result',
    'workspace.media.bulk.result.summary': '{applied} applied, {skipped} skipped, {failed} failed.',
    // Aynı işlem anahtarı iki kez çalışmaz: kaynağın kendi kuralı.
    'workspace.media.bulk.result.replayed':
        'This job had already run with the same key, so it was not run a second time.',
    'workspace.media.bulk.result.filter.all': 'All',
    'workspace.media.bulk.result.filter.ok': 'Applied',
    'workspace.media.bulk.result.filter.skip': 'Skipped',
    'workspace.media.bulk.result.filter.error': 'Failed',
    'workspace.media.bulk.result.retry': 'Retry the {count} failed files only',
    'workspace.media.bulk.result.csv': 'Download CSV',
    'workspace.media.bulk.result.csv.header.id': 'id',
    'workspace.media.bulk.result.csv.header.name': 'file',
    'workspace.media.bulk.result.csv.header.status': 'status',
    'workspace.media.bulk.result.csv.header.reason': 'reason',
    'workspace.media.bulk.result.restart': 'New bulk action',
    'workspace.media.bulk.failure.reprocess': 'Re-rendering failed; the file is unchanged.',
    'workspace.media.bulk.failure.convert': 'Conversion failed; the file is unchanged.',
    'workspace.media.bulk.failure.move': 'The move did not go through; the file stayed put.',
    'workspace.media.bulk.failure.unknown':
        'This file could not be processed and was left as it was.',
    'workspace.media.bulk.audit.heading': 'Audit record',
    'workspace.media.bulk.audit.key': 'Operation key',
    'workspace.media.bulk.audit.note':
        'This record cannot be deleted or edited, and the same operation key never runs twice.',

    /*
        YÖNETİŞİM — kanonik kaynak `docs/reference/panel-v3/
        MedyaModulu.dc.html`, "Yönetişim". Kaynağın cümlesi: "Kim ne
        yapabilir, dosyalar ne kadar saklanır, kim ne yaptı."

        Rol adları UYDURULMAZ: matris kaynağın dört kademesini değil, bu
        deponun gerçek izinlerini gösterir. Kilitli satır gizlenmez —
        gizleseydik editör yapamadığını da öğrenemezdi.
    */
    'workspace.media.governance.tab': 'Governance',
    'workspace.media.governance.region': 'Media governance',
    'workspace.media.governance.lead':
        'Who can do what, how long files are kept, and who did what.',
    'workspace.media.governance.loading': 'Loading the governance record…',
    'workspace.media.governance.failed': 'The governance record could not be read.',
    'workspace.media.governance.role': 'You are looking at this as {role}.',
    'workspace.media.governance.role.owner': 'the owner',
    'workspace.media.governance.role.manager': 'a manager',
    'workspace.media.governance.role.editor': 'an editor',
    'workspace.media.governance.role.member': 'a member',
    'workspace.media.governance.role.unknown': 'a member of this workspace',

    'workspace.media.governance.matrix.heading': 'Bulk action permissions',
    'workspace.media.governance.matrix.allowed': 'Open to you',
    'workspace.media.governance.matrix.locked': 'Locked — needs “{permission}”',
    'workspace.media.governance.action.legal-hold': 'Place or lift a legal hold',
    'workspace.media.governance.action.legal-hold.description':
        'A file tied to a dispute cannot be deleted or moved. Bulk actions skip it and single deletion refuses it.',

    'workspace.media.governance.retention.heading': 'Retention policy',
    'workspace.media.governance.retention.trash': 'Trash',
    'workspace.media.governance.retention.trash.value': '{days} days',
    'workspace.media.governance.retention.trash.description':
        'Then it is deleted for good. Restoring is one tap inside that window.',
    'workspace.media.governance.retention.originals': 'Originals and versions',
    'workspace.media.governance.retention.originals.value': 'Kept',
    'workspace.media.governance.retention.originals.description':
        'The uploaded file is kept byte for byte and no version is ever pruned; re-rendering adds a version next to it.',
    'workspace.media.governance.retention.legalHold': 'Legal hold',
    'workspace.media.governance.retention.legalHold.value': '{count} files',
    'workspace.media.governance.retention.legalHold.description':
        'Files tied to a dispute cannot be deleted — bulk actions skip them.',
    'workspace.media.governance.retention.audit': 'Audit record',
    'workspace.media.governance.retention.audit.value': 'Forever',
    'workspace.media.governance.retention.audit.description':
        'Cannot be deleted or edited. It is only ever read.',

    'workspace.media.governance.legalHold.heading': 'Files under a legal hold',
    'workspace.media.governance.legalHold.empty': 'No file is under a legal hold right now.',
    'workspace.media.governance.legalHold.since': 'Held since {date}',

    'workspace.media.governance.trail.heading': 'Audit trail',
    'workspace.media.governance.trail.empty': 'Nothing has been recorded here yet.',
    'workspace.media.governance.trail.note':
        'Read-only. Records cannot be deleted or edited; they are only exported.',
    'workspace.media.governance.trail.bulk':
        '{action} · {applied} applied, {skipped} skipped, {failed} failed',
    'workspace.media.governance.trail.asset': '{action} · file #{id}',
    'workspace.media.governance.trail.unknownActor': 'Unknown person',

    /*
        OLGUNLUK (`docs/109-PANEL-V3.md` §2, kaynak ekranı "Olgunluk";
        seviye sözlüğü `docs/108` §6.7).

        BU BÖLÜMÜN METNİ KENDİNİ ÖVEMEZ. Seviye rozetleri elle yazılmış
        sayılar değil, kanıttan hesaplanmış sonuçlardır; katalogdaki
        cümleler de bunu saklamaz, açıkça söyler.
    */
    'workspace.media.maturity.tab': 'Maturity',
    'workspace.media.maturity.region': 'Media maturity',
    'workspace.media.maturity.score.heading': 'MVP maturity',
    'workspace.media.maturity.score.value': '{achieved} / {possible}',
    'workspace.media.maturity.score.note':
        'The score is the sum of the levels below. It is not a percentage of readiness.',
    /*
        Bu cümle EKRANIN KENDİSİ HAKKINDADIR ve mühendislik sözcüğü
        taşımaz (`workspace-vocabulary.guard`): sahibin öğrenmesi gereken
        şey "hangi teknik parça" değil, "bu sayı nereden geliyor".
    */
    'workspace.media.maturity.selfAssessed':
        'This is the product marking its own homework, not an outside audit. Every level below has to be earned by something that really exists here — a part that runs, a written rule, or a test that checks it — and a step with nothing behind it never counts.',
    'workspace.media.maturity.legend.heading': 'What the levels mean',

    'workspace.media.maturity.level.0': 'Not there',
    'workspace.media.maturity.level.0.description': 'Not built yet.',
    'workspace.media.maturity.level.1': 'Works',
    'workspace.media.maturity.level.1.description': 'The happy path works.',
    'workspace.media.maturity.level.2': 'Safe',
    'workspace.media.maturity.level.2.description': 'Failures and limits are covered by a test.',
    'workspace.media.maturity.level.3': 'Measured',
    'workspace.media.maturity.level.3.description': 'It produces a number or an audit record.',
    'workspace.media.maturity.level.4': 'Mature',
    'workspace.media.maturity.level.4.description':
        'It recovers on its own and says what happened.',

    /*
        Rozet GÖRÜLEN bir kısaltma değil, OKUNAN bir cümledir. "L2" tek
        başına ekran okuyucuda hiçbir şey ifade etmez.
    */
    'workspace.media.maturity.level.badge': 'Level {level} of {max}',
    'workspace.media.maturity.level.short': 'L{level}',
    'workspace.media.maturity.rung': 'Level {level} · {name}',

    'workspace.media.maturity.evidence.heading': 'Evidence',
    'workspace.media.maturity.evidence.none':
        'No evidence for this step, so it does not count towards the level.',
    'workspace.media.maturity.evidence.found': 'found',
    'workspace.media.maturity.evidence.absent': 'not found',
    'workspace.media.maturity.evidence.unverifiable': 'could not be checked here',
    'workspace.media.maturity.evidence.kind.endpoint': 'A part of the panel that runs',
    'workspace.media.maturity.evidence.kind.requirement': 'A written rule with a test',
    'workspace.media.maturity.evidence.kind.test': 'A test that checks it',
    'workspace.media.maturity.evidence.item': '{kind} · {state}',

    /*
        Yetenek adları SUNUCUNUN anahtarı değildir. Uç `intake` gönderir;
        restoran sahibi "Uploading" okur. İç adlandırmayı ekrana sızdırmak,
        sahibi kendi ürününde yabancı yapar.
    */
    'workspace.media.maturity.capability.intake': 'Uploading',
    'workspace.media.maturity.capability.scan': 'Virus scan',
    'workspace.media.maturity.capability.derivatives': 'Sizes and derivatives',
    'workspace.media.maturity.capability.convert': 'Format conversion',
    'workspace.media.maturity.capability.viewer': 'Viewer',
    'workspace.media.maturity.capability.trash': 'Trash and restore',
    'workspace.media.maturity.capability.bulk': 'Bulk actions',
    'workspace.media.maturity.capability.quota': 'Quota',
    'workspace.media.maturity.capability.governance': 'Governance',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof media, string> {}
}
