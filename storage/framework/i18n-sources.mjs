// resources/js/i18n/auth.ts
var en = {
  "auth.register.heading": "Create your account",
  "auth.register.name": "Name",
  "auth.register.email": "Email",
  "auth.register.password": "Password",
  "auth.register.password_confirmation": "Confirm password",
  "auth.register.submit": "Register",
  "auth.register.error.name": "Enter your name.",
  "auth.register.error.email": "Enter a valid email address.",
  "auth.register.error.password": "Enter a password.",
  "auth.register.error.submit": "We could not create your account. Please try again.",
  "auth.login.heading": "Log in",
  "auth.login.email": "Email",
  "auth.login.password": "Password",
  "auth.login.submit": "Log in",
  "auth.login.forgot_password": "Forgot your password?",
  "auth.login.error.email": "Enter a valid email address.",
  "auth.login.error.password": "Enter your password.",
  "auth.login.error.submit": "We could not log you in. Please try again.",
  "auth.forgot_password.heading": "Forgot your password?",
  "auth.forgot_password.email": "Email",
  "auth.forgot_password.submit": "Send reset link",
  "auth.forgot_password.status.sent": "If an account exists for that email, a reset link has been sent.",
  "auth.forgot_password.error.email": "Enter a valid email address.",
  "auth.forgot_password.error.submit": "We could not process your request. Please try again.",
  "auth.reset_password.heading": "Reset your password",
  "auth.reset_password.password": "Password",
  "auth.reset_password.password_confirmation": "Confirm password",
  "auth.reset_password.submit": "Reset password",
  "auth.reset_password.error.password": "Enter a new password.",
  "auth.reset_password.error.password_confirmation": "Confirm your new password.",
  "auth.reset_password.error.submit": "We could not reset your password. Please try again.",
  "auth.verification_pending.heading": "Verification pending",
  "auth.verification_pending.body": "We sent a verification link to {email}. Click it to activate your account.",
  "auth.verification_pending.resend": "Resend verification email",
  "auth.verification_pending.status.idle": "",
  "auth.verification_pending.status.sending": "Sending verification email\u2026",
  "auth.verification_pending.status.sent": "Verification email sent.",
  "auth.verification_pending.status.error": "Could not resend verification email.",
  "auth.verified.heading": "Email verified",
  "auth.verified.body": "Your email address has been verified.",
  "auth.logout.submit": "Log out",
  "auth.logout.error.submit": "We could not log you out. Please try again.",
  "auth.invitation_accept.heading": "Workspace invitation",
  "auth.invitation_accept.workspace": "Workspace",
  "auth.invitation_accept.email": "Invited email",
  "auth.invitation_accept.role": "Role",
  "auth.invitation_accept.submit": "Accept invitation",
  "auth.invitation_accept.guest_body": "Log in to accept this invitation.",
  "auth.invitation_accept.login_link": "Log in",
  "auth.invitation_accept.unavailable_body": "This invitation is not available.",
  "auth.invitation_accept.error.submit": "We could not accept this invitation. Please try again."
};
var authTranslations = en;

// resources/js/i18n/dashboard.ts
var en2 = {
  "dashboard.heading": "Dashboard",
  "dashboard.loading": "Loading your dashboard summary\u2026",
  "dashboard.empty": "No menu has been created for this location yet.",
  "dashboard.empty.openMenu": "Open Menu",
  "dashboard.setup.region": "Dashboard Setup",
  "dashboard.setup.heading": "Setup",
  "dashboard.setup.brand": "Brand",
  "dashboard.setup.location": "Location",
  "dashboard.setup.menu": "Menu",
  "dashboard.setup.publication": "Publication",
  "dashboard.setup.qr": "QR",
  "dashboard.setup.menu.empty": "No menu yet",
  "dashboard.setup.notConnected": "Not connected yet.",
  "dashboard.setup.statusUnavailable": "Status unavailable.",
  "dashboard.setup.checking": "Checking\u2026",
  "dashboard.setup.published": "Published #{id}",
  "dashboard.setup.qr.activeCount": "{count} active QR",
  "dashboard.setup.qr.activeCount.plural": "{count} active QRs"
};
var dashboardTranslations = en2;

