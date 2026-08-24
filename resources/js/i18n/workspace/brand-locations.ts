export const brandLocations = {
    'workspace.brand.heading': 'Create your brand',
    'workspace.brand.name': 'Name',
    'workspace.brand.timezone': 'Timezone',
    'workspace.brand.currency': 'Currency',
    'workspace.brand.locale': 'Locale',
    'workspace.brand.submit': 'Create',
    'workspace.brand.error.required': 'Name, timezone, and currency are required.',
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
    'workspace.location.error.submit': 'We could not create your location. Please try again.',
    'workspace.brand.loading': 'Loading your brand…',
    'workspace.locations.heading': 'Locations',
    'workspace.locations.add.button': 'Add location',
    'workspace.brandLocations.heading': 'Brand & Locations',
    'workspace.brand.operational.description':
        'Edit the name, locale, timezone, currency, and contact details customers and your team see for this brand.',
    'workspace.locations.empty': 'No locations yet. Add your first location to get started.',
    'workspace.locations.operational.description':
        'Add and edit the physical locations that serve your menu — grouped by city, with an onboarding flow for new ones.',
    'workspace.brandLocations.brand.slug': 'Slug',
    'workspace.brandLocations.brand.locale': 'Locale',
    'workspace.brandLocations.brand.timezone': 'Timezone',
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
