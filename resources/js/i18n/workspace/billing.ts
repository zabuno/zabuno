export const billing = {
    'workspace.billing.heading': 'Billing',
    'workspace.billing.plan.region': 'Plan',
    'workspace.billing.plan.unavailable':
        'Plan information is not available yet; no billing API has been queried.',
    'workspace.billing.plan.loading': 'Loading plans…',
    'workspace.billing.plan.empty': 'No plans are available yet.',
    'workspace.billing.plan.error': 'Plans could not be loaded.',
    'workspace.billing.plan.retry': 'Retry',
    'workspace.billing.plan.priceUnavailable': 'Price unavailable',
    'workspace.billing.currentPlan.region': 'Current plan',
    'workspace.billing.currentPlan.empty':
        'No billing API has been queried yet; the current plan has not been checked.',
    'workspace.billing.currentPlan.loading': 'Loading current plan…',
    'workspace.billing.currentPlan.none': 'No active subscription',
    'workspace.billing.currentPlan.error': 'We could not load the current plan.',
    'workspace.billing.currentPlan.retry': 'Retry',
    'workspace.billing.currentPlan.version': 'Version {version}',
    'workspace.billing.manualPayment.region': 'Manual payment',
    'workspace.billing.manualPayment.empty':
        'No billing API has been queried yet; manual payment has not been checked.',
    'workspace.billing.manualPayment.platformFinance':
        'Manual payments are recorded by platform finance; this workspace view is read-only.',
    'workspace.billing.changePlan.button': 'Change plan',
    'workspace.billing.recordPayment.button': 'Record payment',
    'workspace.billing.operational.description':
        'The plan catalog and current subscription are fetched live from the server-backed billing API. Manual payments below are recorded in Zabuno Platform administration by finance staff. The Iyzico sandbox checkout below is a sandbox-only surface — no real money is charged.',
    'workspace.billing.status.notConnected': 'Not connected',
    'workspace.billing.manualPayment.field.plan': 'Plan assignment',
    'workspace.billing.manualPayment.field.endDate': 'End date',
    'workspace.billing.manualPayment.field.paymentNote': 'Payment note',
    'workspace.billing.manualPayment.field.documentReference': 'Document reference',
    'workspace.billing.iyzicoSandbox.region': 'Iyzico sandbox',
    'workspace.billing.iyzicoSandbox.unavailable':
        'The Iyzico sandbox checkout is unavailable and pending integration; no sandbox session can be started.',
    'workspace.billing.iyzicoSandbox.pendingBadge': 'Iyzico sandbox pending',
    'workspace.billing.iyzicoSandbox.button': 'Start sandbox checkout',
    'workspace.billing.iyzicoSandbox.heading': 'Iyzico sandbox',
    'workspace.billing.iyzicoSandbox.disclaimer':
        'This is a sandbox checkout — no real money is charged.',
    'workspace.billing.iyzicoSandbox.loading': 'Loading…',
    'workspace.billing.iyzicoSandbox.noActive': 'No active subscription or plan.',
    'workspace.billing.iyzicoSandbox.ready': 'Ready to start a sandbox checkout.',
    'workspace.billing.iyzicoSandbox.start': 'Start sandbox checkout',
    'workspace.billing.iyzicoSandbox.processing': 'Sandbox checkout initiated — processing…',
    'workspace.billing.iyzicoSandbox.state.initiated': 'Initiated',
    'workspace.billing.iyzicoSandbox.state.processing': 'Processing',
    'workspace.billing.iyzicoSandbox.state.succeeded': 'Succeeded',
    'workspace.billing.iyzicoSandbox.state.failed': 'Sandbox checkout failed.',
    'workspace.billing.iyzicoSandbox.retry': 'Retry',
    'workspace.billing.iyzicoSandbox.continueLink': 'Continue to Iyzico sandbox',
    'workspace.billing.iyzicoSandbox.invalidRedirect':
        'Received an unsafe redirect link; cannot continue.',
    'workspace.billing.iyzicoSandbox.sessionError': 'Could not load the sandbox checkout session.',
    'workspace.billing.iyzicoSandbox.checkoutError': 'Could not start the sandbox checkout.',
    'workspace.billing.iyzicoSandbox.subscriptionError': 'Could not load your subscription status.',
    'workspace.billing.iyzicoSandbox.conversation': 'Conversation {id}',
    'workspace.billing.iyzicoSandbox.amount': 'Amount {amount} {currency}',
    'workspace.billing.ledger.region': 'Ledger',
    'workspace.billing.ledger.description':
        'Every collected payment is written here as a double entry. A record is never edited or deleted; a correction is made with an opposing entry.',
    'workspace.billing.ledger.loading': 'Loading ledger…',
    'workspace.billing.ledger.empty':
        'No ledger entry yet. The first successful payment writes the first entry.',
    'workspace.billing.ledger.error': 'The ledger could not be loaded.',
    'workspace.billing.ledger.retry': 'Retry',
    'workspace.billing.ledger.column.reference': 'Reference',
    'workspace.billing.ledger.column.debit': 'Debit',
    'workspace.billing.ledger.column.credit': 'Credit',
    'workspace.billing.ledger.column.amount': 'Amount',
    'workspace.billing.ledger.column.occurredAt': 'Date',
    'workspace.billing.ledger.balances': 'Balances',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof billing, string> {}
}