// resources/js/i18n/locales.ts
var LOCALES = {
  en: { name: "English", direction: "ltr", status: "complete" },
  tr: { name: "T\xFCrk\xE7e", direction: "ltr", status: "scaffold" },
  de: { name: "Deutsch", direction: "ltr", status: "scaffold" },
  fr: { name: "Fran\xE7ais", direction: "ltr", status: "scaffold" },
  ar: { name: "\u0627\u0644\u0639\u0631\u0628\u064A\u0629", direction: "rtl", status: "scaffold" },
  ru: { name: "\u0420\u0443\u0441\u0441\u043A\u0438\u0439", direction: "ltr", status: "scaffold" }
};
var FALLBACK_LOCALE = "en";
function isLocaleCode(value) {
  return value in LOCALES;
}
function currentLocale() {
  if (typeof document === "undefined") {
    return FALLBACK_LOCALE;
  }
  const declared = document.documentElement.lang?.trim().toLowerCase() ?? "";
  const base = declared.split("-")[0];
  return isLocaleCode(base) ? base : FALLBACK_LOCALE;
}

// resources/js/i18n/translator.ts
function interpolate(template, vars) {
  if (!vars) return template;
  return Object.entries(vars).reduce(
    (result, [name, value]) => result.replaceAll(`{${name}}`, value),
    template
  );
}
function createTranslator(base, overrides = {}) {
  return function t2(key, vars) {
    const locale = currentLocale();
    const translated = locale === FALLBACK_LOCALE ? void 0 : overrides[locale]?.[key];
    const template = translated ?? base[key] ?? String(key);
    return interpolate(template, vars);
  };
}

// resources/js/i18n/catalogs/menu.tr.ts
var menuTr = {
  "menu.loading": "Men\xFC y\xFCkleniyor\u2026",
  "menu.status.saving": "Kaydediliyor\u2026",
  "menu.initial.error.load": "Men\xFCn\xFCz\xFC y\xFCkleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.initial.error.retry": "Tekrar dene",
  "menu.name.label": "Men\xFC ad\u0131",
  "menu.name.error.required": "Bir men\xFC ad\u0131 girin.",
  "menu.create.submit": "Men\xFC olu\u015Ftur",
  "menu.create.error.submit": "Men\xFCy\xFC olu\u015Fturamad\u0131k. L\xFCtfen tekrar deneyin.",
  "menu.categories.list.label": "Men\xFC kategorileri",
  "menu.category.select.label": "Kategori",
  "menu.category.name.label": "Kategori ad\u0131",
  "menu.category.create.submit": "Kategori ekle",
  "menu.category.name.error.required": "Bir kategori ad\u0131 girin.",
  "menu.category.create.error.submit": "Kategoriyi ekleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.category.items.label": "{name} i\xE7indeki \xFCr\xFCnler",
  "menu.product.name.label": "\xDCr\xFCn ad\u0131",
  "menu.product.create.submit": "\xDCr\xFCn ekle",
  "menu.product.name.error.required": "Bir \xFCr\xFCn ad\u0131 girin.",
  "menu.product.create.error.submit": "\xDCr\xFCn\xFC ekleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.item.price.label": "Fiyat",
  "menu.item.currency.label": "Para birimi",
  "menu.item.create.submit": "Kalem ekle",
  "menu.item.price.error.required": "Bir fiyat girin.",
  "menu.item.create.error.submit": "Men\xFC kalemini ekleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.item.allergens.label": "Alerjenler (virg\xFClle ayr\u0131lm\u0131\u015F)",
  "menu.item.allergens.submit": "Alerjenleri kaydet",
  "menu.item.allergens.error.submit": "Alerjenleri g\xFCncelleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.item.allergens.list.label": "{name} alerjenleri",
  "menu.item.allergens.edit.button": "{name} alerjenlerini d\xFCzenle",
  "menu.item.price.edit.button": "{name} fiyat\u0131n\u0131 d\xFCzenle",
  "menu.item.price.edit.submit": "Fiyat\u0131 kaydet",
  "menu.item.price.edit.error.submit": "Fiyat\u0131 g\xFCncelleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.item.visibility.checkbox.label": "{name} \xFCr\xFCn\xFCn\xFC g\xF6ster",
  "menu.item.visibility.error.submit": "G\xF6r\xFCn\xFCrl\xFC\u011F\xFC g\xFCncelleyemedik. L\xFCtfen tekrar deneyin.",
  "menu.category.order.label": "{name} s\u0131ras\u0131",
  "menu.item.order.label": "{name} s\u0131ras\u0131"
};

