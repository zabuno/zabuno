export const publication = {
    'workspace.publication.heading': 'Publication',
    'workspace.publication.operational.description':
        'Review your draft menu, confirm it is ready, and publish it live — plus manage the QR code that links to it.',
    'workspace.publication.unavailable': 'Publication is not available yet.',
    'workspace.publication.status.region': 'Publication status',
    'workspace.publication.status.unavailable': 'Publication status is not available yet.',
    'workspace.publication.status.notPublished': 'Not published yet.',
    'workspace.publication.status.published': 'Published',
    'workspace.publication.status.draft': 'Draft',
    // Önceden '#{id} · v{version} · {state}' idi. İki kusur vardı ve YALNIZ
    // BİRİ kimlikti: `{id}` kullanıcı için anlamsız bir veritabanı anahtarı,
    // `{state}` ise çevrilmemiş ham bir sunucu değeri — kullanıcının dili ne
    // olursa olsun İngilizce "published" görüyordu.
    //
    // Ama durumun KENDİSİ anlamlı: "menüm yayında mı" sorusunun cevabı. Bu
    // yüzden alan atılmadı, çevrildi. Sürüm de kalıyor: her yayında arttığı
    // için kullanıcı için gerçekten okunabilir tek sayı odur.
    'workspace.publication.status.summary': 'Version {version} · {state}',
    'workspace.publication.status.publishError':
        'Publish failed. The last successful publication is still current.',
    'workspace.publication.status.publishButton': 'Publish',
    'workspace.publication.status.loadError':
        'Could not load the current publication status. Try again.',
    'workspace.publication.status.loading': 'Checking current publication status…',
    /*
        Yeniden deneme düğmesinin metni koda İngilizce GÖMÜLÜYDÜ ("Retry").
        Türkçe kullanan bir restoran sahibi, yayın durumu okunamadığı anda —
        yani panik anında — ekranındaki tek düğmeyi okuyamıyordu.
    */
    'workspace.publication.status.retry': 'Try again',
    'workspace.publication.status.lifecycle.heading': 'Lifecycle',
    'workspace.publication.status.lifecycle.pending': 'Pending',
    'workspace.publication.status.lifecycle.generating': 'Generating',
    'workspace.publication.status.lifecycle.published': 'Published',
    'workspace.publication.status.lifecycle.failed': 'Failed',
    'workspace.publication.status.lifecycle.superseded': 'Superseded',
    'workspace.publication.publishAction.region': 'What publishing does',
    'workspace.publication.publishAction.mode.label': 'Publish mode',
    'workspace.publication.publishAction.mode.immediate': 'Immediate publish',
    'workspace.publication.publishAction.checklistConfirmed': 'I reviewed the publish checklist',
    'workspace.publication.publishAction.permissionNotice':
        'You need permission to publish this menu.',
    'workspace.publication.publishAction.scheduleNotice':
        'Publishing at a chosen time is not available yet.',
    // `docs/101` A2: terim kalır, yanına tek cümlelik karşılığı gelir.
    'workspace.publication.publishAction.snapshotNotice':
        'Publish makes what you see here the menu your guests see. Anything you edit afterwards stays private until you publish again.',
    'workspace.publication.publishAction.failurePreservationNotice':
        'If publishing fails, guests keep seeing the menu you published last — nothing breaks at the table.',
    'workspace.publication.qrDestination.region': 'QR destination',
    'workspace.publication.qrDestination.explanation':
        'Create a QR code that resolves to the current published menu.',
    'workspace.publication.qrDestination.fields.unavailable':
        'Publish your menu first — the QR code needs a published menu to point to.',
    /*
        DİNAMİK KOD GÜVENCESİ (`docs/104` Döngü 11). Bu sektördeki en pahalı
        arıza, üçüncü taraf bir kısaltıcıya bağlı kodların bir gün ölmesidir;
        bu ürünün en güçlü argümanı buydu ve ekranda YAZMIYORDU.
    */
    'workspace.publication.qrDestination.permanence':
        'Printed codes keep working. You can point a code at a different menu or another location later, and the printed card stays valid — you never reprint because something changed here.',
    'workspace.publication.qrDestination.createButton': 'Create',
    /*
        Erişilebilir ad KODUN ADINI taşır (FF-110): 40 satırlık bir listede
        "diğer işlemler" başlıklı 40 düğme, ekran okuyucu kullanan biri için
        tek bir düğmeye eşdeğerdir.
    */
    'workspace.publication.qrDestination.rowActions': 'More actions for {name}',
    'workspace.publication.qrDestination.disable.confirmTitle': 'Disable {name}?',
    /*
        Onay metni SOMUT sonucu söyler. "Emin misiniz?" hiçbir şey öğretmez;
        sahibin bilmesi gereken şey, masadaki basılı kartın misafir için
        ölecek olmasıdır.
    */
    'workspace.publication.qrDestination.disable.confirmBody':
        'Guests who scan the printed card will no longer see your menu. The card itself keeps its address — you can re-enable this code later without reprinting anything.',
    'workspace.publication.qrDestination.disableButton': 'Disable',
    // Yanlış yayından dönmek (`docs/81`).
    'workspace.publication.history.title': 'Published versions',
    'workspace.publication.history.help':
        'Going back writes a new version. Nothing is deleted, and the printed QR codes are untouched.',
    'workspace.publication.history.version': 'Version {version}',
    'workspace.publication.history.live': 'Live',
    'workspace.publication.history.restore': 'Go back to this version',
    'workspace.publication.history.restoreLabel': 'Go back to version {version}',
    'workspace.publication.history.restoreError': 'This version could not be restored.',
    // Kapatmanın KARŞILIĞI (`docs/81`): geri açılamayan bir kod, masadaki
    // basılı kâğıdı kalıcı olarak öldürür.
    'workspace.publication.qrDestination.enableButton': 'Re-enable',
    'workspace.publication.qrDestination.enableError': 'The code could not be re-enabled.',
    'workspace.publication.qrDestination.empty': 'No QR code created yet.',
    'workspace.publication.qrDestination.loading': 'Loading QR codes…',
    'workspace.publication.qrDestination.loadError': 'Could not load QR codes. Try again.',
    'workspace.publication.qrDestination.retry': 'Try again',
    'workspace.publication.qrDestination.createError': 'Could not create QR code. Try again.',
    'workspace.publication.qrDestination.disableError': 'Could not disable QR code. Try again.',
    // KODU BAŞKA ŞUBEYE TAŞI (`docs/81` P1-03; ekranı `docs/98` FF-64).
    'workspace.publication.qrDestination.move.start': 'Move to another location',
    'workspace.publication.qrDestination.move.loading': 'Loading locations…',
    'workspace.publication.qrDestination.move.noOther':
        'This is your only location — there is nowhere else to move the code.',
    'workspace.publication.qrDestination.move.cancel': 'Cancel',
    'workspace.publication.qrDestination.move.label': 'Move this code to',
    'workspace.publication.qrDestination.move.choose': 'Choose a location…',
    'workspace.publication.qrDestination.move.button': 'Move',
    'workspace.publication.qrDestination.move.error':
        'The code could not be moved. The printed card still works as before.',
    'workspace.publication.qrDestination.state.disabled': 'Disabled',
    /*
        Kodun İNSAN ADI (FF-107). Ham çözümleyici adresi satırın başlığıydı;
        bir restoran sahibi 43 karakterlik bir dizeden hiçbir şey öğrenmez.
    */
    'workspace.publication.qrDestination.item.entrance': 'Entrance code',
    /*
        ÜÇ HÂL AYRI (FF-108). "Bilinmiyor", kodların yok olduğu anlamına
        gelmez: masadaki basılı kartlar çalışmaya devam ediyor olabilir.
    */
    'workspace.publication.qrDestination.fields.checking':
        'Checking whether your menu is published…',
    'workspace.publication.qrDestination.statusUnknown':
        'We could not reach the server to check your menu. Your printed codes keep working — reload to see the current state.',
    'workspace.publication.qrDestination.url.copy': 'Copy link',
    'workspace.publication.qrDestination.url.copied': 'Copied',
    'workspace.publication.qrExport.region': 'QR print export',
    'workspace.publication.qrExport.unavailable': 'QR print export is not available yet.',
    'workspace.publication.qrExport.noActive': 'No active QR code to export yet.',
    'workspace.publication.qrExport.selector': 'QR code',
    /* Teslimatın da bir hâli olmalı (FF-107). */
    'workspace.publication.qrExport.preview.failed':
        'The preview could not be produced. Your code still works; try another format or reload.',
    'workspace.publication.qrExport.previewAlt': 'QR code preview',
    'workspace.publication.qrExport.downloadPngLink': 'Download PNG',
    'workspace.publication.qrExport.formats.heading': 'Export formats',
    'workspace.publication.qrExport.formats.png': 'PNG',
    'workspace.publication.qrExport.formats.svg': 'SVG',
    'workspace.publication.qrExport.formats.pdf': 'PDF',
    'workspace.publication.qrExport.bulk': 'Bulk export',
    'workspace.publication.qrExport.themes.heading': 'Colour of the code itself',
    'workspace.publication.qrExport.themes.classic': 'Classic theme',
    'workspace.publication.qrExport.themes.minimal': 'Minimal theme',
    'workspace.publication.qrExport.themes.bold': 'Bold theme',
    'workspace.publication.qrExport.themes.rounded': 'Rounded theme',
    'workspace.publication.qrExport.themes.branded': 'Branded theme',
    'workspace.publication.qrExport.themes.highContrast': 'High contrast theme',
    'workspace.publication.qrExport.exportButton': 'Export',
    'workspace.publication.qrExport.downloadButton': 'Download',
    'workspace.publication.qrExport.printButton': 'Print',
    'workspace.publication.qrExport.config.heading': 'QR export configuration',
    'workspace.publication.qrExport.config.destinationType': 'Destination type',
    'workspace.publication.qrExport.config.destinationType.published': 'Published',
    'workspace.publication.qrExport.config.destinationType.menuCategory': 'Menu category',
    'workspace.publication.qrExport.config.outputFormat': 'Output format',
    'workspace.publication.qrExport.config.paperSize': 'Paper size',
    'workspace.publication.qrExport.config.orientation': 'Orientation',
    'workspace.publication.qrExport.config.orientation.portrait': 'Portrait',
    'workspace.publication.qrExport.config.orientation.landscape': 'Landscape',
    'workspace.publication.qrExport.config.bulk': 'Bulk range or count',
    'workspace.publication.qrExport.config.bulk.placeholder': 'e.g. 1-50 or 25',
    // `docs/101` Y5: tek soru görünür, varsayılanı olanlar 'ileri' altında.
    'workspace.publication.qrExport.bulkWizard.advanced': 'Advanced options',
    'workspace.publication.qrExport.bulkWizard.heading': 'Bulk QR wizard',
    'workspace.publication.qrExport.bulkWizard.areaSectionCount': 'Area/section count',
    'workspace.publication.qrExport.bulkWizard.tableCount': 'Table count',
    'workspace.publication.qrExport.bulkWizard.namingPrefix': 'Naming prefix',
    'workspace.publication.qrExport.bulkWizard.namingSequenceStart': 'Naming sequence start',
    'workspace.publication.qrExport.bulkWizard.namingRange': 'Naming range',
    'workspace.publication.qrExport.bulkWizard.seatCountPerTable': 'Seat count per table',
    'workspace.publication.qrExport.bulkWizard.notice':
        'Fill in the table layout below, then create the codes.',
    'workspace.publication.qrExport.bulkWizard.summary':
        '{tables} tables across {areas} areas, {seats} seats planned',
    'workspace.publication.qrExport.bulkWizard.areaSectionCount.error':
        'Enter a whole number between 1 and 50.',
    'workspace.publication.qrExport.bulkWizard.tableCount.error':
        'Enter a whole number between 1 and 500.',
    'workspace.publication.qrExport.bulkWizard.namingPrefix.error':
        'Naming prefix must be 10 characters or fewer.',
    'workspace.publication.qrExport.bulkWizard.namingSequenceStart.error':
        'Enter a whole number between 0 and 9999.',
    'workspace.publication.qrExport.bulkWizard.namingRange.error':
        'Enter a range as digits-dash-digits with the start no greater than the end.',
    'workspace.publication.qrExport.bulkWizard.seatCountPerTable.error':
        'Enter a whole number between 1 and 20.',
    'workspace.publication.qrExport.bulkWizard.createButton': 'Create table QR codes',
    'workspace.publication.qrExport.bulkWizard.loading': 'Creating table QR codes...',
    /*
        BASILABİLİR DESTE (`docs/104` Döngü 8). Milimetre EKRANDA YAZAR:
        "4 cm" sayısı, kâğıt boyu açılır listesinin yapamadığı işi yapar —
        sahip kartın masada nasıl duracağını gözünde canlandırır.
    */
    /*
        TEMA BİR ZEVK MESELESİ DEĞİL, TARANABİLİRLİK KISITIDIR (Döngü 10).
        Okunmayan bir karekod masadaki ölü kâğıttır ve bunu ilk fark eden
        kişi misafirdir.
    */
    /*
        YAZICIDAN NE ÇIKACAK (`docs/104` Döngü 9). "A4 dikey" bir restoran
        sahibine hiçbir şey anlatmaz; milimetre ve okuma mesafesi anlatır.
    */
    /*
        TEK KOD İKİNCİL BİR İŞTİR (FF-114). Restoran sahibi buraya "QR ayarı
        yapmaya" gelmez; kırk masası, bir mukavvası ve bir yazıcısı vardır.
    */
    /*
        HAM DOSYA (FF-120): kartı değil, kodun kendisini indirmek isteyenler
        için. Matbaa kendi tasarımını yapacaksa kodu çıplak ister.
    */
    'workspace.publication.qrExport.raw.heading': 'Download the bare code file (PNG, SVG, PDF)',
    'workspace.publication.qrExport.preview.paper': '{paper} — {width} × {height} mm',
    'workspace.publication.qrExport.preview.size':
        'The code prints {mm} mm wide, so it can be scanned from about {distance} cm away. Put a sheet this size on a wall or a window, not on a table.',
    /*
        MASADAKİ KART (FF-120, sahibin talebi). Eski "Themes" bloğu YANLIŞ ŞEYİ
        adlandırıyordu: altı düğme karekodun piksel renklerini değiştiriyor ve
        "tema" diyordu. Karekodun rengi bir tema değil bir KISITTIR; tema,
        masaya konacak kartın kendisidir ve markadan beslenir.
    */
    /*
        SALONUN BÖLÜMLERİ (FF-123). Toplu üretim onları "Area 1" diye açıyor ve
        bu bir yer tutucudur: hiçbir restoran sahibi salonunu böyle
        adlandırmaz. Kart basarken alanı seçen kişi kendi kullandığı adı
        görmeli.
    */
    'workspace.publication.diningAreas.heading': 'Areas in your dining room',
    'workspace.publication.diningAreas.help':
        'Bulk creation names them Area 1, Area 2. Rename them the way your team says them out loud — garden, upstairs, terrace. Renaming never breaks a printed card.',
    'workspace.publication.diningAreas.rename': 'Rename {name}',
    'workspace.publication.diningAreas.tableCount': '{count} tables',
    'workspace.publication.diningAreas.empty': 'An area needs a name. Nothing was changed.',
    'workspace.publication.diningAreas.save': 'Save',
    'workspace.publication.diningAreas.cancel': 'Cancel',
    'workspace.publication.diningAreas.renameError': 'The area could not be renamed. Try again.',
    'workspace.publication.qrCard.heading': 'Table card',
    'workspace.publication.qrCard.explanation':
        'The card you cut out and slide into the stand on the table. It carries your own name and colour; the code itself always prints black on white so it stays scannable.',
    'workspace.publication.qrCard.step.scope': 'What to print',
    'workspace.publication.qrCard.scope.single': 'This code',
    'workspace.publication.qrCard.scope.area': 'One area',
    'workspace.publication.qrCard.scope.areaLabel': 'Which area',
    'workspace.publication.qrCard.scope.all': 'All {count} codes',
    'workspace.publication.qrCard.step.design': 'Design',
    'workspace.publication.qrCard.step.size': 'Size',
    'workspace.publication.qrCard.step.export': 'Download',
    'workspace.publication.qrCard.theme.classic': 'Classic',
    'workspace.publication.qrCard.theme.minimal': 'Minimal',
    'workspace.publication.qrCard.theme.banner': 'Banner',
    'workspace.publication.qrCard.theme.frame': 'Framed',
    'workspace.publication.qrCard.headline.label': 'Sentence on the card',
    'workspace.publication.qrCard.headline.default': 'Scan for the menu',
    'workspace.publication.qrCard.size.paper': 'Paper size — for printing on a standard sheet',
    'workspace.publication.qrCard.size.ratio':
        'Card proportion — for a plexiglass stand, 150 mm along the long edge',
    'workspace.publication.qrCard.orientation.label': 'Orientation',
    'workspace.publication.qrCard.orientation.portrait': 'Portrait',
    'workspace.publication.qrCard.orientation.landscape': 'Landscape',
    'workspace.publication.qrCard.export.help':
        'Both files print at the exact size shown. PDF goes straight to a printer; SVG opens in a design tool if your printer wants one.',
    'workspace.publication.qrCard.export.pdf': 'Download card (PDF)',
    'workspace.publication.qrCard.export.svg': 'Download card (SVG)',
    /*
        PNG YOK ve söylenmezse kullanıcı onu arar, bulamayınca ürünü eksik
        sanır. Sebep gerçek: raster bir görsel 4 cm'lik bir karekodda modül
        kenarlarını bulanıklaştırır.
    */
    'workspace.publication.qrCard.export.noPng':
        'There is no PNG here on purpose: a raster image blurs the module edges of a 4 cm code. For print, use PDF or SVG.',
    /*
        TOPLU BASKI BİR ARŞİVDİR (FF-122): matbaa her kartı ayrı dosya olarak
        ister ve dosya adı hangi masa olduğunu söyler.
    */
    'workspace.publication.qrCard.export.zipPdf': 'Download {count} cards (ZIP of PDFs)',
    'workspace.publication.qrCard.export.zipSvg': 'ZIP of SVGs',
    'workspace.publication.qrCard.export.capped':
        'One archive holds up to {cap} cards. Download the rest by picking another area.',
    'workspace.publication.qrCard.preview.alt': 'Preview of the printed card',
    'workspace.publication.qrCard.preview.size': 'Prints at {width} × {height} mm',
    'workspace.publication.qrCard.back': 'Back',
    'workspace.publication.qrCard.next': 'Next',
    'workspace.publication.qrExport.themes.scannability':
        'Every theme here prints dark on light and keeps the quiet zone around the code, so all of them scan. Colour never comes at the cost of a code that cannot be read.',
    'workspace.publication.qrExport.themes.brandTooPale':
        'Your brand colour is too light to scan reliably, so this code prints in black instead.',
    'workspace.publication.qrExport.themes.brandMissing':
        'You have not set a brand colour yet, so this code prints in black.',
    'workspace.publication.qrExport.themes.editBrand': 'Set your brand colour',
    'workspace.publication.qrExport.sheet.heading': 'Print sheet for your tables',
    'workspace.publication.qrExport.sheet.explanation':
        '{codes} codes on {pages} A4 page(s), 12 cards per page. Each code prints at 4 cm — readable from about 40 cm, the distance of someone sitting at the table. Cut along the dashed lines.',
    'workspace.publication.qrExport.sheet.download': 'Download print sheet (PDF)',
    'workspace.publication.qrExport.sheet.downloadPart': 'Print sheet {part} of {total} (PDF)',
    'workspace.publication.qrExport.bulkWizard.needsPublication':
        'Publish your menu first — a QR code has to point at a live menu.',
    /*
        Plan kısıtı bir HATA DEĞİLDİR: tekrar denemek işe yaramaz, çıkış
        yolu plan yükseltmesidir (FF-108).
    */
    'workspace.publication.qrExport.bulkWizard.planRestricted':
        'Creating table codes in bulk is not included in your current plan.',
    'workspace.publication.qrExport.bulkWizard.planRestricted.action': 'See plans',
    'workspace.publication.qrExport.bulkWizard.createError':
        'Could not create table QR codes. Try again.',
    'workspace.publication.qrExport.bulkWizard.success':
        'Created {areas} areas, {tables} tables, {qrCodes} QR codes.',
    'workspace.publication.qrExport.bulkWizard.crossFieldError':
        'Naming range and sequence must match the table count and stay within 0-9999.',
    'workspace.publication.draftPreview.region': 'Draft menu preview',
    'workspace.publication.draftPreview.notice':
        'This is a draft preview — not public or published.',
    'workspace.publication.draftPreview.empty': 'No menu is loaded yet.',
    'workspace.publication.draftPreview.visible': 'Visible',
    'workspace.publication.draftPreview.hidden': 'Hidden',
    'workspace.publication.draftPreview.allergens': 'Allergens',
    'workspace.publication.readiness.region': 'Publish readiness checklist',
    'workspace.publication.readiness.notLoaded': 'Readiness is not loaded yet.',
    'workspace.publication.readiness.ready': 'Ready',
    'workspace.publication.readiness.needsAttention': 'Needs attention',
    'workspace.publication.readiness.hasCategory': 'Has category',
    'workspace.publication.readiness.hasVisibleItem': 'Has visible item',
    'workspace.publication.readiness.visibleProductNames': 'Visible product names ready',
    'workspace.publication.readiness.visiblePriceAndCurrency': 'Visible price and currency ready',
    'workspace.publication.readiness.categoryNames': 'Category names ready',
    'workspace.publication.publishedSnapshot.region': 'Published menu',
    'workspace.publication.publishedSnapshot.publishedAt': 'Published at {publishedAt}',

    /*
        QR EKRANI — panel v3 kanonik kaynağı (`docs/109` §6.7).

        Kaynağın ekranı iki sütundur: solda masa kartları ızgarası, sağda
        seçili kodun paneli. Buradaki anahtarlar o ekranın kendi dilidir;
        `qrExport.*` ve `qrCard.*` anahtarları gelişmiş baskı yüzeyinde
        kalır ve dokunulmaz.
    */
    'workspace.publication.qrScreen.description':
        'A printed code never changes — update your menu as often as you like.',
    'workspace.publication.qrScreen.downloadAll': 'Download every card (PDF)',
    'workspace.publication.qrScreen.downloadAllPart': 'Cards {part} of {total} (PDF)',
    'workspace.publication.qrScreen.tables': 'Table cards',
    'workspace.publication.qrScreen.scans': '{count} scans',
    'workspace.publication.qrScreen.neverScanned': 'Never scanned',
    'workspace.publication.qrScreen.selected': 'Selected code',
    'workspace.publication.qrScreen.state.working': 'Working',
    'workspace.publication.qrScreen.state.active': 'Active',
    'workspace.publication.qrScreen.state.disabled': 'Disabled',
    'workspace.publication.qrScreen.address': 'Full address',
    'workspace.publication.qrScreen.copy': 'Copy address',
    'workspace.publication.qrScreen.copied': 'Copied',
    'workspace.publication.qrScreen.theme': 'Card design',
    'workspace.publication.qrScreen.size': 'Size',
    'workspace.publication.qrScreen.sizeOption': '{name} · {width} × {height} mm',
    'workspace.publication.qrScreen.sizeTableCard': 'Table card',
    'workspace.publication.qrScreen.sizeStand': 'Stand',
    'workspace.publication.qrScreen.downloadPdf': 'Download PDF',
    'workspace.publication.qrScreen.print': 'Print',
    'workspace.publication.qrScreen.previewAlt': 'Printable card preview for {name}',
    /*
        ÖLÇÜLMÜŞ kontrast. Sayı hesaptan gelir (`lib/qrContrast`), elle
        yazılmış bir sabitten değil: kart her zaman siyah kod / beyaz kâğıt
        basar ve oran WCAG bağıl parlaklıkla tam 21,0:1 çıkar. Kaynağın
        "tarayıcı testi geçti" cümlesi YAZILMAZ — ürün hiçbir telefonda tarama
        testi çalıştırmıyor ve çalıştırmadığı bir testin geçtiğini yazmak,
        sahibin kırk kart bastırmasını sağlayan cümledir.
    */
    'workspace.publication.qrScreen.contrast':
        'Contrast measured: {ratio}:1 — dark modules on a light background.',
    'workspace.publication.qrScreen.noDarkTheme':
        'There is no dark card: scanners assume dark-on-light, and an inverted code is not read at all by many phones.',
    'workspace.publication.qrScreen.empty':
        'No table code yet. Create the codes for your tables below.',
    'workspace.publication.qrScreen.loading': 'Loading table codes…',
    'workspace.publication.qrScreen.loadError': 'Could not load table codes. Try again.',
    'workspace.publication.qrScreen.retry': 'Try again',
    'workspace.publication.qrScreen.bulk': 'Bulk codes for new tables',
    /*
        VARSAYILANLARIN CÜMLESİ (kaynağın kendi düzeni). Eski metin sahibe ne
        YAPACAĞINI söylüyordu; söylenmesi gereken şey ne OLACAĞIydı. Ad öneki
        ve başlangıç numarası sunucunun gerçek varsayılanıdır
        (`StoreBulkQrCodesController`), uydurulmuş bir örnek değil.
    */
    'workspace.publication.qrScreen.bulkDefaults':
        'The rest is default: {areas} area(s), {seats} seats per table, names start at T1.',
    'workspace.publication.qrScreen.advanced': 'Code management and advanced printing',

    /*
        ADIM ÇİZGİSİ — kanonik kaynak `docs/reference/panel-v3/panel.dc.html`,
        "Yayınlama" ekranı: Taslak → Önizleme → Yayında.

        Üç adım, sahibin kafasındaki sırayı ekrana koyar. Önceki hâlde ekran
        bölge bölge doğru bilgiyi taşıyordu ama NEREDE OLDUĞUNU söylemiyordu:
        sahip "yayınladım mı, yayınlamadım mı?" sorusunu üç ayrı bölgeyi
        okuyarak cevaplıyordu.
    */
    'workspace.publication.stepper.region': 'Where you are',
    'workspace.publication.stepper.draft': 'Draft',
    'workspace.publication.stepper.draft.changes': '{count} changes waiting',
    'workspace.publication.stepper.draft.noChanges': 'Nothing waiting',
    'workspace.publication.stepper.preview': 'Preview',
    'workspace.publication.stepper.preview.sub': 'Check it on a phone',
    'workspace.publication.stepper.live': 'Live',
    'workspace.publication.stepper.live.sub': 'v{version} · {when}',
    'workspace.publication.stepper.live.never': 'Nothing published yet',
    // Durum RENKLE DEĞİL, metinle de söylenir (WCAG 1.4.1): ekran okuyucu
    // kullanan biri için dolgulu daire ile boş daire aynı şeydir.
    'workspace.publication.stepper.state.current': 'You are here',
    'workspace.publication.stepper.state.done': 'Done',
    'workspace.publication.stepper.state.upcoming': 'Not yet',

    /*
        Göreli zaman. Kaynak "v14 · 2 gün önce" diyor ve bu doğru bir
        seçimdir: sahip için "2 gün önce" ile "2026-09-03T09:00:00Z" aynı
        şey değildir — birincisi bir his verir, ikincisi bir kayıt.
    */
    'workspace.publication.relative.justNow': 'just now',
    'workspace.publication.relative.minutesAgo': '{count} min ago',
    'workspace.publication.relative.hoursAgo': '{count} h ago',
    'workspace.publication.relative.daysAgo': '{count} days ago',

    /*
        YAYINLANACAK DEĞİŞİKLİKLER. Sahibin "şu an basarsam misafir ne
        görecek?" sorusunun cevabı. Fark İKİ GERÇEK VERİDEN üretilir:
        sunucudaki yayınlanmış snapshot ile paneldeki taslak ağaç.

        "Kim değiştirdi ve ne zaman" kaynakta var ama bu depoda menü satırı
        başına aktör/zaman kaydı YOK; o satır bilerek yazılmaz.
    */
    'workspace.publication.diff.region': 'Changes waiting to be published',
    'workspace.publication.diff.title': '{count} changes waiting to be published',
    'workspace.publication.diff.none': 'Nothing is waiting. Guests are seeing v{version}.',
    'workspace.publication.diff.noneFirst':
        'Nothing has been published yet. Publishing sends this menu to your guests for the first time.',
    'workspace.publication.diff.versions': 'v{live} → v{next}',
    'workspace.publication.diff.versionsFirst': 'First version — v{next}',
    'workspace.publication.diff.kind.added': 'Added',
    'workspace.publication.diff.kind.price': 'Price',
    'workspace.publication.diff.kind.renamed': 'Name',
    'workspace.publication.diff.kind.hidden': 'Hidden from guests',
    'workspace.publication.diff.kind.removed': 'Removed',
    'workspace.publication.diff.priceChange': '{name} · {from} → {to}',
    'workspace.publication.diff.nameChange': '{from} → {to}',
    'workspace.publication.diff.detail': '{kind} · {category}',

    /*
        DÜZELT (kaynağın "Düzelt" düğmesi). Eksik bir madde ile onu
        düzeltebileceğin ekran arasındaki mesafe SIFIR olmalı: aksi hâlde
        sahip "görünür ürünlerin fiyatı dolu değil" cümlesini okur ve
        menüde aramaya başlar.
    */
    'workspace.publication.readiness.fix': 'Fix',
    'workspace.publication.readiness.fixLabel': 'Fix: {label}',

    /*
        TELEFONDA ÖNİZLE — kısa ömürlü, imzalı adres (sahibin 2026-09-05
        kararı). Yayın adresiyle KARIŞTIRILAMAZ ve bunu ekran söyler.
    */
    'workspace.publication.preview.region': 'Preview on a phone',
    'workspace.publication.preview.button': 'Preview on a phone',
    'workspace.publication.preview.close': 'Close preview',
    'workspace.publication.preview.heading': 'This is what a guest will see',
    'workspace.publication.preview.linkButton': 'Open the preview link',
    'workspace.publication.preview.linkHelp':
        'The link works for 15 minutes and is closed to search engines. It is not your guests’ address — the printed QR code keeps pointing at the published menu.',
    'workspace.publication.preview.linkError': 'The preview link could not be created. Try again.',
    'workspace.publication.preview.linkPending': 'Creating the preview link…',

    /*
        PLANLA — zamanlanmış yayın. Saatler SUNUCUDAN gelir ve ŞUBENİN saat
        dilimindedir (`docs/62`); ekran yalnız okunabilir hâle çevirir.

        CÜMLELER ARTIK "İSTANBUL" DEMİYOR. Diyorlardı ve bu, aynı markanın
        Berlin şubesinde doğrudan yanlıştı: sahip Berlin saatiyle hesaplanmış
        bir anı "İstanbul saati" diye okurdu. Şehir adı yerine ŞUBE denir,
        çünkü ekrandaki saati belirleyen şey şubenin kendisidir.
    */
    'workspace.publication.schedule.region': 'Schedule the publish',
    'workspace.publication.schedule.button': 'Schedule',
    'workspace.publication.schedule.close': 'Close scheduling',
    'workspace.publication.schedule.help':
        "A scheduled publish is a publish: it takes the next version number and the printed QR code stays the same. Times are in this location's own time zone.",
    'workspace.publication.schedule.option.tonight': 'Tonight 03:00',
    'workspace.publication.schedule.option.tomorrowMorning': 'Tomorrow 09:00',
    'workspace.publication.schedule.option.nextMonday': 'Monday 09:00',
    'workspace.publication.schedule.optionAt': '{label} — {moment}',
    'workspace.publication.schedule.pending': "Scheduled for {moment}, this location's time.",

    /*
        ÇIKMAYAN YAYIN SESSİZ KALAMAZ. Üç cümle de aynı üç şeyi söyler ve
        hiçbiri söz vermez: ne oldu, menünün şu anki hâli ne, sahip ne
        yapabilir. "Birazdan yayınlanacak" ya da tahmini bir süre yazmıyoruz
        — zamanlayıcının ne zaman döneceğini bilmiyoruz, bilinmeyen de
        yazılmaz.
    */
    'workspace.publication.schedule.status.publishing': 'Publishing the {moment} schedule now.',
    'workspace.publication.schedule.status.overdue':
        '{moment} has passed and the publish did not happen. The menu did not change — guests still see the previous version. Publish now, or schedule it again.',
    'workspace.publication.schedule.status.interrupted':
        'The {moment} publish started and did not finish. The menu did not change — guests still see the previous version. Publish now, or schedule it again.',
    'workspace.publication.schedule.status.failed':
        'The {moment} publish was attempted and could not be saved. The menu did not change — guests still see the previous version. Publish now, or schedule it again.',
    'workspace.publication.schedule.status.unknown':
        'This screen cannot read the state of the {moment} schedule, so it cannot tell you whether the menu changed. Reload the page.',
    'workspace.publication.schedule.dismiss': 'Dismiss this notice',

    'workspace.publication.schedule.cancel': 'Cancel this schedule',
    'workspace.publication.schedule.cancelError': 'The schedule could not be cancelled. Try again.',
    'workspace.publication.schedule.error':
        'The publish could not be scheduled. Nothing was scheduled.',
    'workspace.publication.schedule.unready':
        'Finish the readiness list first — a schedule freezes what you see now.',

    // Üst rozet: "Yayında · v14". Kaynağın kendi cümlesi; sürüm numarası
    // kullanıcı için okunabilir tek sayıdır ve her yayında artar.
    'workspace.publication.status.liveBadge': 'Live · v{version}',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof publication, string> {}
}
