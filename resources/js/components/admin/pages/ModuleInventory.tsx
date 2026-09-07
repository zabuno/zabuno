import { t } from '../../../i18n/platform';
import { Badge, type BadgeStatus } from '../../catalog/feedback/micro/Badge';
import { OpsCard } from '../../ops/OpsCard';

export type ModuleRow = {
    code: string;
    name: string;
    moduleClass: string;
    version: string;
    dependencies: string[];
    deterministicBaseline: string;
    aiPosture: string;
};

export type ContextEdge = {
    from: string;
    to: string;
    evidencePath: string;
};

export type ContextGraph = {
    nodes: string[];
    edges: ContextEdge[];
};

export type ModulePresence = 'implemented' | 'partial' | 'definition-only' | 'unknown';

export type SpecModuleRow = {
    slug: string;
    name: string;
    specPath: string;
    moduleClass: string;
    mapping: string;
    mappingNote: string;
    contexts: string[];
    presence: ModulePresence;
    observation: {
        directories: string[];
        routeFiles: string[];
        tables: string[];
        testFiles: number;
    };
};

export type ModuleInventoryProps = {
    modules: ModuleRow[];
    graph: ContextGraph;
    specs: SpecModuleRow[];
    unmappedContexts: string[];
};

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';
const noteClass = 'px-[var(--space-4)] py-[var(--space-3)] text-meta text-fg-muted';

/**
 * Rozetin RENGİ bir bilgi taşımaz, yalnız hızlandırır.
 *
 * Ölçülmüş iki durum renklidir, ölçülmemiş ikisi nötr — çünkü asıl ayrım
 * "iyi/kötü" değil, "ölçüldü/ölçülemedi". Anlamı taşıyan şey her zaman
 * rozetin METNİ ve yanındaki gözlemdir; renk tek başına hiçbir yerde tek
 * kaynak değildir.
 */
const PRESENCE_TONE: Record<ModulePresence, BadgeStatus> = {
    implemented: 'success',
    partial: 'warning',
    'definition-only': 'info',
    unknown: 'info',
};

const PRESENCE_LABEL = {
    implemented: 'engineering.modules.status.implemented',
    partial: 'engineering.modules.status.partial',
    'definition-only': 'engineering.modules.status.definitionOnly',
    unknown: 'engineering.modules.status.unknown',
} as const;

const PRESENCE_MEANING = {
    implemented: 'engineering.modules.status.implemented.means',
    partial: 'engineering.modules.status.partial.means',
    'definition-only': 'engineering.modules.status.definitionOnly.means',
    unknown: 'engineering.modules.status.unknown.means',
} as const;

/**
 * Gözlem metni rozetle AYNI HÜCREDE üretilir.
 *
 * `docs/111` §8.4 bir kabul ölçütü koydu: rozet gözlemsiz çizilmez. Bunu bir
 * kural olarak yazmak yetmez — iki ayrı yerde çizilen iki şey er ya da geç
 * ayrışır. Bu yüzden ikisini tek fonksiyon üretir: rozeti çağıran, gözlemi de
 * çağırmış olur.
 */
function observationOf(spec: SpecModuleRow): string {
    if (spec.presence === 'unknown') {
        /*
            Ölçüm YAPILMADI. Buraya "0 dizin · 0 test" yazmak, yapılmamış bir
            ölçümün sonucunu uydurmak olurdu — sıfır "hiç yok" der ve bu,
            bilinmeyen için yanlış bir cevaptır (`docs/109` §8.3).
        */
        return spec.mappingNote;
    }

    if (spec.presence === 'definition-only') {
        return t('engineering.modules.obs.noContext');
    }

    const parts = [
        t('engineering.modules.obs.directories', {
            count: String(spec.observation.directories.length),
        }),
    ];

    if (spec.observation.routeFiles.length > 0) {
        parts.push(
            t('engineering.modules.obs.routeFiles', {
                count: String(spec.observation.routeFiles.length),
            }),
        );
    }

    if (spec.observation.tables.length > 0) {
        parts.push(
            t('engineering.modules.obs.tables', { count: String(spec.observation.tables.length) }),
        );
    }

    if (spec.observation.testFiles > 0) {
        parts.push(
            t('engineering.modules.obs.tests', { count: String(spec.observation.testFiles) }),
        );
    }

    return parts.join(' · ');
}

/**
 * Modül envanteri — `docs/111` adım 2 (ekran), 3 (rozet) ve 4 (eşleme).
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
 *    yeşil rozeti yalan olur. Durum rozeti bunun yerine geçmez ve ekranda
 *    ne ölçmediğini de yazar.
 *  - Boş hücreye "0", "—" ya da "bilinmiyor" yazılmaz (`docs/109` §8.3):
 *    ölçülmemiş olmak yokluk değildir ve üçü de cevap gibi görünür.
 *  - Tanım dosyalarının DURUM iddiası okunmaz: oradan alınan tek şey, hangi
 *    kod bağlamına karşılık geldiğidir (`docs/111` §3.4, §4.2).
 *
 * Her kartın altında KAYNAK DOSYA yazılıdır. Bu sayfanın değeri listenin
 * kendisi değil — `ls` de bir sayı söyler — listenin nereden geldiğinin
 * denetlenebilir olmasıdır.
 */
