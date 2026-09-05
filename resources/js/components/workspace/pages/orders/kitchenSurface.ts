import type { ReactNode } from 'react';

/**
 * MUTFAK MONİTÖRÜNÜN SÖZLEŞMESİ — cihazdan bağımsız dosyada (`docs/54`).
 *
 * Bağlam panellerinde (`inspectors/types.ts`) kurulan desenin aynısı ve aynı
 * sebeple: paylaşılan kod "bir monitör çizicisi alırım" der, ama MASAÜSTÜ
 * çiziciyi ADIYLA ANMAZ. Kabuk cihaza özgü bir dosyayı adlandırdığı anda o
 * dosyanın adı paylaşılan pakette geçer ve ayrımın doğruluğu tek bir `type`
 * kelimesine bağlı kalırdı.
 *
 * `undefined` gelmesi bir hata değil, telefonun NORMAL hâlidir: mobil giriş
 * noktası bu çiziciyi hiç vermez ve mutfak monitörünün kodu o pakete hiç
 * inmez. Ekran o durumda dürüst bir cümle gösterir — boş bir kutu değil.
 */
export type KitchenSurfaceContext = {
    workspaceId: number;
    locationId: number;
    /** `order.kitchen`: ocağı ilerletebilir mi? */
    canAdvance: boolean;
    /** `order.confirm`: tabağı masaya teslim edebilir mi? */
    canDeliver: boolean;
};

export type KitchenSurfaceRenderer = (context: KitchenSurfaceContext) => ReactNode;