// resources/js/i18n/menu.ts
var en3 = {
  "menu.loading": "Loading menu\u2026",
  "menu.status.saving": "Saving\u2026",
  "menu.initial.error.load": "We could not load your menu. Please try again.",
  "menu.initial.error.retry": "Retry",
  "menu.name.label": "Menu name",
  "menu.name.error.required": "Enter a menu name.",
  "menu.create.submit": "Create menu",
  "menu.create.error.submit": "We could not create the menu. Please try again.",
  "menu.categories.list.label": "Menu categories",
  "menu.category.select.label": "Category",
  "menu.category.name.label": "Category name",
  "menu.category.create.submit": "Add category",
  "menu.category.name.error.required": "Enter a category name.",
  "menu.category.create.error.submit": "We could not add the category. Please try again.",
  "menu.category.items.label": "Items in {name}",
  "menu.product.name.label": "Product name",
  "menu.product.create.submit": "Add product",
  "menu.product.name.error.required": "Enter a product name.",
  "menu.product.create.error.submit": "We could not add the product. Please try again.",
  "menu.item.price.label": "Price",
  "menu.item.currency.label": "Currency",
  "menu.item.create.submit": "Add item",
  "menu.item.price.error.required": "Enter a price.",
  "menu.item.create.error.submit": "We could not add the menu item. Please try again.",
  "menu.item.allergens.label": "Allergens (comma-separated)",
  "menu.item.allergens.submit": "Save allergens",
  "menu.item.allergens.error.submit": "We could not update allergens. Please try again.",
  "menu.item.allergens.list.label": "Allergens for {name}",
  "menu.item.allergens.edit.button": "Edit allergens for {name}",
  "menu.item.price.edit.button": "Edit price for {name}",
  "menu.item.price.edit.submit": "Save price",
  "menu.item.price.edit.error.submit": "We could not update the price. Please try again.",
  "menu.item.visibility.checkbox.label": "Show {name}",
  "menu.item.visibility.error.submit": "We could not update visibility. Please try again.",
  "menu.category.order.label": "Order for {name}",
  "menu.item.order.label": "Order for {name}"
};
var t = createTranslator(
  en3,
  { tr: menuTr }
);
var menuTranslations = en3;

