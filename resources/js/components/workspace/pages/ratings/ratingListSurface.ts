import type { ReactNode } from 'react';

import type { RatingRow } from './ratingPresentation';

/**
 * PUAN LİSTESİNİN CİHAZA ÖZGÜ YÜZEYİ — `docs/151` §B, `docs/153` §5.
 *
 * Sayfanın karar verdiği her şey (izin, ön koşul, yükleme, hata, boş liste
 * ve "puan kaldırılamaz" cümlesi) PAYLAŞILANdIR ve buraya gelmez. Yüzeye
 * geçen tek şey, sayfa okumayı BİTİRDİKTEN sonra elinde kalan listedir.
 *
 * Ayrım bu yüzden dar: masaüstünde değişen şey listenin nasıl TARANDIĞI ve
 * yanıtın nerede yazıldığıdır — hangi ürünün puanının gösterileceği değil.
 * O karar sunucudan gelir ve iki yüzey de `ratingPresentation` üzerinden
 * aynı cümleyi okur.
 */
export type RatingListSurfaceContext = {
    workspaceId: number;
    rows: RatingRow[];
    /** Sunucu söylemediyse `null` — ekran sürüm UYDURMAZ. */
    algorithmVersion: string | null;
    /** Yanıt kaydedildiğinde sayfanın listesini günceller. */
    onReplySaved: (productId: number, body: string | null) => void;
};

export type RatingListSurfaceRenderer = (context: RatingListSurfaceContext) => ReactNode;
