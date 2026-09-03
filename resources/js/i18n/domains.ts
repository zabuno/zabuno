/**
 * Çeviri alan adları (text domains) — CORE-08.
 *
 * Bir "domain" tek bir sorumluluk alanının İngilizce kaynak kataloğudur.
 * PO/MO/JSON zincirinin her adımı bu listeyi kaynak alır; hiçbir script
 * dosya adı tahmin etmez. Yeni bir katalog eklemek, burada tek satırdır.
 */
import { authTranslations } from './auth';
import { dashboardTranslations } from './dashboard';
import { guestTranslations } from './guest';
import { menuTranslations } from './menu';
import { platformTranslations } from './platform';
import { siteTranslations } from './site';
import { themeTranslations } from './theme';
import { workspaceTranslations } from './workspace';

export const DOMAIN_CATALOGS: Record<string, Record<string, string>> = {
    auth: authTranslations,
    dashboard: dashboardTranslations,
    // MİSAFİR yüzeyi: kaynak dili TÜRKÇE (`resources/js/i18n/guest.ts`).
    guest: guestTranslations,
    menu: menuTranslations,
    platform: platformTranslations,
    // TANITIM SİTESİ: ürünün kendi yüzeyi, restoranınki değil (`docs/88`).
    site: siteTranslations,
    theme: themeTranslations,
    workspace: workspaceTranslations,
};

export const DOMAINS = Object.keys(DOMAIN_CATALOGS).sort();
