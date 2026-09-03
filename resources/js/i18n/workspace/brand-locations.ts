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
    'workspace.locations.operational.description':
        'Add and edit the physical locations that serve your menu — grouped by city, with an onboarding flow for new ones.',
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
    'workspace.brandLocations.locations.view.country': 'Country',
    'workspace.brandLocations.locations.view.postalCode': 'Postal code',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof brandLocations, string> {}
}
