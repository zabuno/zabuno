import { Sparkle } from '@phosphor-icons/react';
import { Button } from 'flowbite-react';

import { t } from '../../../../i18n/workspace';
import { PanelCard } from '../shared/PanelCard';
import type { MenuEngineeringReport } from './MenuEngineeringRegion';
import type { AnalyticsTimeSeries } from './useAnalyticsTimeSeries';

export type InsightsHighlightProps = {
    series: AnalyticsTimeSeries;
    report: MenuEngineeringReport | null;
    /** Yoksa hiçbir düğme çizilmez — gidilecek yer yoksa yol da gösterilmez. */
    onNavigateToSection?: (section: string) => void;
};

/** Karşılaştırma cümlesi. Önceki pencere boşsa yüzde UYDURULMAZ. */
function comparisonSentence(series: AnalyticsTimeSeries): string | null {
    const comparison = series.comparison;

    if (comparison === null) {
        return null;
    }

    const basis =
        comparison.basis === 'same_weekday_last_week'
            ? t('workspace.analytics.compare.basis.sameWeekdayLastWeek')
            : t('workspace.analytics.compare.basis.previousPeriod');

    if (comparison.deltaRatio === null) {
        /*
            Sıfırdan yüzde artış YOKTUR: bölen sıfırdır. "%100 arttı" demek
            matematiksel olarak uydurmadır ve sahibin bir sonraki kararına
            temel olur.
        */
        return t('workspace.analytics.compare.noBaseline', { basis });
    }

    const percent = Math.round(Math.abs(comparison.deltaRatio) * 100);

    if (percent === 0) {
        return t('workspace.analytics.compare.flat', { basis });
    }

    return comparison.deltaRatio > 0
        ? t('workspace.analytics.compare.up', { percent: String(percent), basis })
        : t('workspace.analytics.compare.down', { percent: String(percent), basis });
}

/** En yoğun gün ve saat — sahibin vardiya kararını taşıyan tek cümle. */
function busiestSentence(series: AnalyticsTimeSeries): string | null {
    if (series.hourly.length === 0) {
        return null;
    }

    const busiest = series.hourly.reduce((best, cell) =>
        cell.qrResolveCount > best.qrResolveCount ? cell : best,
    );

    if (busiest.qrResolveCount === 0) {
        return null;
    }

    return t('workspace.analytics.highlight.busiest', {
        day: t(
            `workspace.analytics.weekday.${String(busiest.weekday)}` as 'workspace.analytics.weekday.1',
        ),
        hour: `${String(busiest.hour).padStart(2, '0')}:00`,
    });
}

/**
 * "Bu aralıkta ne oldu?" — `docs/109` §1 (Insights), §6.1.
 *
 * Kaynak bu kartı "AI yorumu" diye adlandırıyor ama içindeki cümlelerin
 * hepsi GERÇEK ÖLÇÜMDEN doğuyor: saat kırılımı, arama kayıtları, ürün
 * görüntülenmesi. Yani bunlar "AI" değil, ölçümden çıkan gözlemler —
 * sağlayıcı bağlı olmasa da üretilebilirler ve uydurulmuş tek bir cümle
 * içermezler.
 *
 * Kaynağın değişmez kuralı burada da geçerli: *"Öneri yapar, sen
 * onaylarsın. Onaysız hiçbir şey değişmez."* Düğmeler hiçbir şeyi
 * uygulamaz; sahibi kararı vereceği ekrana götürür.
 */
export function InsightsHighlight({ series, report, onNavigateToSection }: InsightsHighlightProps) {
    const topMissing = report?.searchesWithNoResults[0] ?? null;
    const firstNeverViewed = report?.neverViewed[0] ?? null;

    const sentences = [
        comparisonSentence(series),
        busiestSentence(series),
        topMissing === null
            ? null
            : t('workspace.analytics.highlight.missing', {
                  term: topMissing.term,
                  count: String(topMissing.searches),
              }),
        firstNeverViewed === null
            ? null
            : t('workspace.analytics.highlight.neverViewed', {
                  name: firstNeverViewed.productName,
              }),
    ].filter((sentence): sentence is string => sentence !== null);

    if (sentences.length === 0) {
        /*
            Boş bir "ne oldu?" kartı, her açılışta okunup hiçbir şey
            söylemeyen bir çerçeveye dönüşür ve altındaki gerçek bilgiyi
            aşağı iter.
        */
        return null;
    }

    return (
        <PanelCard title={t('workspace.analytics.highlight.title')}>
            <div className="flex flex-wrap items-start gap-[var(--space-4)]">
                {/*
                    İkon DEKORATİFTİR: yanındaki başlık zaten kartın ne
                    olduğunu söylüyor. Emoji kullanılmaz, Phosphor kullanılır.
                */}
                <span
                    aria-hidden="true"
                    className="flex size-[var(--control-height)] shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-surface-subtle text-fg-warning"
                >
                    <Sparkle size={22} weight="fill" />
                </span>

                <div className="flex min-w-[14rem] flex-1 flex-col gap-[var(--space-2)]">
                    <ul className="flex flex-col gap-[var(--space-1)] text-body text-fg-secondary">
                        {sentences.map((sentence) => (
                            <li key={sentence}>{sentence}</li>
                        ))}
                    </ul>

                    {/*
                        Düğme GERÇEKTEN bir yere götürür. `onNavigateToSection`
                        yoksa hiç çizilmez: basıldığında hiçbir şey yapmayan
                        bir düğme, kullanıcıya olmayan bir yol göstermektir.
                    */}
                    {onNavigateToSection ? (
                        <div className="flex flex-wrap gap-[var(--space-2)]">
                            {topMissing ? (
                                <Button
                                    size="xs"
                                    color="light"
                                    onClick={() => {
                                        onNavigateToSection('menu');
                                    }}
                                >
                                    {t('workspace.analytics.highlight.action.addTerm', {
                                        term: topMissing.term,
                                    })}
                                </Button>
                            ) : null}
                            {firstNeverViewed ? (
                                <Button
                                    size="xs"
                                    color="light"
                                    onClick={() => {
                                        onNavigateToSection('menu');
                                    }}
                                >
                                    {t('workspace.analytics.highlight.action.editMenu')}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </PanelCard>
    );
}

export default InsightsHighlight;
