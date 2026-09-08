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
import { workspaceDesktopTranslations } from './workspace-desktop';

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
    /*
        ÇEVİRİ ALAN ADI CİHAZ TANIMAZ (`docs/151`).

        Masaüstünün kendi dizeleri ayrı bir klasörde toplanır ve mobil
        pakete hiç inmez — ama bu bir PAKETLEME kararıdır, bir çeviri
        kararı değil. İki tablo burada birleşir, dolayısıyla PO/POT
        zinciri tek bir `workspace` alan adı görmeye devam eder ve
        çevirmen aynı ekranın cümlelerini iki dosyada aramaz.

        BU DOSYA TARAYICIYA İNMEZ: yalnız `scripts/i18n` onu derler
        (`SOURCE_ENTRY`). Masaüstü kataloğunu burada anmak, o kataloğu
        mobil pakete sokmaz — `scripts/adaptive-bundle-gate` bunu her
        koşuda doğrular.

        Çakışma: aynı anahtar iki tabloda birden olsaydı masaüstü tablosu
        ortağı sessizce ezerdi. `WorkspaceModuleCatalog` testi bunu
        yasaklar ve sayısıyla ölçer.
    */
    workspace: { ...workspaceTranslations, ...workspaceDesktopTranslations },
};

export const DOMAINS = Object.keys(DOMAIN_CATALOGS).sort();
