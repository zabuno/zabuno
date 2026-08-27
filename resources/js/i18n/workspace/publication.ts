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
    'workspace.publication.status.lifecycle.heading': 'Lifecycle',
    'workspace.publication.status.lifecycle.pending': 'Pending',
    'workspace.publication.status.lifecycle.generating': 'Generating',
    'workspace.publication.status.lifecycle.published': 'Published',
    'workspace.publication.status.lifecycle.failed': 'Failed',
    'workspace.publication.status.lifecycle.superseded': 'Superseded',
    'workspace.publication.publishAction.region': 'Publish action',
    'workspace.publication.publishAction.mode.label': 'Publish mode',
    'workspace.publication.publishAction.mode.immediate': 'Immediate publish',
    'workspace.publication.publishAction.checklistConfirmed': 'I reviewed the publish checklist',
    'workspace.publication.publishAction.permissionNotice':
        'You need permission to publish this menu.',
    'workspace.publication.publishAction.scheduleNotice':
        'Publishing at a chosen time is not available yet.',
    'workspace.publication.publishAction.snapshotNotice':
        'Publishing saves a fixed copy. Later edits stay private until you publish again.',
    'workspace.publication.publishAction.failurePreservationNotice':
        'If publishing fails, guests keep seeing the menu you published last.',
    'workspace.publication.qrDestination.region': 'QR destination',
    'workspace.publication.qrDestination.explanation':
        'Create a QR code that resolves to the current published menu.',
    'workspace.publication.qrDestination.fields.unavailable':
        'Publish your menu first — the QR code needs a published menu to point to.',
    'workspace.publication.qrDestination.createButton': 'Create',
    'workspace.publication.qrDestination.disableButton': 'Disable',
    'workspace.publication.qrDestination.empty': 'No QR code created yet.',
    'workspace.publication.qrDestination.loading': 'Loading QR codes…',
    'workspace.publication.qrDestination.loadError': 'Could not load QR codes. Try again.',
    'workspace.publication.qrDestination.createError': 'Could not create QR code. Try again.',
    'workspace.publication.qrDestination.disableError': 'Could not disable QR code. Try again.',
    'workspace.publication.qrDestination.state.disabled': 'Disabled',
    'workspace.publication.qrExport.region': 'QR print export',
    'workspace.publication.qrExport.unavailable': 'QR print export is not available yet.',
    'workspace.publication.qrExport.noActive': 'No active QR code to export yet.',
    'workspace.publication.qrExport.selector': 'QR code',
    'workspace.publication.qrExport.previewAlt': 'QR code preview',
    'workspace.publication.qrExport.downloadPngLink': 'Download PNG',
    'workspace.publication.qrExport.formats.heading': 'Export formats',
    'workspace.publication.qrExport.formats.png': 'PNG',
    'workspace.publication.qrExport.formats.svg': 'SVG',
    'workspace.publication.qrExport.formats.pdf': 'PDF',
    'workspace.publication.qrExport.bulk': 'Bulk export',
    'workspace.publication.qrExport.themes.heading': 'Themes',
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
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof publication, string> {}
}
