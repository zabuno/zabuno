import type { ReactNode } from 'react';

/**
 * GARSON KUYRUĞUNUN CİHAZA ÖZGÜ YÜZEYİ — `docs/153`, `docs/54`.
 *
 * `kitchenSurface.ts` ile aynı desen ve aynı sebep: paylaşılan kod "bir
 * kuyruk çizicisi alırım" der, ama MASAÜSTÜ çiziciyi ADIYLA ANMAZ. Kabuk
 * cihaza özgü bir dosyayı adlandırdığı anda o dosyanın adı paylaşılan
 * pakette geçer ve ayrımın doğruluğu tek bir `type` kelimesine kalırdı.
 *
 * `undefined` gelmesi bir eksiklik değil, TELEFONUN NORMAL HÂLİDİR: mobil
 * giriş noktası bu çiziciyi hiç vermez, masaüstü kuyruğunun kodu o pakete
 * hiç inmez ve ekran bugünkü dokunmatik kuyruğu çizer.
 *
 * Bağlamda VERİ YOKTUR, yalnız kimlik ve izin vardır. Sipariş verisini iki
 * yüzey de aynı yerden — `useOrderFeed` — okur; bağlamdan geçirilseydi
 * ikinci bir okuma yolu doğar ve iki ekran aynı akşam farklı liste
 * gösterebilirdi.
 */
export type OrderQueueSurfaceContext = {
    workspaceId: number;
    locationId: number;
    /** Şube sipariş alıyor mu? `null` = henüz bilinmiyor. */
    acceptsOrders: boolean | null;
    /** Plan sipariş almayı içeriyor mu? `null` = henüz bilinmiyor. */
    planIncludesOrdering: boolean | null;
    onNavigateToSettings: () => void;
    /** Plan kapısının çıkış yolu; yoksa düğme çizilmez. */
    onNavigateToPlan?: () => void;
};

export type OrderQueueSurfaceRenderer = (context: OrderQueueSurfaceContext) => ReactNode;
