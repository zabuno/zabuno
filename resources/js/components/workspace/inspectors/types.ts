import type { ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

/**
 * Bağlam paneli sözleşmesi — `docs/60` §5.
 *
 * Sözleşme cihazdan bağımsız bir dosyada durur: kabuk "bir panel haritası
 * alırım" der, ama MASAÜSTÜ haritasını adıyla anmaz. Kabuk cihaza özgü bir
 * dosyayı adlandırdığı anda, o dosyanın adı paylaşılan kodda geçer ve ayrımın
 * doğruluğu tek bir `type` kelimesine bağlı kalırdı.
 */
export type WorkspaceInspector = {
    /**
     * Panelin başlığı ve `aside` bölgesinin ERİŞİLEBİLİR ADI.
     *
     * Tek kaynaktır: kabuk bu anahtardan bölge adını üretir, panel aynı
     * metni başlık olarak çizer. Başlığı iki yerde tutmak, panel değişince
     * ekran okuyucunun eski adı okuması demekti.
     */
    titleKey: string;
    /**
     * Panelin içeriği; GÖSTERİLECEK BAĞLAM YOKSA `null`.
     *
     * Uygunluk kararı burada verilir, panel bileşeninin içinde değil: kabuk
     * `null` görmeden boş bir sütun çizmekten kaçınamaz. Boş bir sütun,
     * olmayan bir bağlamı varmış gibi gösterir (`docs/60` §4).
     */
    render: (ctx: WorkspaceSectionRuntimeContext) => ReactNode | null;
};

export type WorkspaceInspectorMap = Record<string, WorkspaceInspector>;
