import { useEffect, useState } from 'react';

import { t } from '../../../i18n/platform';
import { OpsCard } from '../../ops/OpsCard';

type ModuleRow = {
    code: string;
    name: string;
    moduleClass: string;
    version: string;
    dependencies: string[];
    deterministicBaseline: string;
    aiPosture: string;
};

type ContextEdge = {
    from: string;
    to: string;
    evidencePath: string;
};

type ContextGraph = {
    nodes: string[];
    edges: ContextEdge[];
};

type State =
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; modules: ModuleRow[]; graph: ContextGraph };

const ENDPOINT = '/api/admin/modules';

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';
const noteClass = 'px-[var(--space-4)] py-[var(--space-3)] text-meta text-fg-muted';

/**
 * Modül envanteri — `docs/111` adım 2.
 *
 * Superadmin bu sayfayı dört soruyla açar ve dördü de somut: bu kurulumda
 * şu yetenek gerçekten ayakta mı, neye bağlı, bunu nereden biliyorum,
 * kapatabilir miyim. Sayfa dördünü de cevaplar — sonuncusunu "hayır" diye.
 *
 * ÇİZİLMEYENLERİN LİSTESİ, ÇİZİLENLER KADAR KASITLIDIR (`docs/111` §6):
 *  - Açma/kapama anahtarı yok, devre dışı olanı da yok: bugün hiçbir rota,
 *    iş ya da menü bir modül anahtarına bakmıyor. Devre dışı bir düğme
 *    tutulmayacak bir söz verir (`docs/109` §8.4).
 *  - Sağlık rozeti yok: modül başına bir sonda yok, olmayan bir sondanın
 *    yeşil rozeti yalan olur.
 *  - Boş hücreye "0", "—" ya da "bilinmiyor" yazılmaz (`docs/109` §8.3):
 *    ölçülmemiş olmak yokluk değildir ve üçü de cevap gibi görünür.
 *  - `modules/*.md` durum iddiası okunmaz: o 62 dosya kendini "PLANNING
 *    ONLY" ilan ediyor ve en az 18'inde bu yanlış (`docs/111` §0).
 *
 * Her kartın altında KAYNAK DOSYA yazılıdır. Bu sayfanın değeri listenin
 * kendisi değil — `ls modules | wc -l` de bir sayı söyler — listenin
 * nereden geldiğinin denetlenebilir olmasıdır.
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
                };
                setState({
                    phase: 'ready',
                    modules: body.modules ?? [],
                    graph: {
                        nodes: body.contextGraph?.nodes ?? [],
                        edges: body.contextGraph?.edges ?? [],
                    },
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
        <div className="flex flex-col gap-[var(--space-5)]">
            <OpsCard title={t('engineering.modules.core.title')} padded={false}>
                {state.modules.length === 0 ? (
                    <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.modules.core.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <caption className="sr-only">
                                {t('engineering.modules.core.title')}
                            </caption>
                            <thead className="bg-[var(--color-surface-subtle)]">
                                <tr>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.code')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.name')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.version')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.class')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.aiPosture')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.baseline')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.col.dependsOn')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.modules.map((module) => (
                                    <tr
                                        key={module.code}
                                        className="border-t border-[var(--color-border)]"
                                    >
                                        <td className={cellClass}>
                                            <code className="text-meta">{module.code}</code>
                                        </td>
                                        <td className={cellClass}>{module.name}</td>
                                        <td className={cellClass}>{module.version}</td>
                                        {/*
                                            Sınıf, duruş ve taban ham değerleriyle yazılır.
                                            Bunlar kayıt dosyasının kelimeleridir; ekranda
                                            güzelleştirilmiş bir eşanlamlı, dosyaya yeni bir
                                            değer eklendiği gün sessizce yanlış olurdu.
                                        */}
                                        <td className={cellClass}>
                                            <code className="text-meta">{module.moduleClass}</code>
                                        </td>
                                        <td className={cellClass}>
                                            <code className="text-meta">{module.aiPosture}</code>
                                        </td>
                                        <td className={cellClass}>
                                            <code className="text-meta">
                                                {module.deterministicBaseline}
                                            </code>
                                        </td>
                                        {/*
                                            Bağımlılığı olmayan modülde hücre BOŞ kalır.
                                            "Bağımsız" yazmak bir ölçüm değil bir yorumdur ve
                                            bu dosya yalnız CORE kodları arasındaki bağı
                                            taşır — bağlam düzeyindeki bağ alttaki karttadır.
                                        */}
                                        <td className={cellClass}>
                                            {module.dependencies.length > 0 ? (
                                                <span className="flex flex-wrap gap-[var(--space-2)]">
                                                    {module.dependencies.map((dependency) => (
                                                        <code
                                                            key={dependency}
                                                            className="text-meta"
                                                        >
                                                            {dependency}
                                                        </code>
                                                    ))}
                                                </span>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="border-t border-[var(--color-border)]">
                    <p className={noteClass}>{t('engineering.modules.core.source')}</p>
                    <p className={noteClass}>{t('engineering.modules.core.scope')}</p>
                </div>
            </OpsCard>

            <OpsCard title={t('engineering.modules.graph.title')} padded={false}>
                <p className={noteClass}>{t('engineering.modules.graph.about')}</p>
                {state.graph.nodes.length === 0 ? (
                    <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.modules.graph.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <caption className="sr-only">
                                {t('engineering.modules.graph.title')}
                            </caption>
                            <thead className="bg-[var(--color-surface-subtle)]">
                                <tr>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.graph.col.from')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.graph.col.to')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.modules.graph.col.evidence')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.graph.nodes.map((node) => {
                                    const outgoing = state.graph.edges.filter(
                                        (edge) => edge.from === node,
                                    );

                                    return (
                                        <tr
                                            key={node}
                                            className="border-t border-[var(--color-border)]"
                                        >
                                            <td className={cellClass}>{node}</td>
                                            {/*
                                                Kenarı olmayan bağlam listeden DÜŞMEZ ama
                                                hücresi de doldurulmaz. Grafik gözlemdir,
                                                mimari zorlama değil: kenarın yokluğu
                                                "bağımsız" demek değil, "bu taramada
                                                görülmedi" demektir.
                                            */}
                                            <td className={cellClass}>
                                                {outgoing.length > 0 ? (
                                                    <span className="flex flex-col gap-[var(--space-1)]">
                                                        {outgoing.map((edge) => (
                                                            <span key={edge.to}>{edge.to}</span>
                                                        ))}
                                                    </span>
                                                ) : null}
                                            </td>
                                            {/*
                                                Kanıt kenarın YANINDA durur. `docs/109`
                                                §8.7'deki beş kusurun ortak noktası,
                                                iddiaya eşlik eden ölçümün gösterilmemiş
                                                olmasıydı; okunmayan kanıt yoktur.
                                            */}
                                            <td className={cellClass}>
                                                {outgoing.length > 0 ? (
                                                    <span className="flex flex-col gap-[var(--space-1)]">
                                                        {outgoing.map((edge) => (
                                                            <code
                                                                key={edge.to}
                                                                className="text-meta break-all"
                                                            >
                                                                {edge.evidencePath}
                                                            </code>
                                                        ))}
                                                    </span>
                                                ) : null}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="border-t border-[var(--color-border)]">
                    <p className={noteClass}>{t('engineering.modules.graph.source')}</p>
                </div>
            </OpsCard>
        </div>
    );
}

export default ModulesPage;
