import type { ReactNode } from 'react';
import type { MediaFolder, MediaFolderId } from './MediaFolderRail';
import type { MediaAsset, MediaLibraryActions } from '../MediaPage';

export type MediaLibraryLoadState = 'loading' | 'idle' | 'error';

/**
 * KÜTÜPHANENİN CİHAZA ÖZGÜ YÜZEYİ — `docs/153` §4-5.
 *
 * `queueSurface.ts` ile aynı desen ve aynı sebep: paylaşılan sayfa "bir
 * kütüphane çizicisi alırım" der, ama MASAÜSTÜ çiziciyi ADIYLA ANMAZ.
 * Kabuk cihaza özgü bir dosyayı adlandırdığı anda o dosyanın adı paylaşılan
 * pakette geçer ve ayrımın doğruluğu tek bir `type` kelimesine kalırdı.
 *
 * `undefined` gelmesi bir eksiklik değil, TELEFONUN NORMAL HÂLİDİR: mobil
 * giriş noktası bu çiziciyi hiç vermez, masaüstü ızgarasının kodu o pakete
 * hiç inmez ve ekran bugünkü dokunmatik listeyi çizer — taban odur
 * (TOUCH-FIRST-INTERFACE §1).
 *
 * ## Kuyruktan bir FARK var ve bilerek
 *
 * Sipariş kuyruğunun bağlamında VERİ YOKTUR: iki yüzey de `useOrderFeed`
 * ile aynı yerden okur. Burada dosya listesi bağlamla GEÇER, çünkü aynı
 * liste sayfada beş bölüm tarafından daha okunuyor (toplu işlem,
 * görüntüle, klasör sayaçları). Kütüphane kendi okumasını yapsaydı sahip
 * bir dosyayı silince öteki bölümler onu bir süre daha listelerdi — ve
 * hangi listenin doğru olduğu sorusu ekranda cevapsız kalırdı.
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
