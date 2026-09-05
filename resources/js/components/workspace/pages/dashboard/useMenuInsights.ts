import { useEffect, useState } from 'react';

/**
 * Home'un ölçüm kaynağı — `docs/109` §6.1.
 *
 * Kaynak ekranın "AI önerileri" ve "en çok bakılanlar" bölümlerinin ikisi de
 * AYNI olguya dayanır: hangi ürüne kaç FARKLI ziyaretçi baktı, hiç bakılmayan
 * hangisi, menüde olmayan ne arandı. Depoda bu ölçümün tek sahibi
 * `/analytics/menu-engineering` ucudur (`docs/84`).
 *
 * Bu yüzden iki bölüm iki ayrı istek atmaz: ölçüm bir kez okunur, iki bölüm
 * onu paylaşır. Aksi hâlde aynı sayfada aynı sorunun iki cevabı olabilirdi —
 * iki istek arasına giren bir görüntülenme, öneriyi tabloyla çelişir hâle
 * getirirdi ve sahip hangisine inanacağını bilemezdi.
 */

export type MenuInsightsRow = {
    menuItemId: number;
    productName: string;
    categoryName: string;
    viewers: number;
};

export type MenuInsightsSearch = { term: string; searches: number };

export type MenuInsights = {
    /**
     * Uç, eşiğin altındaki ölçümü `not_enough_data` diye işaretler ve SAYI
     * VERMEZ: üç ziyaretçinin baktığı bir ürünü "en çok bakılan" diye sunmak,
     * sahibi gürültüye göre menü düzenlettirirdi. Arayüz o kararı ezmez.
     */
    state: 'ready' | 'not_enough_data';
    mostViewed: MenuInsightsRow[];
    neverViewed: MenuInsightsRow[];
    searchesWithNoResults: MenuInsightsSearch[];
};

/**
 * ARALIK NEDEN 30 GÜN?
 *
 * Kaynak ekran "Bugün en çok bakılanlar" diyor. Ama uç, rapor için en az beş
 * FARKLI ziyaretçi şart koşuyor (`ShowMenuEngineeringController`): tek bir
 * günün ölçümü küçük bir restoranda bu eşiği çoğu sabah geçmez ve bölüm her
 * gün "veri yok" diye kapalı kalırdı. Var olduğu hâlde hiç görünmeyen bir
 * bölüm, hiç yazılmamış bir bölümle aynı şeydir.
 *
 * Aralık bu yüzden 30 gün ve ekranda AÇIKÇA yazar. Ölçülen aralığı
 * söylemeyen bir tablo, okuyanın "bugün" sandığı bir rakam üretir.
 */
export const MENU_INSIGHTS_RANGE = '30d';

function rows(value: unknown): MenuInsightsRow[] {
    return Array.isArray(value) ? (value as MenuInsightsRow[]) : [];
}

/**
 * Yanıt şekline körü körüne güvenilmez: araya giren bir vekil, eski bir
 * önbellek sürümü ya da 200 dönen bir hata sayfası beklenmedik bir gövde
 * verebilir. Eksik bir alan Home'un TAMAMINI çökertmemeli.
 */
function normalize(body: Partial<MenuInsights>): MenuInsights {
    return {
        state: body.state === 'ready' ? 'ready' : 'not_enough_data',
        mostViewed: rows(body.mostViewed),
        neverViewed: rows(body.neverViewed),
        searchesWithNoResults: Array.isArray(body.searchesWithNoResults)
            ? body.searchesWithNoResults
            : [],
    };
}

/**
 * Ölçüm okunamadıysa `null` döner — SIFIR DEĞİL.
 *
 * Sıfır "baktım, bir şey yok" der; `null` "daha bakmadım" der. Bu ikisini
 * karıştırmak, çağıranı boş bir öneri kutusu çizmeye iter ve o kutu sahibe
 * olmayan bir ölçümün varlığını iddia eder.
 */
export function useMenuInsights(workspaceId?: number): MenuInsights | null {
    /*
        Ölçüm, HANGİ çalışma alanına ait olduğuyla birlikte saklanır.

        Sadece veriyi saklamak bir sızıntıya izin verirdi: sahip üst çubuktan
        ikinci işletmesine geçtiğinde, yeni yanıt gelene kadar ekranda BİRİNCİ
        işletmenin önerileri kalırdı — "Vejetaryen 14 kez arandı" cümlesi
        yanlış restoranın altında. Kimliği veriyle birlikte tutmak, o birkaç
        yüz milisaniyeyi kapatır: eşleşmeyen ölçüm hiç gösterilmez.
    */
    const [measured, setMeasured] = useState<{
        workspaceId: number;
        insights: MenuInsights;
    } | null>(null);

    useEffect(() => {
        if (!workspaceId) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/analytics/menu-engineering?range=${MENU_INSIGHTS_RANGE}`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (cancelled) return;

                /*
                    404 (yetki yok) ve 402 (plan kapalı) burada HATA DEĞİL,
                    yalnız "bu kullanıcı için ölçüm yok"tur. Mutfak rolündeki
                    biri analitiği görmez; ona bir hata uyarısı göstermek,
                    yapabileceği bir şey olmadığı hâlde ekranı meşgul ederdi.
                */
                if (!response.ok) {
                    return;
                }

                const body = (await response.json()) as Partial<MenuInsights>;

                if (cancelled) return;

                setMeasured({ workspaceId, insights: normalize(body) });
            } catch {
                /*
                    Başarısızlıkta HİÇBİR ŞEY yazılmaz: `null` zaten "daha
                    bakmadım" demektir ve ağ hatası da tam olarak odur.
                */
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    return measured !== null && measured.workspaceId === workspaceId ? measured.insights : null;
}

export default useMenuInsights;
