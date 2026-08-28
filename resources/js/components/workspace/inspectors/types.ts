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
export type WorkspaceInspectorMap = Record<
    string,
    (ctx: WorkspaceSectionRuntimeContext) => ReactNode
>;
