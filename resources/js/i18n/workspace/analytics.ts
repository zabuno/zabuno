export const analytics = {
    'workspace.analytics.heading': 'Analytics',
    // MENÜ MÜHENDİSLİĞİ (`docs/84`, P1-08). "Menün 214 kez açıldı" menüyü
    // DEĞİŞTİRMEK için hiçbir şey söylemez.
    'workspace.analytics.menuEngineering.region': 'What works on your menu',
    'workspace.analytics.menuEngineering.title': 'What works on your menu',
    'workspace.analytics.menuEngineering.loading': 'Loading menu figures…',
    'workspace.analytics.menuEngineering.error': 'Menu figures could not be loaded.',
    // Eşik ve gözlenen sayı AÇIKÇA yazılır: kaç ziyaretçi gerektiğini
    // bilmeyen biri, ne kadar bekleyeceğini de bilemez (`docs/66`).
    'workspace.analytics.menuEngineering.thin':
        'Not enough visitors yet to rank your dishes: {observed} of {threshold}. Keep the menu published and check back.',
    'workspace.analytics.menuEngineering.mostViewed': 'Most looked at',
    'workspace.analytics.menuEngineering.neverViewed': 'Never looked at',
    'workspace.analytics.menuEngineering.neverViewed.none': 'Every dish was looked at.',
    'workspace.analytics.menuEngineering.viewers': '{count} visitors',
    'workspace.analytics.menuEngineering.searches': 'Searched for, not found',
    'workspace.analytics.menuEngineering.searches.none':
        'No one searched for something your menu does not have.',
    'workspace.analytics.menuEngineering.searchCount': '{count} visitors',
    'workspace.analytics.range.label': 'Range',
    'workspace.analytics.range.today': 'Today',
    'workspace.analytics.range.7d': 'Last 7 days',
    'workspace.analytics.range.30d': 'Last 30 days',
    'workspace.analytics.report.region': 'Analytics report',
    'workspace.analytics.report.loading': 'Loading analytics…',
    'workspace.analytics.report.error': 'Analytics failed to load. Please try again.',
    'workspace.analytics.report.unavailable':
        'Analytics reporting is not available yet; no metrics have been generated.',
    'workspace.analytics.metric.qrResolve': 'QR Resolve',
    'workspace.analytics.metric.menuOpen': 'Confirmed Menu Open',
    'workspace.analytics.operational.description':
        'Review real QR resolve and confirmed menu open counts for the selected location and range.',
    'workspace.analytics.status.notConnected': 'Not requested yet',
    'workspace.analytics.status.loading': 'Loading',
    'workspace.analytics.status.error': 'Failed to load',
    'workspace.analytics.action.refresh': 'Refresh',
    'workspace.analytics.action.retry': 'Retry',
    // PLAN cevabı, hata DEĞİL. Ayrı tutulmasının sebebi somut: "tekrar
    // deneyin" demek ve bir Retry düğmesi koymak, hiçbir zaman işe
    // yaramayacak bir yol göstermektir (`docs/44` engellenmiş durum
    // standardı: her blocked state nedenini VE çözümünü söyler).
    'workspace.analytics.status.planRestricted': 'Not in your plan',
    'workspace.analytics.report.planRestricted':
        'Analytics reporting is not included in your current plan. Your QR scans are still being recorded, so no data is lost — the reports appear as soon as the plan includes them.',
    'workspace.analytics.action.viewPlan': 'View your plan',

    // --- ZAMAN SERİSİ (`docs/109` §1 Insights, §6.5) ----------------------
    // Aralık TOPLAMI bir haftanın şeklini gizliyordu: hangi gün çöktü,
    // hangi saatte yoğunlaştı, geçen haftaya göre nasıl, hangi şube
    // çekiyor. Kaynağın çubuk+çizgi grafiği, saat ısı haritası ve şube
    // halkası bu metinlerle konuşur.
    'workspace.analytics.description':
        'What guests look at, and what they look for and cannot find.',
    'workspace.analytics.timeSeries.region': 'Traffic over time',
    'workspace.analytics.timeSeries.loading': 'Loading the charts…',
    'workspace.analytics.timeSeries.error': 'The charts could not be loaded.',
    // Eşik ve gözlenen sayı AÇIKÇA yazılır — menü mühendisliğiyle aynı
    // kelimeler, çünkü ikisi aynı ekranda yan yana duruyor ve farklı
    // kelimelerle aynı şeyi söylemek iki ayrı kural olduğunu düşündürür.
    'workspace.analytics.timeSeries.thin':
        'Not enough visitors yet to chart your traffic: {observed} of {threshold}. Keep the menu published and check back.',
    'workspace.analytics.timeSeries.trend.title': 'Scans and menu opens',
    'workspace.analytics.timeSeries.trend.description': 'Scans and menu opens per day',
    'workspace.analytics.timeSeries.trend.column': 'Day',
    'workspace.analytics.timeSeries.heat.title': 'Busiest hours',
    'workspace.analytics.timeSeries.heat.description': 'Scans by weekday and hour ({timezone})',
    'workspace.analytics.timeSeries.heat.column': 'Weekday',
    'workspace.analytics.timeSeries.heat.withheld': 'withheld',
    // Gizleme SESSİZ olmaz: ekran "o saatte kimse yoktu" derse bu yanlıştır.
    'workspace.analytics.timeSeries.heat.withheldNote':
        '{count} hour slots are not shown: a single visitor would be identifiable in them.',
    'workspace.analytics.timeSeries.share.title': 'Share by location',
    'workspace.analytics.timeSeries.share.description': 'Scans per location',
    // Pay HER ZAMAN markanın tamamından okunur; süzülmüş bir halka her
    // zaman %100 çizilir ve hiçbir şey söylemez.
    'workspace.analytics.timeSeries.share.scope':
        'Across your whole brand, not only the selected location.',
    'workspace.analytics.timeSeries.tables.title': 'Busiest tables',
    'workspace.analytics.timeSeries.tables.value': 'scans',
    'workspace.analytics.timeSeries.scans': 'Scans',
    'workspace.analytics.timeSeries.menuOpens': 'Menu opens',

    // Karşılaştırma. Sıfırdan yüzde artış YOKTUR: bölen sıfırken oran
    // uydurulmaz, "karşılaştıracak bir şey yok" denir.
    'workspace.analytics.compare.basis.previousPeriod': 'the period before',
    'workspace.analytics.compare.basis.sameWeekdayLastWeek': 'the same day last week',
    'workspace.analytics.compare.up': 'Up {percent}% on {basis}',
    'workspace.analytics.compare.down': 'Down {percent}% on {basis}',
    'workspace.analytics.compare.flat': 'Level with {basis}',
    'workspace.analytics.compare.noBaseline': 'Nothing to compare: {basis} had no scans',

    // `metric.uniqueVisitors` ve `metric.openRate` `shell.ts` içinde
    // tanımlı; aynı anahtarı iki modülde tanımlamak katalog kurucusunda
    // hataya düşer (`i18n/workspace.ts`).
    'workspace.analytics.metric.searches': 'Searches',
    'workspace.analytics.metric.searches.support': '{count} found nothing',

    // "Bu 7 günde ne oldu?" — kaynağın üst kartı. Cümleler ÖLÇÜMDEN doğar;
    // sağlayıcı bağlı olmasa da üretilebilirler (`docs/109` §6.1).
    'workspace.analytics.highlight.title': 'What happened in this range?',
    'workspace.analytics.highlight.busiest': '{day} around {hour} was the busiest slot.',
    'workspace.analytics.highlight.missing':
        '“{term}” was searched {count} times and returned nothing — that may be a gap in your menu.',
    'workspace.analytics.highlight.neverViewed': '{name} was not looked at once in this range.',
    'workspace.analytics.highlight.action.addTerm': 'Add “{term}” to the menu',
    'workspace.analytics.highlight.action.editMenu': 'Review the menu',

    'workspace.analytics.menuEngineering.searches.add': 'Add',
    'workspace.analytics.menuEngineering.searches.addFor': 'Add “{term}” to the menu',

    'workspace.analytics.weekday.1': 'Mon',
    'workspace.analytics.weekday.2': 'Tue',
    'workspace.analytics.weekday.3': 'Wed',
    'workspace.analytics.weekday.4': 'Thu',
    'workspace.analytics.weekday.5': 'Fri',
    'workspace.analytics.weekday.6': 'Sat',
    'workspace.analytics.weekday.7': 'Sun',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof analytics, string> {}
}
