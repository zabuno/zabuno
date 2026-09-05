/**
 * Bir kırılım satırı — `docs/68`.
 *
 * Toplam sayı, iki şubesi olan bir işletmede birinin HİÇ taranmadığını
 * gizler; kırılım o gizlenen şeyi görünür kılar. Tip ayrı bir dosyada durur
 * çünkü onu hem özeti çeken sayfa hem de "masaya göre ilk 5" listesini
 * çizen bölge okuyor — birinin diğerinden tip ithal etmesi, iki bileşen
 * arasında yalnız tip taşımak için bir bağ kurardı.
 */
export type AnalyticsBreakdownRow = {
    id: number;
    label: string;
    qrResolveCount: number;
    menuOpenCount: number;
};
