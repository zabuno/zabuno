export const billing = {
    'workspace.billing.heading': 'Billing',
    'workspace.billing.plan.region': 'Plan',
    'workspace.billing.plan.unavailable': 'Plan information is not available yet.',
    'workspace.billing.plan.loading': 'Loading plans…',
    'workspace.billing.plan.empty': 'No plans are available yet.',
    'workspace.billing.plan.error': 'Plans could not be loaded.',
    'workspace.billing.plan.retry': 'Retry',
    'workspace.billing.plan.priceUnavailable': 'Price unavailable',
    'workspace.billing.currentPlan.region': 'Current plan',
    'workspace.billing.currentPlan.empty': 'Your current plan has not been loaded yet.',
    'workspace.billing.currentPlan.loading': 'Loading current plan…',
    'workspace.billing.currentPlan.none': 'No active subscription',
    'workspace.billing.currentPlan.error': 'We could not load the current plan.',
    'workspace.billing.currentPlan.retry': 'Retry',
    'workspace.billing.currentPlan.version': 'Version {version}',
    'workspace.billing.manualPayment.region': 'Manual payment',
    'workspace.billing.manualPayment.empty': 'No manual payment has been recorded yet.',
    'workspace.billing.manualPayment.platformFinance':
        'Manual payments are recorded by platform finance; this workspace view is read-only.',
    'workspace.billing.changePlan.button': 'Change plan',
    'workspace.billing.recordPayment.button': 'Record payment',
    'workspace.billing.operational.description':
        'See your plan, change it, and review payments. Payments made by bank transfer are recorded for you by our finance team.',
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
    // Kendi kendine abonelik (docs/107 Faz 1.1 + 1.3, docs/123): plan seç →
    // fatura profili → Iyzico ödeme sayfası. Kart alanı YOK — kart yalnız
    // sağlayıcının sayfasına girilir.
    'workspace.billing.checkout.region': 'Subscribe',
    'workspace.billing.checkout.description':
        'Choose a plan, confirm your billing details, and pay by card on the Iyzico payment page. Each payment covers {days} days.',
    'workspace.billing.checkout.mode.sandbox': 'Test mode: no real money is charged.',
    'workspace.billing.checkout.mode.live':
        'You will be redirected to Iyzico to pay by card. Zabuno never stores card details.',
    'workspace.billing.checkout.plan.legend': 'Choose a plan',
    'workspace.billing.checkout.plan.loading': 'Loading plans…',
    'workspace.billing.checkout.plan.empty': 'No plan can be purchased yet.',
    'workspace.billing.checkout.plan.error': 'Plans could not be loaded.',
    'workspace.billing.checkout.plan.price': '{price} / {days} days',
    'workspace.billing.checkout.profile.heading': 'Billing details',
    'workspace.billing.checkout.profile.loading': 'Loading billing details…',
    'workspace.billing.checkout.profile.missing':
        'Billing details are missing. Add your company name, tax number and address before paying.',
    'workspace.billing.checkout.profile.error': 'Billing details could not be loaded.',
    'workspace.billing.checkout.profile.add': 'Add billing details',
    'workspace.billing.checkout.profile.edit': 'Edit billing details',
    'workspace.billing.checkout.profile.summary.taxNumber': 'Tax number {number}',
    'workspace.billing.checkout.proceed': 'Proceed to payment',
    'workspace.billing.checkout.proceeding': 'Opening the payment page…',
    'workspace.billing.checkout.invalidRedirect':
        'Received an unsafe redirect link; cannot continue.',
    'workspace.billing.checkout.planNotPurchasable': 'This plan cannot be purchased.',
    'workspace.billing.checkout.status.loading': 'Loading payment status…',
    'workspace.billing.checkout.status.error': 'Payment status could not be loaded.',
    'workspace.billing.checkout.latest.failed': 'Payment failed: {reason}',
    'workspace.billing.checkout.latest.failedNoReason': 'Payment failed.',
    'workspace.billing.checkout.latest.failedHint': 'Nothing was charged. You can try again.',
    'workspace.billing.checkout.latest.succeeded': 'Your last payment succeeded.',
    'workspace.billing.checkout.latest.initiated':
        'A payment is in progress. If you left the payment page, you can continue or start again.',
    'workspace.billing.checkout.latest.continue': 'Continue to the payment page',
    'workspace.billing.checkout.latest.refunded': 'Your last payment was refunded.',
    'workspace.billing.checkout.retry': 'Retry',
    'workspace.billing.profile.field.legalName': 'Company name',
    'workspace.billing.profile.field.taxNumber': 'Tax number',
    'workspace.billing.profile.field.taxOffice': 'Tax office',
    'workspace.billing.profile.field.address': 'Address',
    'workspace.billing.profile.field.city': 'City',
    'workspace.billing.profile.field.country': 'Country code',
    'workspace.billing.profile.field.countryHelp': 'Two-letter country code, for example TR.',
    'workspace.billing.profile.field.email': 'Billing email',
    'workspace.billing.profile.field.phone': 'Phone',
    'workspace.billing.profile.save': 'Save billing details',
    'workspace.billing.profile.cancel': 'Cancel',
    'workspace.billing.profile.saveError': 'Billing details could not be saved.',
    // FATURA (FF-215, docs/107 Faz 1.4, docs/130): tahsilatın karşılığındaki
    // belge. Ekran kipi burada; kâğıt kipi sunucuda üretilen A4 PDF'tir ve
    // metni belgenin kendi kaynak dilindedir (katalogda değil).
    'workspace.billing.invoices.region': 'Invoices',
    'workspace.billing.invoices.description':
        'Every collected payment is recorded here as a numbered document. A document is never edited or deleted; a refund is recorded as a separate credit note.',
    'workspace.billing.invoices.loading': 'Loading invoices…',
    'workspace.billing.invoices.empty':
        'No invoice yet. The first successful payment creates the first document.',
    'workspace.billing.invoices.error': 'Invoices could not be loaded.',
    'workspace.billing.invoices.retry': 'Retry',
    'workspace.billing.invoices.kind.invoice': 'Invoice',
    'workspace.billing.invoices.kind.creditNote': 'Credit note',
    'workspace.billing.invoices.issuedAt': 'Issued {date}',
    'workspace.billing.invoices.line': '{plan} — {days} days of subscription',
    'workspace.billing.invoices.vat': 'VAT {amount}',
    'workspace.billing.invoices.vatUnknown':
        'No VAT rate is configured, so this document shows no tax breakdown.',
    'workspace.billing.invoices.download': 'Download PDF',
    'workspace.billing.invoices.sellerIncomplete':
        'Our company details are not complete yet, so this document is not a complete commercial invoice.',
    'workspace.billing.invoices.missingDocuments':
        '{count} collected payment(s) have no document, because no billing details were recorded when they were collected.',
    'workspace.billing.invoices.earchiveNotConfigured':
        'No e-Arşiv / e-Fatura provider is connected, so these records have not been sent to any tax authority.',
    // ABONELİĞİN EKSİK YARISI (FF-219, docs/107 Faz 1.3, docs/134): iptal,
    // iptalden cayma, plan düşürme, ödemesiz süre ve askı. Metinler TARİHİ ve
    // SONUCU söyler; "aboneliğiniz güncellendi" gibi hiçbir şey anlatmayan bir
    // cümle yoktur.
    'workspace.billing.lifecycle.region': 'Subscription',
    'workspace.billing.lifecycle.loading': 'Loading your subscription…',
    'workspace.billing.lifecycle.error': 'Your subscription could not be loaded.',
    'workspace.billing.lifecycle.retry': 'Retry',
    'workspace.billing.lifecycle.none':
        'You have no paid subscription. The free journey — menu, publishing and QR codes — keeps working.',
    'workspace.billing.lifecycle.active': 'Your {plan} plan runs until {date}.',
    'workspace.billing.lifecycle.cancelled':
        'You cancelled. You keep using {plan} until {date}, and it will not renew.',
    'workspace.billing.lifecycle.grace':
        'Your paid period ended on {date} and no payment has arrived. Your {plan} features stay on until {graceDate}. Pay below to continue.',
    'workspace.billing.lifecycle.suspended':
        'Your {plan} features have been off since {graceDate} because the period was not paid for. Your published menus are still online and your data is untouched — a payment turns the features back on.',
    'workspace.billing.lifecycle.ended':
        'Your subscription ended on {date}. Your published menus are still online and your data is untouched.',
    'workspace.billing.lifecycle.guestUnaffected':
        'Your guests are not told about any of this: the menu behind a printed QR code keeps showing exactly what you published.',
    'workspace.billing.lifecycle.cancel': 'Cancel subscription',
    'workspace.billing.lifecycle.cancel.confirmHeading': 'Cancel your subscription?',
    'workspace.billing.lifecycle.cancel.confirmBody':
        'Your plan stays active until {date}. Nothing is refunded for the period you have already paid for, and you can undo this before {date}.',
    'workspace.billing.lifecycle.cancel.confirm': 'Yes, cancel',
    'workspace.billing.lifecycle.cancel.dismiss': 'Keep my subscription',
    'workspace.billing.lifecycle.cancel.error': 'The subscription could not be cancelled.',
    'workspace.billing.lifecycle.resume': 'Undo the cancellation',
    'workspace.billing.lifecycle.resume.hint': 'You will not be asked to pay again.',
    'workspace.billing.lifecycle.resume.error': 'The cancellation could not be undone.',
    'workspace.billing.lifecycle.change.heading': 'Change plan',
    'workspace.billing.lifecycle.change.legend': 'Move to a cheaper plan',
    'workspace.billing.lifecycle.change.empty': 'There is no cheaper plan to move to.',
    'workspace.billing.lifecycle.change.upgradeHint':
        'To move up a plan, pay for it below — the higher plan starts as soon as the payment succeeds.',
    'workspace.billing.lifecycle.change.previewError':
        'We could not work out what this change would do.',
    'workspace.billing.lifecycle.change.effective':
        'Takes effect on {date}. Nothing is refunded for the current period.',
    'workspace.billing.lifecycle.change.losing': 'You will lose on {date}:',
    'workspace.billing.lifecycle.change.losingNothing':
        'Nothing you use today goes away; only the price changes.',
    'workspace.billing.lifecycle.change.guestWarning':
        'Guests see the difference only in your next publication — the menu behind a QR code you have already printed does not change.',
    'workspace.billing.lifecycle.change.submit': 'Schedule the change',
    'workspace.billing.lifecycle.change.error': 'The plan change could not be scheduled.',
    'workspace.billing.lifecycle.scheduled':
        'On {date} your plan becomes {plan}. Until then nothing changes.',
    'workspace.billing.lifecycle.scheduled.withdraw': 'Keep my current plan',
    'workspace.billing.lifecycle.scheduled.error': 'The scheduled change could not be withdrawn.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof billing, string> {}
}