export function ModuleInventory({ modules, graph, specs, unmappedContexts }: ModuleInventoryProps) {
    return (
        <div className="flex flex-col gap-[var(--space-5)]">
            <OpsCard title={t('engineering.modules.core.title')} padded={false}>
                {modules.length === 0 ? (
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
                                {modules.map((module) => (
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

            <OpsCard title={t('engineering.modules.specs.title')} padded={false}>
                <p className={noteClass}>{t('engineering.modules.specs.about')}</p>
                {/*
                    ROZETİN İDDİA ETMEDİĞİ ŞEY, İDDİA ETTİĞİ KADAR GÖRÜNÜR.
                    Kanıtsız bir "çalışıyor" rozeti olmayan bir sayfadan kötüdür:
                    superadmin ona bakıp aramayı bırakır (`docs/111` §1).
                */}
                <p className="px-[var(--space-4)] pb-[var(--space-3)] text-meta text-fg">
                    {t('engineering.modules.specs.claim')}
                </p>
                <dl className="border-t border-[var(--color-border)] px-[var(--space-4)] py-[var(--space-3)]">
                    <dt className="text-meta font-bold text-fg-subtle">
                        {t('engineering.modules.legend.title')}
                    </dt>
                    {(Object.keys(PRESENCE_LABEL) as ModulePresence[]).map((presence) => (
                        <dd
                            key={presence}
                            className="flex flex-wrap items-baseline gap-[var(--space-2)] pt-[var(--space-2)] text-meta text-fg-muted"
                        >
                            <Badge status={PRESENCE_TONE[presence]}>
                                {t(PRESENCE_LABEL[presence])}
                            </Badge>
                            <span className="min-w-0 flex-1">{t(PRESENCE_MEANING[presence])}</span>
                        </dd>
                    ))}
                </dl>
                {specs.length === 0 ? (
                    <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.modules.specs.empty')}
                    </p>
                ) : (
                    /*
                        TABLO DEĞİL, LİSTE. Altmış iki satır ve dört sütun,
                        320 pikselde ancak yatay kaydırmayla sığardı — ve dar
                        ekranda yatay kaydırma, kullanıcının bir daha
                        bulamayacağı içerik demektir. Blok satır her genişlikte
                        aynı sırayı okutur: ad, rozet + gözlem, adres.
                    */
                    <ul className="border-t border-[var(--color-border)]">
                        {specs.map((spec) => (
                            <li
                                key={spec.slug}
                                className="flex flex-col gap-[var(--space-1)] border-b border-[var(--color-border)] px-[var(--space-4)] py-[var(--space-3)] last:border-b-0"
                            >
                                <p className="text-body font-bold text-fg">{spec.name}</p>
                                <p className="flex flex-wrap items-baseline gap-[var(--space-2)] text-meta text-fg-muted">
                                    <Badge status={PRESENCE_TONE[spec.presence]}>
                                        {t(PRESENCE_LABEL[spec.presence])}
                                    </Badge>
                                    <span className="min-w-0">{observationOf(spec)}</span>
                                </p>
                                <p className="flex flex-wrap gap-[var(--space-2)] text-meta text-fg-muted">
                                    {/*
                                        Bağlamı olmayan modülde bu satır BOŞ kalır;
                                        "yok" yazılmaz. Rozet zaten ne ölçüldüğünü
                                        söylüyor, ikinci bir olumsuzlama bilgi
                                        eklemez (`docs/109` §8.3).
                                    */}
                                    {spec.contexts.map((context) => (
                                        <code key={context} className="text-meta">
                                            {context}
                                        </code>
                                    ))}
                                    <code className="text-meta break-all">{spec.specPath}</code>
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="border-t border-[var(--color-border)]">
                    <p className={noteClass}>{t('engineering.modules.specs.source')}</p>
                </div>
            </OpsCard>

            <OpsCard title={t('engineering.modules.unmapped.title')} padded={false}>
                <p className={noteClass}>{t('engineering.modules.unmapped.about')}</p>
                {unmappedContexts.length === 0 ? (
                    <p className="px-[var(--space-4)] pb-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.modules.unmapped.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-[var(--space-2)] border-t border-[var(--color-border)] px-[var(--space-4)] py-[var(--space-3)]">
                        {unmappedContexts.map((context) => (
                            <li key={context}>
                                <code className="text-meta">{context}</code>
                            </li>
                        ))}
                    </ul>
                )}
            </OpsCard>

            <OpsCard title={t('engineering.modules.graph.title')} padded={false}>
                <p className={noteClass}>{t('engineering.modules.graph.about')}</p>
                {graph.nodes.length === 0 ? (
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
                                {graph.nodes.map((node) => {
                                    const outgoing = graph.edges.filter(
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

export default ModuleInventory;
