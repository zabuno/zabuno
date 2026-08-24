export const analytics = {
    'workspace.analytics.heading': 'Analytics',
    'workspace.analytics.range.label': 'Range',
    'workspace.analytics.range.today': 'Today',
    'workspace.analytics.range.7d': 'Last 7 days',
    'workspace.analytics.range.30d': 'Last 30 days',
    'workspace.analytics.report.region': 'Analytics report',
    'workspace.analytics.report.loading': 'Loading analytics…',
    'workspace.analytics.report.error': 'Analytics failed to load. Please try again.',
    'workspace.analytics.report.unavailable':
        'Analytics reporting is not available yet; no metrics have been generated.',
    'workspace.analytics.metric.qrResolve': 'QR Resolve',
    'workspace.analytics.metric.menuOpen': 'Confirmed Menu Open',
    'workspace.analytics.operational.description':
        'Review real QR resolve and confirmed menu open counts for the selected location and range.',
    'workspace.analytics.status.notConnected': 'Not requested yet',
    'workspace.analytics.status.loading': 'Loading',
    'workspace.analytics.status.error': 'Failed to load',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof analytics, string> {}
}
