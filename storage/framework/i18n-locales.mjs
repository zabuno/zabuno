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
function directionOf(locale) {
  return LOCALES[locale].direction;
}
function currentLocale() {
  if (typeof document === "undefined") {
    return FALLBACK_LOCALE;
  }
  const declared = document.documentElement.lang?.trim().toLowerCase() ?? "";
  const base = declared.split("-")[0];
  return isLocaleCode(base) ? base : FALLBACK_LOCALE;
}
export {
  FALLBACK_LOCALE,
  LOCALES,
  currentLocale,
  directionOf,
  isLocaleCode
};
