import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
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
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('platform'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const platformTranslations: Record<string, string> = en;
