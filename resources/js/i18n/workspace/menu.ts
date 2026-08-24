export const menu = {
    'workspace.menu.loading': 'Loading your menu…',
    'workspace.menu.heading': 'Menu',
    'workspace.menu.operational.description':
        'Build and edit the categories and items on your live menu catalog for the selected location.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof menu, string> {}
}
