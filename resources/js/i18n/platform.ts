import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    // MÜHENDİSLİK KABUĞU (`docs/98` FF-66): readiness ve denetim izi plan/ödeme
    // kabuğundan ayrıldı — aynı kişi olabilir, aynı iş değil.
    'engineering.shell.brand': 'Zabuno Engineering',
    'engineering.shell.navLabel': 'Engineering',
    'engineering.shell.heading': 'Engineering',
    'engineering.shell.group.evidence': 'Evidence',
    'engineering.shell.toPlatform': 'Platform admin',
    'platform.shell.toEngineering': 'Engineering',
    'platform.shell.group.commercial': 'Commercial',
    'platform.shell.group.integrations': 'Integrations',
    'engineering.aiAudit.nav.label': 'AI audit trail',
    'engineering.aiAudit.heading': 'AI audit trail',
    'engineering.aiAudit.description':
        'Who changed which provider key and when, which account fell out of the pool, and which restaurant is pinned to which account. Read-only; nothing here carries a secret.',
    'engineering.aiAudit.loading': 'Loading the audit trail…',
    'engineering.aiAudit.error': 'The audit trail could not be loaded.',
    'engineering.aiAudit.assignments.title': 'Restaurant → account pinning',
    'engineering.aiAudit.assignments.empty':
        'No restaurant has made an AI call yet, so nothing is pinned.',
    'engineering.aiAudit.audits.title': 'Key and health events',
    'engineering.aiAudit.audits.empty': 'No key has been written yet.',
    'engineering.aiAudit.col.workspace': 'Restaurant',
    'engineering.aiAudit.col.provider': 'Provider',
    'engineering.aiAudit.col.connection': 'Account',
    'engineering.aiAudit.col.health': 'Health',
    'engineering.aiAudit.col.since': 'Pinned since',
    'engineering.aiAudit.col.at': 'When',
    'engineering.aiAudit.col.action': 'Event',
    'engineering.aiAudit.col.actor': 'By',
    'engineering.aiAudit.actor.server': 'server command',

    // MODÜL ENVANTERİ (`docs/111`). Metinler bilerek "hangi dosya söylüyor"
    // diye yazıldı: bu yüzeyin değeri listenin kendisi değil, listenin
    // NEREDEN geldiğinin okunabilir olmasıdır.
    'engineering.modules.nav.label': 'Modules',
    'engineering.modules.heading': 'Modules',
    'engineering.modules.description':
        'What this installation actually carries, read from the two files that are verified today: the core kernel registry and the observed dependency graph. Read-only — a module cannot be turned on or off anywhere in this product yet, so no switch is drawn here.',
    'engineering.modules.loading': 'Loading the module inventory…',
    'engineering.modules.error': 'The module inventory could not be loaded.',
    'engineering.modules.core.title': 'Core kernel registry',
    'engineering.modules.core.source': 'Source: config/core-modules.php',
    'engineering.modules.core.empty': 'The core kernel registry is empty.',
    'engineering.modules.core.scope':
        'Only the core kernel is listed. Module specs outside it carry no code, version or AI posture anywhere in the source, and a row invented for them would be a claim rather than a reading.',
    'engineering.modules.col.code': 'Code',
    'engineering.modules.col.name': 'Module',
    'engineering.modules.col.version': 'Version',
    'engineering.modules.col.class': 'Class',
    'engineering.modules.col.aiPosture': 'AI posture',
    'engineering.modules.col.baseline': 'Deterministic baseline',
    'engineering.modules.col.dependsOn': 'Depends on',
    'engineering.modules.graph.title': 'Observed dependencies between contexts',
    'engineering.modules.graph.source': 'Source: config/module-dependency-dag.json',
    'engineering.modules.graph.about':
        'These are context names from the source tree, not registry codes, and each edge names the file whose import proves it. Only edges the repository could verify are listed.',
    'engineering.modules.graph.empty': 'No cross-context import has been observed.',
    'engineering.modules.graph.col.from': 'Context',
    'engineering.modules.graph.col.to': 'Depends on',
    'engineering.modules.graph.col.evidence': 'Proved by',
    'platform.shell.brand': 'Zabuno Platform',
    'platform.shell.navLabel': 'Platform admin',
    'platform.shell.heading': 'Platform administration',
    'platform.shell.backToWorkspace': 'Back to workspace',

    'platform.plans.region.label': 'Plans',
    'platform.plans.loading': 'Loading plans…',
    'platform.plans.empty': 'No plans yet.',
    'platform.plans.error': 'We could not load plans.',
    'platform.plans.retry': 'Retry',
    'platform.plans.priceUnavailable': 'Price unavailable',
    'platform.plans.inactive': 'Inactive',

    'platform.plans.form.heading': 'Create plan',
    'platform.plans.form.name': 'Plan name',
    'platform.plans.form.code': 'Code',
    'platform.plans.form.version': 'Version',
    'platform.plans.form.amount': 'Amount (minor units)',
    'platform.plans.form.currency': 'Currency',
    'platform.plans.form.entitlements': 'Entitlements (one per line)',
    'platform.plans.form.sortOrder': 'Sort order',
    'platform.plans.form.submit': 'Create plan',
    'platform.plans.form.error': 'We could not create the plan. Please try again.',
    // Zorunlu/isteğe bağlı ayrımı ETİKETTE görünür. Öncesinde her alan
    // birbirinin aynıydı ve "Create plan" düğmesi hiçbir açıklama vermeden
    // devre dışı duruyordu: kullanıcı hangi alanın eksik olduğunu bilemezdi.
    'platform.plans.form.optional': '{label} (optional)',
    'platform.plans.form.name.error.required': 'Enter a plan name.',
    'platform.plans.form.code.error.required': 'Enter a plan code.',
    'platform.plans.form.version.error.required': 'Enter a whole number, for example 1.',
    'platform.plans.form.sortOrder.error.required': 'Enter a whole number, for example 10.',
    'platform.plans.form.amount.error.invalid': 'Enter the amount in minor units, digits only.',
    'platform.plans.form.currency.error.invalid': 'Use a three-letter code, for example EUR.',
    // Tutar ve para birimi BİRLİKTE anlamlıdır: biri olmadan diğeri fiyat
    // değil, yarım bir kayıttır.
    'platform.plans.form.pair.error':
        'Enter the amount and the currency together, or leave both empty.',
    'platform.subscriptions.form.endDate.error.required': 'Enter the end date.',
    'platform.subscriptions.form.plan.error.required': 'Choose a plan.',
    'platform.subscriptions.form.optional': '{label} (optional)',

    'platform.plans.activate.button': 'Activate',
    'platform.plans.activate.dialog.heading': 'Activate plan',
    'platform.plans.activate.dialog.cancel': 'Cancel',
    'platform.plans.activate.dialog.confirm': 'Confirm activation',
    'platform.plans.activate.success': 'Plan activated.',
    'platform.plans.activate.error': 'We could not activate this plan. Please try again.',

    'platform.subscriptions.nav.label': 'Subscriptions',

    'platform.subscriptions.workspace.label': 'Workspace',
    'platform.subscriptions.workspace.loading': 'Loading workspaces…',
    'platform.subscriptions.workspace.empty': 'No workspaces found.',
    'platform.subscriptions.workspace.error': 'We could not load workspaces.',
    'platform.subscriptions.workspace.retry': 'Retry',
    'platform.subscriptions.workspace.placeholder': 'Select a workspace',

    'platform.subscriptions.plans.blocked':
        'A plan must be created and activated before recording a manual payment.',
    'platform.subscriptions.plans.error': 'We could not load plans.',

    'platform.subscriptions.subscription.region.label': 'Subscription',
    'platform.subscriptions.subscription.loading': 'Loading subscription…',
    'platform.subscriptions.subscription.none': 'No active subscription',
    'platform.subscriptions.subscription.error': 'We could not load the subscription.',
    'platform.subscriptions.subscription.retry': 'Retry',
    'platform.subscriptions.subscription.active.label': 'Active',

    'platform.subscriptions.form.plan.label': 'Plan',
    'platform.subscriptions.form.endDate.label': 'End date',
    'platform.subscriptions.form.paymentNote.label': 'Payment note',
    'platform.subscriptions.form.documentReference.label': 'Document reference',
    'platform.subscriptions.form.submit': 'Record manual payment',
    'platform.subscriptions.form.error':
        'We could not record the manual payment. Please try again.',
    'platform.subscriptions.form.retry': 'Retry',

    'platform.subscriptions.confirm.heading': 'Confirm manual payment',
    'platform.subscriptions.confirm.cancel': 'Cancel',
    'platform.subscriptions.confirm.confirm': 'Confirm',

    'platform.subscriptions.success': 'Manual payment recorded successfully.',
    'platform.subscriptions.success.region.label': 'Manual payment status',

    // Yayına hazırlık KANITI — 2026-08-27'de restoran panelinden buraya
    // taşındı. Commit hash'i, test süresi, tenant izolasyonu ve yedek
    // tatbikatı restoran sahibinin işi değildir; geliştirme ekibinin
    // işidir (UX raporu §4.3, §9.10).
    'platform.releaseReadiness.nav.label': 'Release readiness',
    'platform.releaseReadiness.heading': 'Release readiness',
    'platform.releaseReadiness.operational.description':
        'A checklist of the evidence still needed before Stage 1 can exit. Evidence appears here only after each item’s check is run independently — nothing below is inferred from source files.',
    'platform.releaseReadiness.checklist.region': 'Release readiness checklist',
    'platform.releaseReadiness.checklist.explanation':
        'Evidence-backed items below resolve only from real, independently recorded checks; every other item stays unavailable until its own check is run and recorded.',
    'platform.releaseReadiness.item.status.unavailable': 'Unavailable',
    'platform.releaseReadiness.checklist.tenantIsolation.title': 'Tenant isolation evidence',
    'platform.releaseReadiness.checklist.tenantIsolation.description':
        'Proof that one restaurant workspace cannot read or write another’s data.',
    'platform.releaseReadiness.tenantIsolation.status.loading': 'Loading evidence…',
    'platform.releaseReadiness.tenantIsolation.status.passed': 'Passed',
    'platform.releaseReadiness.tenantIsolation.status.failed': 'Failed',
    'platform.releaseReadiness.tenantIsolation.status.error': 'Evidence check error',
    'platform.releaseReadiness.tenantIsolation.metadata.ranAt': 'Ran at',
    'platform.releaseReadiness.tenantIsolation.metadata.gitSha': 'Commit',
    'platform.releaseReadiness.tenantIsolation.metadata.durationMs': 'Duration',
    'platform.releaseReadiness.checklist.backupRestore.title': 'Backup & restore drill',
    'platform.releaseReadiness.checklist.backupRestore.description':
        'A completed drill proving production-shaped data can be recovered.',
    'platform.releaseReadiness.backupRestore.status.loading': 'Loading evidence…',
    'platform.releaseReadiness.backupRestore.status.passed': 'Passed',
    'platform.releaseReadiness.backupRestore.status.failed': 'Failed',
    'platform.releaseReadiness.backupRestore.status.error': 'Evidence check error',
    'platform.releaseReadiness.backupRestore.metadata.ranAt': 'Ran at',
    'platform.releaseReadiness.backupRestore.metadata.gitSha': 'Commit',
    'platform.releaseReadiness.backupRestore.metadata.durationMs': 'Duration',
    'platform.releaseReadiness.backupRestore.metadata.restoredRowCount': 'Restored rows',
    // FF-63 (`docs/98`): altı madde de gerçek kayıttan okunur. Makine kanıtı
    // ile insan tanıklığı AYRI etiketlenir — "Attested" ≠ "Passed".
    'platform.releaseReadiness.evidence.loading': 'Loading evidence…',
    'platform.releaseReadiness.evidence.error': 'Evidence check error',
    'platform.releaseReadiness.evidence.recordedAt': 'Recorded at',
    'platform.releaseReadiness.checklist.hostCapability.title': 'Shared-host capability evidence',
    'platform.releaseReadiness.checklist.hostCapability.description':
        'Evidence that the application runs within its hosting plan’s resource limits.',
    'platform.releaseReadiness.hostCapability.status.full': 'All capabilities present',
    'platform.releaseReadiness.hostCapability.status.degraded':
        'Running with {count} planned degradation(s)',
    'platform.releaseReadiness.checklist.qr-physical-scan.title': 'Physical QR scan evidence',
    'platform.releaseReadiness.checklist.qr-physical-scan.description':
        'A field test of a printed code scanned with a real device.',
    'platform.releaseReadiness.checklist.rpo-rto-decision.title': 'RPO & RTO decision',
    'platform.releaseReadiness.checklist.rpo-rto-decision.description':
        'A recorded decision for how much data loss and downtime this system can tolerate.',
    'platform.releaseReadiness.checklist.owasp-asvs-audit.title': 'OWASP ASVS audit',
    'platform.releaseReadiness.checklist.owasp-asvs-audit.description':
        'A security audit reference for this application — self-assessment or third-party, stated as which.',
    'platform.releaseReadiness.attest.help.qr-physical-scan':
        'Record the scan you actually did: which phone, which printed code, did the published menu open.',
    'platform.releaseReadiness.attest.help.rpo-rto-decision':
        'Record the decision in hours: how much data may be lost (RPO) and how long the system may be down (RTO).',
    'platform.releaseReadiness.attest.help.owasp-asvs-audit':
        'Point to the audit record. Say plainly whether it is a self-assessment or a third-party audit — never claim certification.',
    'platform.releaseReadiness.attest.field.status': 'Outcome',
    'platform.releaseReadiness.attest.field.device': 'Device',
    'platform.releaseReadiness.attest.field.rpoHours': 'RPO (hours of data loss tolerated)',
    'platform.releaseReadiness.attest.field.rtoHours': 'RTO (hours of downtime tolerated)',
    'platform.releaseReadiness.attest.field.summary': 'What was done, in your own words',
    'platform.releaseReadiness.attest.field.reference': 'Reference (link or repo path, optional)',
    'platform.releaseReadiness.attest.status.passed': 'It worked',
    'platform.releaseReadiness.attest.status.failed': 'It did not work',
    'platform.releaseReadiness.attest.submit': 'Record this',
    'platform.releaseReadiness.attest.error': 'The record could not be saved.',
    'platform.releaseReadiness.attest.badge.passed': 'Attested — worked',
    'platform.releaseReadiness.attest.badge.failed': 'Attested — failed',
    'platform.releaseReadiness.attest.badge.decided': 'Decided',
    'platform.releaseReadiness.attest.badge.recorded': 'Recorded',
    'platform.releaseReadiness.attest.by': 'Recorded by',
    'platform.releaseReadiness.attest.by.server': 'server command',
    'platform.releaseReadiness.attest.disclaimer':
        'This is a human attestation, not an automated check.',
    'platform.releaseReadiness.refresh.button': 'Refresh evidence',
    'platform.releaseReadiness.workspace.empty':
        'Choose a workspace above to load its release evidence.',

    'platform.credentials.region.label': 'Provider keys',
    'platform.credentials.nav.label': 'Provider keys',
    'platform.credentials.intro':
        'Store the platform provider keys here. A secret is shown only as a masked hint after it is saved — it is never sent back to this screen. Leave a secret field blank to keep the current value.',
    'platform.credentials.loading': 'Loading provider keys…',
    'platform.credentials.error': 'We could not load the provider keys.',
    'platform.credentials.retry': 'Retry',
    'platform.credentials.save': 'Save',
    'platform.credentials.saved': 'Saved.',
    'platform.credentials.saveError': 'We could not save. Please try again.',
    'platform.credentials.disable': 'Disable',
    'platform.credentials.secretSet': 'Set ({mask})',
    'platform.credentials.keepPlaceholder': 'Leave blank to keep current',

    'platform.credentials.provider.mailgun': 'Mailgun (email)',
    'platform.credentials.provider.iyzico': 'Iyzico (payments)',
    'platform.credentials.provider.openai': 'OpenAI (ChatGPT)',
    'platform.credentials.provider.gemini': 'Gemini (Google)',
    // Faz 3 (`docs/95`). "Custom endpoint" bir marka adı taşımaz BİLEREK:
    // Qwen, vLLM, Ollama ya da başka bir OpenAI-uyumlu sunucu aynı kutuya
    // girer; tek bir markanın adını yazmak, diğerlerini dışlıyormuş gibi
    // okunurdu.
    'platform.credentials.provider.anthropic': 'Anthropic (Claude)',
    'platform.credentials.provider.kimi': 'Kimi (Moonshot)',
    'platform.credentials.provider.custom_endpoint': 'Custom endpoint (OpenAI-compatible)',

    // ÇOK-BAĞLANTI PANELİ — `docs/95` Faz 3. Etiket zorunludur çünkü sır
    // görünmediği için aynı sağlayıcının iki kartını ayırt eden tek şey odur.
    'platform.connections.add': '+ Add a connection',
    'platform.connections.add.cancel': 'Cancel',
    'platform.connections.add.heading': 'New connection',
    'platform.connections.provider.label': 'Provider',
    'platform.connections.provider.choose': 'Choose a provider…',
    'platform.connections.label.label': 'Connection name',
    'platform.connections.label.help':
        'Your own name for this account, e.g. “OpenAI — bulk import”. It is the only way to tell two cards apart.',
    'platform.connections.scope.label': 'Who owns this key',
    'platform.connections.scope.platform': 'Platform account',
    'platform.connections.scope.byok': 'A customer’s own key (BYOK)',
    'platform.connections.scope.platform_owned': 'Platform account',
    'platform.connections.scope.tenant_byok': 'Customer’s own key',
    'platform.connections.workspace.label': 'Workspace ID',
    'platform.connections.workspace.help':
        'A customer key only ever serves that one workspace. It never appears as a candidate for anyone else.',
    'platform.connections.create': 'Save connection',
    'platform.connections.create.error': 'The connection could not be saved.',
    'platform.connections.enable': 'Enable',
    'platform.connections.empty': 'No connection yet.',
    // Sağlık: "bilinmiyor" ile "sağlıklı" AYRI durumlar. Hiç sınanmamış bir
    // bağlantıyı sağlıklı göstermek, ilk gerçek isteği bir tahmine
    // dayandırmak olurdu.
    // Yoklama: kaydettikten sonra "gerçekten çalışıyor mu" sorusunu tek ve
    // ücretsiz bir çağrıyla yanıtlar. "Yoklanacak bir şey yok" bir HATA
    // değildir — Mailgun'un model listesi yoktur ve bu onu bozuk yapmaz.
    'platform.connections.probe': 'Test this connection',
    'platform.connections.probe.reachable': 'Reached it — the key works.',
    'platform.connections.probe.rejected': 'The provider refused this key.',
    'platform.connections.probe.unsupported': 'Nothing to test for this provider.',
    'platform.connections.health.unknown': 'Not checked yet',
    'platform.connections.health.healthy': 'Healthy',
    'platform.connections.health.unhealthy': 'Not responding',

    'platform.credentials.state.active': 'Active',
    'platform.credentials.state.disabled': 'Disabled',
    'platform.credentials.state.unset': 'Not set',

    'platform.credentials.field.domain': 'Domain',
    'platform.credentials.field.secret': 'Secret / API key',
    'platform.credentials.field.endpoint': 'Endpoint',
    'platform.credentials.field.api_key': 'API key',
    'platform.credentials.field.secret_key': 'Secret key',
    'platform.credentials.field.base_url': 'Base URL',
    'platform.credentials.field.organization': 'Organization',
    'platform.credentials.field.project': 'Project',

    /*
        SÜPERADMİNİN İLK GÜNÜ (`docs/122` Y2). Üç ekran, üçü de yalnız
        OKUYOR: kiracı ayrıntısı, kullanıcı görünürlüğü, denetim günlüğü.
        Metinler bilerek "bu ekran ne yapmaz" cümlesini de taşır — bir
        destek aracında eksik olan yeteneğin sessizce eksik olması, yanlış
        beklenti üretir.
    */
    'platform.shell.group.oversight': 'Oversight',

    'platform.tenants.nav.label': 'Workspaces',
    'platform.tenants.region.label': 'Workspace detail',
    'platform.tenants.intro':
        'Pick a workspace to see its branches, menus, usage, subscription and last events. This screen only reads: nothing here changes a customer’s data, and there is no way to sign in as a customer.',
    'platform.tenants.idle': 'Pick a workspace to see its detail.',
    'platform.tenants.loading': 'Loading the workspace…',
    'platform.tenants.error': 'We could not load this workspace.',
    'platform.tenants.retry': 'Retry',
    'platform.tenants.identity.title': 'Identity',
    'platform.tenants.identity.slug': 'Slug',
    'platform.tenants.identity.state': 'State',
    'platform.tenants.identity.created': 'Created',
    'platform.tenants.identity.brand': 'Brand',
    'platform.tenants.identity.currency': 'Currency',
    'platform.tenants.identity.locale': 'Content language',
    'platform.tenants.identity.noBrand': 'This workspace has no brand yet.',
    'platform.tenants.subscription.title': 'Subscription',
    // "Aboneliği yok" ile "aboneliğini okuyamadık" AYRI durumlar; boş bir
    // plan kartı ikisini birbirine karıştırırdı.
    'platform.tenants.subscription.none':
        'No subscription has ever been recorded for this workspace.',
    'platform.tenants.subscription.plan': 'Plan',
    'platform.tenants.subscription.version': 'Plan version',
    'platform.tenants.subscription.endsAt': 'Runs until',
    'platform.tenants.usage.title': 'Usage',
    'platform.tenants.usage.locations': 'Branches',
    'platform.tenants.usage.menus': 'Menus',
    'platform.tenants.usage.products': 'Products',
    'platform.tenants.usage.mediaAssets': 'Media files',
    'platform.tenants.usage.members': 'Team members',
    'platform.tenants.locations.title': 'Branches',
    'platform.tenants.locations.empty': 'No branch yet.',
    'platform.tenants.menus.title': 'Menus',
    'platform.tenants.menus.empty': 'No menu yet.',
    'platform.tenants.members.title': 'Team',
    'platform.tenants.members.empty': 'Nobody belongs to this workspace.',
    'platform.tenants.events.title': 'Last events',
    'platform.tenants.events.empty': 'Nothing has been recorded for this workspace yet.',
    // Kırpılan liste, yanlış sayı değildir — ama söylenmezse öyle okunur.
    'platform.tenants.truncated': 'Only the first rows are drawn; the count above is the real one.',
    'platform.tenants.col.name': 'Name',
    'platform.tenants.col.city': 'City',
    'platform.tenants.col.country': 'Country',
    'platform.tenants.col.timezone': 'Time zone',
    'platform.tenants.col.state': 'State',
    'platform.tenants.col.branch': 'Branch',
    'platform.tenants.col.role': 'Role',
    'platform.tenants.col.email': 'Email',
    'platform.tenants.col.since': 'Member since',
    'platform.tenants.col.when': 'When',
    'platform.tenants.col.source': 'Source',
    'platform.tenants.col.action': 'Event',
    'platform.tenants.col.subject': 'Subject',
    'platform.tenants.col.actor': 'By',

    'platform.users.nav.label': 'Users',
    'platform.users.region.label': 'Users',
    'platform.users.intro':
        'Who a person is, which workspaces they belong to and with which role, and whether their address is verified. This screen only reads — there is no password reset, no lock and no role change here.',
    'platform.users.search.label': 'Search by name or email address',
    'platform.users.search.submit': 'Search',
    'platform.users.loading': 'Loading users…',
    'platform.users.error': 'We could not load users.',
    'platform.users.retry': 'Retry',
    'platform.users.empty': 'No user matches this search.',
    'platform.users.title': 'Users',
    'platform.users.col.name': 'Person',
    'platform.users.col.email': 'Email',
    'platform.users.col.verified': 'Address verified',
    'platform.users.col.platformRole': 'Platform role',
    'platform.users.col.sessions': 'Open sessions',
    'platform.users.col.memberships': 'Workspaces',
    'platform.users.verified.no': 'Not verified',
    'platform.users.memberships.none': 'Belongs to no workspace',
    'platform.users.sessions.note':
        'Open sessions are only counted where this installation keeps sessions in the database. An empty cell means “not measured here”, not “nobody is signed in”.',
    'platform.users.lock.note':
        'This product has no user lock today, so no lock column is drawn: a “not locked” badge would report a check that does not exist.',
    'platform.users.truncated': 'Only the first rows are drawn. Narrow the search to see the rest.',

    'platform.auditLog.nav.label': 'Audit log',
    'platform.auditLog.region.label': 'Audit log',
    'platform.auditLog.intro':
        'Who did what, and when — read from the trails this product already writes: media, menu, publication and the credential vault. Read-only: a recorded event cannot be edited or erased from here.',
    'platform.auditLog.loading': 'Loading the audit log…',
    'platform.auditLog.error': 'We could not load the audit log.',
    'platform.auditLog.retry': 'Retry',
    'platform.auditLog.empty': 'Nothing has been recorded yet.',
    'platform.auditLog.title': 'Events',
    'platform.auditLog.filter.source': 'Source',
    'platform.auditLog.filter.source.all': 'All sources',
    'platform.auditLog.filter.workspace': 'Workspace ID',
    'platform.auditLog.filter.apply': 'Apply',
    'platform.auditLog.source.media': 'Media',
    'platform.auditLog.source.menu': 'Menu',
    'platform.auditLog.source.publication': 'Publication',
    'platform.auditLog.source.credential': 'Credential vault',
    'platform.auditLog.col.when': 'When',
    'platform.auditLog.col.source': 'Source',
    'platform.auditLog.col.action': 'Event',
    'platform.auditLog.col.subject': 'Subject',
    'platform.auditLog.col.actor': 'By',
    'platform.auditLog.col.workspace': 'Workspace',
    'platform.auditLog.page': 'Page {page}',
    'platform.auditLog.prev': 'Previous page',
    'platform.auditLog.next': 'Next page',
    'platform.auditLog.scope':
        'A credential-vault row belongs to no workspace, so its workspace cell stays empty. A menu event’s old and new values are deliberately not shown here; they live in that workspace’s own menu history.',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('platform'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const platformTranslations: Record<string, string> = en;
