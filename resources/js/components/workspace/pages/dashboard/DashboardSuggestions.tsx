import { useState, type ReactNode } from 'react';
import { EyeSlash, MagnifyingGlass, X } from '@phosphor-icons/react';
import { t } from '../../../../i18n/dashboard';
import type { MenuInsights } from './useMenuInsights';

/**
 * Home'un öneri bölümü — kaynak `data-screen-label="Home"`, `docs/109` §6.1.
 *
 * Kaynağın kuralı bu bölümün TAMAMINI yönetir: **"Öneri yapar, sen
 * onaylarsın. Onaysız hiçbir şey değişmez."** Buradaki hiçbir düğme fiyat
 * değiştirmez, ürün gizlemez, kategori açmaz — sahibi kararı verebileceği
 * ekrana götürür ve karar orada verilir.
 *
 * BU BÖLÜM NEDEN "AI ÖNERİLERİ" DEĞİL?
 *
 * Kaynak ona öyle diyor ve bir kıvılcım simgesi koyuyor. Depoda bağlı bir AI
 * sağlayıcısı YOK (`lib/aiAssistState.ts` sabit `disconnected`). Bir modelin
 * yazmadığı cümleyi "AI önerisi" diye sunmak, yanlış bir yeteneği satmaktır:
 * sahip yarın "AI'a sor" der ve öyle bir şey olmadığını öğrenir.
 *
 * Oysa kaynağın üç örneğinin üçü de zaten ÖLÇÜMDEN çıkıyor — arama kaydı,
 * ürün görüntülenmesi, fotoğraf varlığı. Yani bölümün doğması için model
 * gerekmiyor; ölçüm yetiyor. Başlık bu yüzden ölçümü söylüyor, modeli değil.
 */

type SuggestionKind = 'search' | 'unviewed';

type Suggestion = {
    key: string;
    kind: SuggestionKind;
    title: string;
    why: string;
    cta: string;
    section: string;
};

/** Kaynağın satır sayısı. Bkz. aşağıdaki gerekçe. */
const MAX_SUGGESTIONS = 3;

const ICON: Record<SuggestionKind, ReactNode> = {
    search: <MagnifyingGlass aria-hidden="true" size={22} weight="regular" />,
    unviewed: <EyeSlash aria-hidden="true" size={22} weight="regular" />,
};

/**
 * Ölçümden öneriye.
 *
 * Sıra ÖNEMLİ: menüde olmayan bir talep (arama) her zaman, hiç bakılmayan bir
 * üründen önce gelir. Biri kaçırılmış bir satış, diğeri temizlenmemiş bir
 * liste — ve sabah beş dakikası olan bir restoran sahibi için bunlar aynı
 * aciliyette değildir.
 *
 * Liste ÜÇTE kesilir. Bir sabah on beş "hiç bakılmayan" ürün çıkarsa öneri
 * listesi ekranı yutar ve Home'un asıl işi ("şimdi ne yapmalıyım") gözden
 * kaybolur. Öneri bir gündem maddesidir, bir rapor değil; raporun yeri
 * analitik ekranıdır.
 */
function buildSuggestions(insights: MenuInsights | null): Suggestion[] {
    if (insights === null || insights.state !== 'ready') {
        return [];
    }

    const fromSearches: Suggestion[] = insights.searchesWithNoResults.map((search) => ({
        key: `search:${search.term}`,
        kind: 'search',
        /*
            `searches` alanı, adına rağmen KİŞİ sayar (uç
            `COUNT(DISTINCT visitor_key)` döndürüyor). Cümle bu yüzden "kez
            arandı" değil "kişi aradı" der; tekil ayrı anahtardır, çünkü
            "1 visitors" diye bir cümle yok.
        */
        title:
            search.searches === 1
                ? t('dashboard.suggestions.search.title', { term: search.term })
                : t('dashboard.suggestions.search.title.plural', {
                      term: search.term,
                      count: String(search.searches),
                  }),
        why: t('dashboard.suggestions.search.why'),
        cta: t('dashboard.suggestions.search.cta'),
        section: 'menu',
    }));

    const fromUnviewed: Suggestion[] = insights.neverViewed.map((row) => ({
        key: `unviewed:${row.menuItemId}`,
        kind: 'unviewed',
        title: t('dashboard.suggestions.unviewed.title', { name: row.productName }),
        why: t('dashboard.suggestions.unviewed.why'),
        cta: t('dashboard.suggestions.unviewed.cta'),
        section: 'menu',
    }));

    return [...fromSearches, ...fromUnviewed].slice(0, MAX_SUGGESTIONS);
}

