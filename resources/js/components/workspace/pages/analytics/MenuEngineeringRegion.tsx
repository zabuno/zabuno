import { type ReactNode } from 'react';
import { Button } from 'flowbite-react';

import { t } from '../../../../i18n/workspace';
import { PanelCard } from '../shared/PanelCard';
import { useMenuEngineering, type MenuEngineeringSource } from './useMenuEngineering';

/*
    SATIRIN ORTAK RİTMİ (FF-131, teslim paketi "Kart grameri": tek kart,
    içinde İNCE AYRAÇLI satırlar).

    Yükseklik yoğunluk jetonundan gelir — sahip Ayarlar'dan "Sıkı / Standart /
    Ferah" seçtiğinde bu liste de onunla değişmeli. Yatay dolgu kart
    başlığınınkiyle aynı (`--space-5`), yoksa ad sütunu başlığa göre içeri
    kayar ve kart "iki farklı ızgaradan yapılmış" görünür.

    Ayraç ÜSTE konur ve grup başlığının hemen altındaki ilk satırda
    susturulur: başlık zaten ayırıcıdır, üstüne bir de çizgi konduğunda
    başlık kendi listesinden kopmuş görünür.
*/
const ROW =
    'flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border px-[var(--space-5)] py-[var(--space-2)] text-body text-fg-secondary first:border-t-0';

/*
    Grup başlığı: 700 ağırlık, cümle düzeni, soluk ton. AEP yalnız
    400/500/700 yayınlıyor; 600 Roboto'da ayrı kesim olarak yüklenmediği
    için tarayıcı tarafından SENTEZLENİYOR ve harf biçimleri bozuluyordu.
*/
const GROUP_LABEL =
    'px-[var(--space-5)] pt-[var(--space-4)] pb-[var(--space-2)] text-meta font-bold text-fg-muted';

const QUIET = 'px-[var(--space-5)] py-[var(--space-3)] text-body text-fg-muted';

export type MenuEngineeringRow = {
    menuItemId: number;
    productName: string;
    categoryName: string;
    viewers: number;
};

export type MenuEngineeringSearch = { term: string; searches: number };

export type MenuEngineeringReport = {
    state: 'ready' | 'not_enough_data';
    threshold: number;
    observedViewers: number;
    mostViewed: MenuEngineeringRow[];
    neverViewed: MenuEngineeringRow[];
    searchesWithNoResults: MenuEngineeringSearch[];
};

type MenuEngineeringRegionProps = {
    workspaceId: number;
    range: string;
    /**
     * Rapor DIŞARIDAN verilebilir.
     *
     * Aynı rapor ekranın üstündeki "bu aralıkta ne oldu?" kartında da
     * okunuyor; iki ayrı istek atmak aynı sayıyı iki kez indirmek ve iki
     * listenin ayrışabilmesi demekti. Verilmediğinde bileşen kendi isteğini
     * atmaya devam eder.
     */
    source?: MenuEngineeringSource;
    /**
     * "Ekle" düğmesinin gerçek hedefi. Yoksa düğme HİÇ çizilmez: basıldığında
     * hiçbir şey yapmayan bir düğme, kullanıcıya olmayan bir yol göstermektir.
     */
    onAddTerm?: (term: string) => void;
};

/**
 * "Menümde ne işe yarıyor?" — `docs/84` (P1-08).
 *
 * Bugüne kadarki cevap "menün 214 kez açıldı"ydı ve bu, menüyü DEĞİŞTİRMEK
 * için hiçbir şey söylemez: hangi ürünü büyütmeli, hangisini listeden
 * çıkarmalı, hangi talebi karşılamıyorum?
 */
