export const dashboard = {
    'workspace.dashboard.heading': 'Dashboard',
    'workspace.dashboard.operational.description':
        'A live overview of your setup progress and current menu — see what is connected and jump straight to what still needs work.',
    'workspace.dashboard.loading': 'Loading your dashboard summary…',
    'workspace.dashboard.empty': 'No menu has been created for this location yet.',
    'workspace.dashboard.empty.openMenu': 'Open Menu',
    'workspace.dashboard.setup.region': 'Dashboard Setup',
    'workspace.dashboard.setup.heading': 'Setup',
    'workspace.dashboard.setup.brand': 'Brand',
    'workspace.dashboard.setup.location': 'Location',
    'workspace.dashboard.setup.menu': 'Menu',
    'workspace.dashboard.setup.publication': 'Publication',
    'workspace.dashboard.setup.qr': 'QR',
    'workspace.dashboard.setup.menu.empty': 'No menu yet',
    'workspace.dashboard.setup.notConnected': 'Not connected yet.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof dashboard, string> {}
}
