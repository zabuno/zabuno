import { useEffect, useState } from 'react';

import { t } from '../../../i18n/platform';
import {
    ModuleInventory,
    type ContextGraph,
    type ModuleRow,
    type SpecModuleRow,
} from './ModuleInventory';

type State =
    | { phase: 'loading' }
    | { phase: 'error' }
    | {
          phase: 'ready';
          modules: ModuleRow[];
          graph: ContextGraph;
          specs: SpecModuleRow[];
          unmappedContexts: string[];
      };

const ENDPOINT = '/api/admin/modules';

/**
 * Modül envanteri — `docs/111` adım 2'nin getirme yarısı.
 *
 * Bu dosya yalnız uçtan veri alır ve `ModuleInventory`'ye verir. Ayrım FF-210
 * ile yapıldı ve gerekçesi ölçmekle ilgili: çizen parça getirmeyi bilmediği
 * için 320 pikselde GERÇEK bir düzen motorunda ölçülebiliyor
 * (`scripts/mobile-ux-audit`). Getirmeyle iç içe bir bileşen, hikâyede hep
 * "yükleniyor" gösterirdi — yani ekranın kendisi hiç ölçülmemiş olurdu ve
 * rapor yine de "geçti" derdi.
 *
 * Uçta olmayan hiçbir alan burada uydurulmaz; salt okunur ve öyle kalır
 * (`docs/111` §5).
 */
export function ModulesPage() {
    const [state, setState] = useState<State>({ phase: 'loading' });

    useEffect(() => {
        let cancelled = false;
        void (async () => {
            try {
                const response = await fetch(ENDPOINT, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (cancelled) return;
                if (!response.ok) {
                    setState({ phase: 'error' });
                    return;
                }
                const body = (await response.json()) as {
                    modules?: ModuleRow[];
                    contextGraph?: ContextGraph;
                    specModules?: SpecModuleRow[];
                    unmappedContexts?: string[];
                };
                setState({
                    phase: 'ready',
                    modules: body.modules ?? [],
                    graph: {
                        nodes: body.contextGraph?.nodes ?? [],
                        edges: body.contextGraph?.edges ?? [],
                    },
                    specs: body.specModules ?? [],
                    unmappedContexts: body.unmappedContexts ?? [],
                });
            } catch {
                if (!cancelled) setState({ phase: 'error' });
            }
        })();
        return () => {
            cancelled = true;
        };
    }, []);

    if (state.phase === 'loading') {
        return (
            <p role="status" className="text-body text-fg-muted">
                {t('engineering.modules.loading')}
            </p>
        );
    }

    if (state.phase === 'error') {
        /*
            OKUNAMADI, BOŞ DEĞİL. Hata durumunda boş bir tablo çizmek
            superadmin'e "bu kurulumda modül yok" derdi; oysa bilinen tek şey
            listeyi okuyamadığımızdır.
        */
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('engineering.modules.error')}
            </p>
        );
    }

    return (
        <ModuleInventory
            modules={state.modules}
            graph={state.graph}
            specs={state.specs}
            unmappedContexts={state.unmappedContexts}
        />
    );
}

export default ModulesPage;