type DashboardSuggestionsProps = {
    insights: MenuInsights | null;
    onNavigateToSection?: (section: string) => void;
};

export function DashboardSuggestions({ insights, onNavigateToSection }: DashboardSuggestionsProps) {
    /*
        Kapatma OTURUMLUKTUR ve bu bilinçli bir sınırdır. Sunucuda "bu öneriyi
        bir daha gösterme" diye bir kayıt yok; olmayan bir kalıcılığı varmış
        gibi davranmak, sahibin yarın aynı satırı görüp "kapatmıştım" demesine
        yol açardı. Kapatma bugünün gündeminden düşürür, tarihe yazmaz.
    */
    const [dismissed, setDismissed] = useState<string[]>([]);

    const suggestions = buildSuggestions(insights).filter((row) => !dismissed.includes(row.key));

    /*
        ÖLÇÜM YOKSA BÖLÜM YOK — `docs/109` §4.3.

        Boş bir öneri kutusu ("Bugün için 0 öneri") sahibe "baktım, önerecek
        bir şey bulamadım" der. Ölçüm hiç okunamadıysa doğrusu "daha
        bakmadım"dır ve ikisi farklı şeylerdir. Kutunun kendisi bir iddiadır.
    */
    if (suggestions.length === 0) {
        return null;
    }

    return (
        <section
            aria-label={t('dashboard.suggestions.region')}
            className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface"
        >
            <div className="flex flex-wrap items-center gap-[var(--space-3)] border-b border-border px-[var(--space-fluid-md)] py-[var(--space-4)]">
                <h2 className="text-body font-bold tracking-tight text-fg">
                    {suggestions.length === 1
                        ? t('dashboard.suggestions.heading', { count: '1' })
                        : t('dashboard.suggestions.heading.plural', {
                              count: String(suggestions.length),
                          })}
                </h2>
                {/*
                    Kaynağın DEĞİŞMEZ cümlesi başlığın yanında durur, dipnotta
                    değil: bir öneri listesine bakan kişinin ilk sorusu "bunlar
                    kendiliğinden mi uygulanıyor?"dur ve cevabı listeyi
                    okumadan görmeli.
                */}
                <p className="text-meta text-fg-muted">{t('dashboard.suggestions.rule')}</p>
            </div>

            <ul className="flex flex-col">
                {suggestions.map((row) => (
                    <li
                        key={row.key}
                        className="flex flex-wrap items-center gap-[var(--space-3)] border-t border-border px-[var(--space-fluid-md)] py-[var(--space-3)] first:border-t-0"
                    >
                        {/*
                            Simge, önerinin NEREDEN geldiğini söyler: büyüteç
                            arama kaydı, kapalı göz görüntülenme ölçümü. Süs
                            değil, kaynağın kısaltması.
                        */}
                        <span className="grid size-[2.5rem] shrink-0 place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-fg-secondary">
                            {ICON[row.kind]}
                        </span>

                        <span className="flex min-w-0 flex-1 flex-col gap-[var(--space-1)] text-start">
                            <span className="text-body font-medium text-fg">{row.title}</span>
                            {/*
                                GEREKÇE ZORUNLU. "Şunu yap" diyen bir satır,
                                neden dediğini söylemedikçe bir tahmindir.
                                Sahip menüsünü ancak ölçümü görünce değiştirir.
                            */}
                            <span className="text-meta text-fg-muted">{row.why}</span>
                        </span>

                        <span className="flex items-center gap-[var(--space-2)]">
                            {onNavigateToSection ? (
                                <button
                                    type="button"
                                    onClick={() => onNavigateToSection(row.section)}
                                    className="inline-flex min-h-[var(--control-height)] items-center justify-center rounded-[var(--radius-md)] bg-[var(--color-fg)] px-[var(--space-4)] text-meta font-bold text-[var(--color-surface)] transition-opacity duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                >
                                    {row.cta}
                                </button>
                            ) : null}
                            <button
                                type="button"
                                onClick={() => setDismissed((current) => [...current, row.key])}
                                className="inline-flex min-h-[var(--control-height)] min-w-[var(--control-height)] items-center justify-center rounded-[var(--radius-md)] text-fg-muted transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:bg-surface-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            >
                                <X aria-hidden="true" size={18} weight="bold" />
                                <span className="sr-only">
                                    {t('dashboard.suggestions.dismiss')}
                                </span>
                            </button>
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default DashboardSuggestions;
