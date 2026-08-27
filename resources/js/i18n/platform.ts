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
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey, vars?: Record<string, string>) => string = createTranslator(
    en,
    overridesFor('platform'),
);

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const platformTranslations: Record<string, string> = en;