// resources/js/i18n/platform.ts
var en4 = {
  "platform.shell.brand": "Zabuno Platform",
  "platform.shell.navLabel": "Platform admin",
  "platform.shell.heading": "Platform administration",
  "platform.shell.backToWorkspace": "Back to workspace",
  "platform.plans.region.label": "Plans",
  "platform.plans.loading": "Loading plans\u2026",
  "platform.plans.empty": "No plans yet.",
  "platform.plans.error": "We could not load plans.",
  "platform.plans.retry": "Retry",
  "platform.plans.priceUnavailable": "Price unavailable",
  "platform.plans.inactive": "Inactive",
  "platform.plans.form.heading": "Create plan",
  "platform.plans.form.name": "Plan name",
  "platform.plans.form.code": "Code",
  "platform.plans.form.version": "Version",
  "platform.plans.form.amount": "Amount (minor units)",
  "platform.plans.form.currency": "Currency",
  "platform.plans.form.entitlements": "Entitlements (one per line)",
  "platform.plans.form.sortOrder": "Sort order",
  "platform.plans.form.submit": "Create plan",
  "platform.plans.form.error": "We could not create the plan. Please try again.",
  "platform.plans.activate.button": "Activate",
  "platform.plans.activate.dialog.heading": "Activate plan",
  "platform.plans.activate.dialog.cancel": "Cancel",
  "platform.plans.activate.dialog.confirm": "Confirm activation",
  "platform.plans.activate.success": "Plan activated.",
  "platform.plans.activate.error": "We could not activate this plan. Please try again.",
  "platform.subscriptions.nav.label": "Subscriptions",
  "platform.subscriptions.workspace.label": "Workspace",
  "platform.subscriptions.workspace.loading": "Loading workspaces\u2026",
  "platform.subscriptions.workspace.empty": "No workspaces found.",
  "platform.subscriptions.workspace.error": "We could not load workspaces.",
  "platform.subscriptions.workspace.retry": "Retry",
  "platform.subscriptions.workspace.placeholder": "Select a workspace",
  "platform.subscriptions.plans.blocked": "A plan must be created and activated before recording a manual payment.",
  "platform.subscriptions.plans.error": "We could not load plans.",
  "platform.subscriptions.subscription.region.label": "Subscription",
  "platform.subscriptions.subscription.loading": "Loading subscription\u2026",
  "platform.subscriptions.subscription.none": "No active subscription",
  "platform.subscriptions.subscription.error": "We could not load the subscription.",
  "platform.subscriptions.subscription.retry": "Retry",
  "platform.subscriptions.subscription.active.label": "Active",
  "platform.subscriptions.form.plan.label": "Plan",
  "platform.subscriptions.form.endDate.label": "End date",
  "platform.subscriptions.form.paymentNote.label": "Payment note",
  "platform.subscriptions.form.documentReference.label": "Document reference",
  "platform.subscriptions.form.submit": "Record manual payment",
  "platform.subscriptions.form.error": "We could not record the manual payment. Please try again.",
  "platform.subscriptions.form.retry": "Retry",
  "platform.subscriptions.confirm.heading": "Confirm manual payment",
  "platform.subscriptions.confirm.cancel": "Cancel",
  "platform.subscriptions.confirm.confirm": "Confirm",
  "platform.subscriptions.success": "Manual payment recorded successfully.",
  "platform.subscriptions.success.region.label": "Manual payment status"
};
var platformTranslations = en4;

// resources/js/i18n/theme.ts
var en5 = {
  "theme.group_label": "Theme",
  "theme.system": "System",
  "theme.light": "Light",
  "theme.dark": "Dark"
};
var themeTranslations = en5;

// resources/js/i18n/workspace.ts
var modules = import.meta.glob("./workspace/*.ts", {
  eager: true
});
var en6 = {};
var seenSourceByKey = /* @__PURE__ */ new Map();
for (const modulePath of Object.keys(modules).sort()) {
  const moduleExports = modules[modulePath];
  for (const catalog of Object.values(moduleExports)) {
    for (const [key, value] of Object.entries(catalog)) {
      const existingSource = seenSourceByKey.get(key);
      if (existingSource !== void 0) {
        throw new Error(
          `Duplicate workspace translation key "${key}" found in "${modulePath}"; already defined in "${existingSource}".`
        );
      }
      seenSourceByKey.set(key, modulePath);
      en6[key] = value;
    }
  }
}
var workspaceTranslations = en6;

// resources/js/i18n/domains.ts
var DOMAIN_CATALOGS = {
  auth: authTranslations,
  dashboard: dashboardTranslations,
  menu: menuTranslations,
  platform: platformTranslations,
  theme: themeTranslations,
  workspace: workspaceTranslations
};
var DOMAINS = Object.keys(DOMAIN_CATALOGS).sort();
export {
  DOMAINS,
  DOMAIN_CATALOGS
};
