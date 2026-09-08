import type { ReactNode } from 'react';

import type { MediaAsset, MediaLibraryActions } from '../MediaPage';
import type { MediaFolder, MediaFolderId } from './MediaFolderRail';

export type MediaLibraryLoadState = 'loading' | 'idle' | 'error';

/**
 * KÜTÜPHANENİN CİHAZA ÖZGÜ YÜZEYİ — `docs/151`, `docs/153` §5.
 *
 * `queueSurface.ts` ile aynı desen ve aynı sebep: paylaşılan sayfa "bir
 * kütüphane çizicisi alırım" der ama MASAÜSTÜ çiziciyi ADIYLA ANMAZ.
 * Andığı anda o ad paylaşılan pakete girer ve ayrımın doğruluğu tek bir
 * `type` kelimesine kalır.
 *
 * `undefined` gelmesi bir eksiklik değil, TELEFONUN NORMAL HÂLİDİR: mobil
 * giriş bu çiziciyi hiç vermez, masaüstü ızgarasının kodu o pakete hiç
 * inmez ve ekran bugünkü dokunmatik listeyi çizer.
 *
 * ── Bağlamda ne var, ne yok ───────────────────────────────────────────
 *
 * VAR: sayfanın zaten OKUMUŞ olduğu veri ve sayfanın sahip olduğu yazma
 * yolları. Bunlar iki yüzeyde de aynıdır ve tek bir yerden gelir.
 *
 * YOK: ikinci bir okuma yolu. Masaüstü ızgarası `fetch` çağırmaz; kendi
 * listesini kendisi çekseydi aynı klasörde iki farklı sayı gösteren iki
 * ekran doğardı (`docs/153` §4).
 */
export type MediaLibrarySurfaceContext = {
    assets: MediaAsset[];
    onDelete: (id: number) => void;
    loadState: MediaLibraryLoadState;
    onRetry?: () => void;
    pendingDeleteIds?: Set<number>;
    deleteErrorIds?: Set<number>;
    deleteNotice?: string | null;
    /**
     * Kütüphane eylemleri (kullanım, sürüm, çöp). Verilmezse yüzey yalnız
     * listeler ve siler — bileşen tek başına da çalışır.
     */
    actions?: MediaLibraryActions;
    trashRetentionDays?: number;
    /**
     * Arama KABUKTAN gelebilir (`MediaManagerShell`). Verildiğinde yüzey
     * kendi arama kutusunu çizmez: aynı ekranda iki arama alanı, hangisinin
     * geçerli olduğunu belirsizleştirir.
     */
    query?: string;
    /** Klasörler — boşsa hap şeridi hiç çizilmez. */
    folders?: MediaFolder[];
    activeFolderId?: MediaFolderId | null;
    onFolderChange?: (id: MediaFolderId | null) => void;
};

export type MediaLibrarySurfaceRenderer = (context: MediaLibrarySurfaceContext) => ReactNode;
