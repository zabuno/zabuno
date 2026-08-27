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
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof menu, string> {}
}