export function MenuEngineeringRegion({
    workspaceId,
    range,
    source,
    onAddTerm,
}: MenuEngineeringRegionProps) {
    /*
        Rapor DIŞARIDAN geldiyse kendi isteğimizi atmayız: `workspaceId`
        tanımsız verildiğinde kanca hiçbir şey çekmez. Aynı sayıyı iki kez
        indirmek, iki listenin ayrışabilmesi demekti — ve ekranın üstündeki
        özet kartı ile alttaki liste farklı sayılar söylerdi.
    */
    const fetched = useMenuEngineering(source === undefined ? workspaceId : undefined, range);
    const { report, failed } = source ?? fetched;

    if (failed) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.analytics.menuEngineering.error')}
            </p>
        );
    }

    if (report === null) {
        return (
            <p role="status" className="text-body text-fg-muted">
                {t('workspace.analytics.menuEngineering.loading')}
            </p>
        );
    }

    if (report.state === 'not_enough_data') {
        return (
            <Region>
                <PanelCard title={t('workspace.analytics.menuEngineering.title')}>
                    {/*
                        Boş bir tablo, sahibe "ürünüm bozuk" dedirtir.

                        Sebep ve EŞİK açıkça yazılır (`docs/66` disiplini): kaç
                        ziyaretçi gerektiğini bilmeyen biri, ne kadar
                        bekleyeceğini de bilemez.
                    */}
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.analytics.menuEngineering.thin', {
                            observed: String(report.observedViewers),
                            threshold: String(report.threshold),
                        })}
                    </p>
                </PanelCard>
            </Region>
        );
    }

    return (
        <Region>
            {/*
                İKİ KARAR, İKİ KART (FF-131, teslim paketi §5).

                "Levrek'e 8 kişi baktı" ile "4 kişi karides güveç arayıp
                bulamadı" aynı listede alt alta duruyordu. Oysa ilki menüde
                VAR OLAN bir ürünle, ikincisi menüde HİÇ OLMAYAN bir taleple
                ilgili — ve sahip ikisini karıştırdığında, ürünü büyütmesi
                gereken yerde yenisini ekliyor.

                `padded={false}`: dolguyu satırlar yönetir; kartın kendi
                dolgusu olsaydı ayraçlar kenarlığa ulaşmaz, çizgiler havada
                asılı kalırdı.
            */}
            <PanelCard
                title={t('workspace.analytics.menuEngineering.title')}
                padded={false}
                className="overflow-hidden"
            >
                <h3 className={GROUP_LABEL}>
                    {t('workspace.analytics.menuEngineering.mostViewed')}
                </h3>
                <ol className="flex flex-col">
                    {report.mostViewed.map((row) => (
                        <li key={row.menuItemId} className={ROW}>
                            <span className="font-medium text-fg">{row.productName}</span>
                            <span className="text-fg-muted">{row.categoryName}</span>
                            {/*
                                Sayı satırın SONUNA yaslanır (`ms-auto`) ve
                                `tabular-nums` taşır: aksi hâlde sayı ürün
                                adının uzunluğuna göre sağa sola kayar, göz
                                her satırda onu yeniden aramak zorunda kalır
                                ve karşılaştırma yapılamaz.
                            */}
                            <span className="ms-auto tabular-nums">
                                {t('workspace.analytics.menuEngineering.viewers', {
                                    count: String(row.viewers),
                                })}
                            </span>
                        </li>
                    ))}
                </ol>

                <h3 className={`${GROUP_LABEL} border-t border-border`}>
                    {t('workspace.analytics.menuEngineering.neverViewed')}
                </h3>
                {report.neverViewed.length === 0 ? (
                    <p className={QUIET}>
                        {t('workspace.analytics.menuEngineering.neverViewed.none')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {report.neverViewed.map((row) => (
                            <li key={row.menuItemId} className={ROW}>
                                <span className="font-medium text-fg">{row.productName}</span>
                                <span className="text-fg-muted">{row.categoryName}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </PanelCard>

            <PanelCard
                title={t('workspace.analytics.menuEngineering.searches')}
                padded={false}
                className="overflow-hidden"
            >
                {report.searchesWithNoResults.length === 0 ? (
                    <p className={QUIET}>
                        {t('workspace.analytics.menuEngineering.searches.none')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {report.searchesWithNoResults.map((row) => (
                            <li key={row.term} className={ROW}>
                                <span className="font-medium text-fg">{row.term}</span>
                                <span className="ms-auto tabular-nums">
                                    {t('workspace.analytics.menuEngineering.searchCount', {
                                        count: String(row.searches),
                                    })}
                                </span>
                                {/*
                                    "EKLE" GERÇEKTEN BİR ŞEY YAPAR.

                                    Bu listenin tek işi bir BOŞLUĞU
                                    göstermek: menüde olmayan ama istenen
                                    şey. Boşluğu görüp onu kapatmak için
                                    ekranı terk etmek, sahibin aklındaki
                                    terimi de yolda bırakır. Düğme menü
                                    ekranına götürür.

                                    `onAddTerm` yoksa düğme HİÇ çizilmez:
                                    basıldığında hiçbir şey yapmayan bir
                                    düğme, kullanıcıya olmayan bir yol
                                    göstermektir.
                                */}
                                {onAddTerm ? (
                                    <Button
                                        size="xs"
                                        color="light"
                                        onClick={() => {
                                            onAddTerm(row.term);
                                        }}
                                    >
                                        {t('workspace.analytics.menuEngineering.searches.add')}
                                        {/*
                                            Görünen etiket kısa, erişilebilir
                                            adı TAM: bir ekran okuyucu
                                            kullanıcısı düğme listesinde beş
                                            tane "Add" görürse hangisinin
                                            hangi terime ait olduğunu bilemez.
                                        */}
                                        <span className="sr-only">
                                            {` ${t('workspace.analytics.menuEngineering.searches.addFor', { term: row.term })}`}
                                        </span>
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </PanelCard>
        </Region>
    );
}

/**
 * İki kartı tek bir isimli bölgede toplar.
 *
 * Bölge adı korunur: ekran okuyucu kullanıcısı "menümde ne işe yarıyor"
 * bölgesine landmark listesinden atlayabilmeli; iki kartı gövdeye serbest
 * bırakmak o atlama noktasını yok ederdi.
 */
function Region({ children }: { children: ReactNode }) {
    return (
        <section
            aria-label={t('workspace.analytics.menuEngineering.region')}
            className="flex flex-col gap-[var(--space-fluid-md)]"
        >
            {children}
        </section>
    );
}

export default MenuEngineeringRegion;
