export const brandLocations = {
    'workspace.brand.heading': 'Create your brand',
    'workspace.brand.intro':
        'Your customers will see this name. You can add locations and menus in the next steps, and change any of this later.',
    'workspace.brand.name': 'Brand name',
    'workspace.brand.name.help': 'You can change this later.',
    'workspace.brand.market': 'Main market',
    'workspace.brand.market.help':
        'Where you do business. We use it to set your time zone and currency.',
    'workspace.brand.market.placeholder': 'Select a country',
    'workspace.brand.regional': 'Regional settings',
    'workspace.brand.timezone': 'Time zone',
    'workspace.brand.timezone.help': 'Used for opening hours and reports.',
    'workspace.brand.currency': 'Currency',
    'workspace.brand.currency.help': 'Used for every price on your menu.',
    'workspace.brand.locale': 'Locale',
    'workspace.brand.submit': 'Create brand',
    'workspace.brand.submitting': 'Creating brand…',
    'workspace.brand.error.required': 'Name, timezone, and currency are required.',
    'workspace.brand.error.name.required': 'Enter a name for your brand.',
    'workspace.brand.error.market.required': 'Select the country where you do business.',
    'workspace.brand.error.network': 'We could not reach the server. Nothing was lost — try again.',
    'workspace.brand.error.submit': 'We could not create your brand. Please try again.',
    'workspace.location.heading': 'Create your location',
    'workspace.location.displayName': 'Display name',
    'workspace.location.countryCode': 'Country',
    'workspace.location.city': 'City',
    'workspace.location.addressLine1': 'Address line 1',
    'workspace.location.addressLine2': 'Address line 2',
    'workspace.location.postalCode': 'Postal code',
    'workspace.location.submit': 'Create',
    'workspace.location.error.required':
        'Display name, country, city, and address line 1 are required.',
    // Hata ALANIN yanında durur. Formun tepesindeki tek bir "şunlar
    // zorunludur" cümlesi, kullanıcıya hangisinin boş kaldığını aramayı
    // bırakır — üstelik odak hiç hareket etmiyordu (`docs/47` Kural 5).
    'workspace.location.displayName.error.required': 'Enter a display name.',
    'workspace.location.countryCode.error.required': 'Select the country.',
    'workspace.location.city.error.required': 'Enter the city.',
    'workspace.location.addressLine1.error.required': 'Enter the street address.',
    'workspace.location.optional': '{label} (optional)',
    'workspace.location.error.submit': 'We could not create your location. Please try again.',
    'workspace.brand.loading': 'Loading your brand…',
    'workspace.locations.heading': 'Locations',
    'workspace.locations.add.button': 'Add location',
    'workspace.brandLocations.heading': 'Brand & Locations',
    'workspace.brand.operational.description':
        'Edit the name, locale, timezone, currency, and contact details customers and your team see for this brand.',
    'workspace.locations.empty': 'No locations yet',
    'workspace.locations.empty.description':
        'A location holds the address, opening hours and time zone that your menu is served under.',
    // Kaynağın kendi cümlesi (`panel.dc.html`, "Şubeler"): ekranın ne
    // olduğunu değil, sahibin bilmesi gerekeni söyler — masalar ve karekodlar
    // şubeye aittir, menü ortaktır.
    'workspace.locations.operational.description':
        'Every location has its own tables and QR codes; the menu is shared.',
    'workspace.locations.region': 'Locations',
    // ŞUBE KARTI (`docs/109` §6.4).
    'workspace.locations.card.noAddress': 'No address yet',
    'workspace.locations.card.tables': '{count} tables',
    'workspace.locations.card.scansPerWeek': '{count} scans/week',
    // Yalnız KANITLANABİLİR yön. Masası olmayan şube taranamaz, yani kurulumu
    // bitmemiştir; masası olan bir şubenin açık olduğunu söyleyen hiçbir alan
    // yoktur ve o iddia ekranda yazmaz.
    'workspace.locations.card.status.setup': 'In setup',
    // ŞU AN AÇIK MI (FF-148). Gerekçe DÜŞTÜ: kapının açık olduğunu söyleyen
    // alan artık VAR (`location_opening_hours`) ve cevabı sunucu, şubenin
    // kendi saat diliminde veriyor. Rozet KELİME taşır — renk tek başına bir
    // işaret değildir (WCAG 2.2 §1.4.1). "now" bilerek yazılıdır: bu bir
    // tarife değil, o ANIN durumudur; tarife kartın saat satırında zaten var.
    // Saati girilmemiş şubede hiçbiri çizilmez.
    'workspace.locations.card.status.open': 'Open now',
    'workspace.locations.card.status.closed': 'Closed now',
    // ÇALIŞMA SAATLERİ (`docs/109` §6.4). Kaynağın kartındaki üçüncü ölçü.
    // Hafta TEK TİPSE aralık koşulsuz yazılır — her gün doğrudur. Hafta
    // değişiyorsa tek bir aralık yalan olurdu; o zaman kart BUGÜNÜ söyler
    // ve bunu açıkça belirtir. Saat girilmemişse hiçbiri çizilmez.
    'workspace.locations.card.hours.today': 'Today {range}',
    'workspace.locations.card.hours.closedToday': 'Closed today',
    'workspace.locations.card.hours.closedAllWeek': 'Closed all week',
    'workspace.locations.card.tables.label': 'Tables',
    'workspace.locations.card.tables.action': 'Tables at {name}',
    'workspace.locations.card.edit.label': 'Edit',
    // Adı bilerek "Edit {name}" DEĞİL: açılan panelin kendi düğmesi zaten o
    // adı taşıyor (`LocationEditForm`) ve iki düğme aynı adla görünseydi ekran
    // okuyucuyla gezen biri hangisine bastığını bilemezdi. Görünen etiket
    // yine "Edit" — erişilebilir ad onu içerir (WCAG 2.2 §2.5.3).
    'workspace.locations.card.edit.action': 'Edit details for {name}',
    'workspace.brandLocations.brand.slug': 'Slug',
    // "Locale" bir kullanıcı sözcüğü değildir ve tek başına belirsizdir:
    // panel dili mi, menü dili mi, tarih biçimi mi? Bu alan menünün ANA
    // İÇERİK dilidir; adı da onu söylemeli.
    'workspace.brandLocations.brand.locale': 'Menu language',
    'workspace.brandLocations.brand.timezone': 'Time zone',
    'workspace.brandLocations.brand.currency': 'Currency',
    'workspace.brandLocations.brand.description': 'Description',
    'workspace.brandLocations.brand.contactEmail': 'Contact email',
    'workspace.brandLocations.brand.contactPhone': 'Contact phone',
    'workspace.brandLocations.locations.heading': 'Locations',
    'workspace.brandLocations.locations.count': '{count} locations',
    'workspace.brandLocations.edit.button': 'Edit',
    'workspace.brandLocations.edit.save': 'Save',
    'workspace.brandLocations.edit.cancel': 'Cancel',
    'workspace.brandLocations.edit.error.submit':
        'We could not save your brand changes. Please try again.',
    'workspace.brandLocations.locations.edit.error.submit':
        'We could not save your location changes. Please try again.',
    'workspace.brandLocations.locations.edit.countryCode': 'Country code',
    // LOGO BAĞLAMA (`docs/98` FF-64): arka uç vardı, bağlayan ekran yoktu.
    'workspace.brand.logo.heading': 'Logo',
    'workspace.brand.logo.help':
        'Shown at the top of your guest menu next to the brand name. Upload the file on the Media page (slot: Logo), then pick it here.',
    'workspace.brand.logo.loading': 'Loading your logos…',
    'workspace.brand.logo.empty':
        'No processed logo yet. Upload one on the Media page (slot: Logo) first.',
    'workspace.brand.logo.choose': 'Choose a logo',
    'workspace.brand.logo.none': 'No logo',
    'workspace.brand.logo.save': 'Use this logo',
    'workspace.brand.logo.saving': 'Saving…',
    'workspace.brand.logo.saved': 'Logo saved. It goes live with your next publish.',
    'workspace.brand.logo.error': 'The logo could not be saved. Try again.',
    'workspace.brandLocations.brand.section.identity': 'Identity',
    'workspace.brandLocations.brand.section.regional': 'Regional settings',
    'workspace.brandLocations.brand.section.description': 'Description',
    'workspace.brandLocations.brand.section.contact': 'Contact',
    'workspace.brandLocations.locations.section.identity': 'Identity',
    'workspace.brandLocations.locations.section.address': 'Address',
    // ÇALIŞMA SAATİ GİRİŞİ (`docs/109` §6.4).
    //
    // Hafta gün gün girilir çünkü gerçek bir restoranın haftası tek aralık
    // değildir: pazartesi kapalıdır, cuma gece ikiye kadar açıktır. "Ertesi
    // gün" diye bir kutu YOKTUR — kapanış açılıştan erkense tek makul okuma
    // zaten ertesi gündür ve yardım metni bunu söyler.
    'workspace.brandLocations.locations.section.hours': 'Opening hours',
    'workspace.location.hours.enable': 'This location has opening hours',
    'workspace.location.hours.help':
        'Times use this location’s own time zone. A closing time earlier than the opening time means the next day — 18:00 to 02:00 closes at two in the morning.',
    'workspace.location.hours.empty':
        'No opening hours yet. The location card leaves that line out until you add them.',
    'workspace.location.hours.closed': 'Closed',
    'workspace.location.hours.opens': 'Opens',
    'workspace.location.hours.closes': 'Closes',
    // Kapanış ertesi güne taştığında satırın yanında durur: sahip
    // "02:00 yazdım ama bu hangi gün" diye sormak zorunda kalmaz.
    'workspace.location.hours.nextDay': 'next day',
    'workspace.location.hours.day.1': 'Monday',
    'workspace.location.hours.day.2': 'Tuesday',
    'workspace.location.hours.day.3': 'Wednesday',
    'workspace.location.hours.day.4': 'Thursday',
    'workspace.location.hours.day.5': 'Friday',
    'workspace.location.hours.day.6': 'Saturday',
    'workspace.location.hours.day.7': 'Sunday',
    'workspace.brandLocations.locations.view.country': 'Country',
    'workspace.brandLocations.locations.view.postalCode': 'Postal code',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof brandLocations, string> {}
}
