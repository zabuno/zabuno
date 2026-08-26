export const launchReadiness = {
    'workspace.launchReadiness.heading': 'Launch readiness',
    'workspace.launchReadiness.operational.description':
        'A checklist of the evidence still needed before Stage 1 can exit. Evidence appears here only after each item’s check is run independently — nothing below is inferred from source files.',
    'workspace.launchReadiness.checklist.region': 'Launch readiness checklist',
    'workspace.launchReadiness.checklist.explanation':
        'Evidence-backed items below resolve only from real, independently recorded checks; every other item stays unavailable until its own check is run and recorded.',
    'workspace.launchReadiness.item.status.unavailable': 'Unavailable',
    'workspace.launchReadiness.checklist.tenantIsolation.title': 'Tenant isolation evidence',
    'workspace.launchReadiness.checklist.tenantIsolation.description':
        'Proof that one restaurant workspace cannot read or write another’s data.',
    'workspace.launchReadiness.tenantIsolation.status.loading': 'Loading evidence…',
    'workspace.launchReadiness.tenantIsolation.status.passed': 'Passed',
    'workspace.launchReadiness.tenantIsolation.status.failed': 'Failed',
    'workspace.launchReadiness.tenantIsolation.status.error': 'Evidence check error',
    'workspace.launchReadiness.tenantIsolation.metadata.ranAt': 'Ran at',
    'workspace.launchReadiness.tenantIsolation.metadata.gitSha': 'Commit',
    'workspace.launchReadiness.tenantIsolation.metadata.durationMs': 'Duration',
    'workspace.launchReadiness.checklist.backupRestore.title': 'Backup & restore drill',
    'workspace.launchReadiness.checklist.backupRestore.description':
        'A completed drill proving production-shaped data can be recovered.',
    'workspace.launchReadiness.backupRestore.status.loading': 'Loading evidence…',
    'workspace.launchReadiness.backupRestore.status.passed': 'Passed',
    'workspace.launchReadiness.backupRestore.status.failed': 'Failed',
    'workspace.launchReadiness.backupRestore.status.error': 'Evidence check error',
    'workspace.launchReadiness.backupRestore.metadata.ranAt': 'Ran at',
    'workspace.launchReadiness.backupRestore.metadata.gitSha': 'Commit',
    'workspace.launchReadiness.backupRestore.metadata.durationMs': 'Duration',
    'workspace.launchReadiness.backupRestore.metadata.restoredRowCount': 'Restored rows',
    'workspace.launchReadiness.refresh.button': 'Refresh evidence',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof launchReadiness, string> {}
}
