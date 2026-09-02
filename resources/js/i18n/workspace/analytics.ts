export const analytics = {
    'workspace.analytics.heading': 'Analytics',
    // MENÜ MÜHENDİSLİĞİ (`docs/84`, P1-08). "Menün 214 kez açıldı" menüyü
    // DEĞİŞTİRMEK için hiçbir şey söylemez.
    'workspace.analytics.menuEngineering.region': 'What works on your menu',
    'workspace.analytics.menuEngineering.title': 'What works on your menu',
    'workspace.analytics.menuEngineering.loading': 'Loading menu figures…',
    'workspace.analytics.menuEngineering.error': 'Menu figures could not be loaded.',
    // Eşik ve gözlenen sayı AÇIKÇA yazılır: kaç ziyaretçi gerektiğini
    // bilmeyen biri, ne kadar bekleyeceğini de bilemez (`docs/66`).
    'workspace.analytics.menuEngineering.thin':
        'Not enough visitors yet to rank your dishes: {observed} of {threshold}. Keep the menu published and check back.',
    'workspace.analytics.menuEngineering.mostViewed': 'Most looked at',
    'workspace.analytics.menuEngineering.neverViewed': 'Never looked at',
    'workspace.analytics.menuEngineering.neverViewed.none': 'Every dish was looked at.',
    'workspace.analytics.menuEngineering.viewers': '{count} visitors',
    'workspace.analytics.menuEngineering.searches': 'Searched for, not found',
    'workspace.analytics.menuEngineering.searches.none':
        'No one searched for something your menu does not have.',
    'workspace.analytics.menuEngineering.searchCount': '{count} visitors',
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
    'workspace.analytics.action.refresh': 'Refresh',
    'workspace.analytics.action.retry': 'Retry',
    // PLAN cevabı, hata DEĞİL. Ayrı tutulmasının sebebi somut: "tekrar
    // deneyin" demek ve bir Retry düğmesi koymak, hiçbir zaman işe
    // yaramayacak bir yol göstermektir (`docs/44` engellenmiş durum
    // standardı: her blocked state nedenini VE çözümünü söyler).
    'workspace.analytics.status.planRestricted': 'Not in your plan',
    'workspace.analytics.report.planRestricted':
        'Analytics reporting is not included in your current plan. Your QR scans are still being recorded, so no data is lost — the reports appear as soon as the plan includes them.',
    'workspace.analytics.action.viewPlan': 'View your plan',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof analytics, string> {}
}
