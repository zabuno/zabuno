import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';

import { t } from '../../../i18n/platform';
import { OpsCard } from '../../ops/OpsCard';
import { Button } from '../../catalog/forms/micro/Button';
import { Select } from '../../catalog/forms/micro/Select';
import { TextInput } from '../../catalog/forms/micro/TextInput';

type AuditEntry = {
    id: string;
    source: string;
    action: string;
    subject: string | null;
    actor: string | null;
    workspaceId: number | null;
    workspaceName: string | null;
    at: string | null;
};

type State =
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; entries: AuditEntry[]; hasMore: boolean };

const SOURCE_LABELS = {
    media: 'platform.auditLog.source.media',
    menu: 'platform.auditLog.source.menu',
    publication: 'platform.auditLog.source.publication',
    credential: 'platform.auditLog.source.credential',
} as const;

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';
const noteClass = 'px-[var(--space-4)] py-[var(--space-3)] text-meta text-fg-muted';

type SourceKey = keyof typeof SOURCE_LABELS;

function sourceLabel(source: string): string {
    // Sunucu bir gün beşinci kaynağı eklerse ekran onu SAKLAMAZ: çevirisi
    // olmayan kaynak ham adıyla yazılır. Bilinmeyen bir olayı gizleyen bir
    // denetim günlüğü, denetim günlüğü olmaktan çıkar.
    if (!Object.prototype.hasOwnProperty.call(SOURCE_LABELS, source)) {
        return source;
    }

    return t(SOURCE_LABELS[source as SourceKey]);
}

/**
 * Denetim günlüğü — `docs/122` §3 boşluk 6, dalga Y2.
 *
 * Ölçülen cümle: *"Kayıt yazılıyor, okunacak yer yok."* Dört tablo aylardır
 * doluyor — medya, menü, yayın, kasa — ve platform düzeyinde hiçbirini okuyan
 * bir yer yok. **Okunmayan denetim izi yoktur.**
 *
 * ÇİZİLMEYENLER:
 *  - **Düzenleme ve silme yok.** Düzeltilebilen bir denetim izi denetim izi
 *    değildir; bu ekranda kaydı değiştiren tek bir düğme bile yoktur.
 *  - **Menünün öncesi/sonrası değerleri yok.** "380'den 420'ye" cümlesi
 *    kiracının kendi menü geçmişinde yerindedir; kiracılar arası bir listede
 *    fazladan veridir.
 *  - **Kiracısı olmayan satıra kiracı yakıştırılmaz:** kasa izi platformun
 *    kaydıdır, hücresi boş kalır.
 *
 * SÜZGEÇ VE SAYFA SUNUCUDA. Tarayıcıda süzmek, yalnız görünen sayfayı
 * süzerdi ve "bu kiracıda hiçbir olay yok" cevabı gerçekte "bu sayfada yok"
 * anlamına gelirdi.
 */
export function AuditLogPage() {
    const [source, setSource] = useState('');
    const [workspaceInput, setWorkspaceInput] = useState('');
    const [workspace, setWorkspace] = useState('');
    const [page, setPage] = useState(1);
    const [state, setState] = useState<State>({ phase: 'loading' });
    const requestRef = useRef(0);

    const load = useCallback(
        async (nextPage: number, nextSource: string, nextWorkspace: string) => {
            const requestId = ++requestRef.current;

            const params = new URLSearchParams({ page: String(nextPage) });
            if (nextSource !== '') params.set('source', nextSource);
            if (nextWorkspace !== '') params.set('workspace', nextWorkspace);

            try {
                const response = await fetch(`/api/admin/audit-log?${params.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (requestRef.current !== requestId) return;

                if (!response.ok) {
                    setState({ phase: 'error' });

                    return;
                }

                const body = (await response.json()) as {
                    entries?: AuditEntry[];
                    hasMore?: boolean;
                };

                if (requestRef.current !== requestId) return;

                setState({
                    phase: 'ready',
                    entries: body.entries ?? [],
                    hasMore: body.hasMore === true,
                });
            } catch {
                if (requestRef.current === requestId) setState({ phase: 'error' });
            }
        },
        [],
    );

    /*
        Yükleme durumu EFEKTTE kurulmaz, olayı BAŞLATAN yerde kurulur.
        Efektin içinde eşzamanlı `setState`, bir render'ın hemen ardından
        ikincisini tetikler (`react-hooks/set-state-in-effect`); üstelik ilk
        durum zaten `loading` olduğu için gereksizdir.
    */
    useEffect(() => {
        void (async () => {
            await load(page, source, workspace);
        })();
    }, [load, page, source, workspace]);

    function handleSourceChange(next: string) {
        // Süzgeç değişince sayfa BAŞA döner: 7. sayfada süzgeç daraltıldığında
        // kullanıcıyı var olmayan bir sayfaya bırakmak, boş bir liste
        // gösterip "hiç olay yok" dedirtirdi.
        setPage(1);
        setSource(next);
    }

    function handleWorkspaceSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPage(1);
        setWorkspace(workspaceInput.trim());
    }

    return (
        <div className="flex flex-col gap-[var(--space-5)]">
            <p className="text-body text-fg-secondary">{t('platform.auditLog.intro')}</p>

            <div className="flex flex-wrap items-end gap-[var(--space-4)]">
                <label
                    htmlFor="audit-log-source"
                    className="flex flex-col gap-[var(--space-1)] text-body text-fg-secondary"
                >
                    {t('platform.auditLog.filter.source')}
                    <Select
                        id="audit-log-source"
                        name="audit-log-source"
                        value={source}
                        onChange={(event) => handleSourceChange(event.target.value)}
                    >
                        <option value="">{t('platform.auditLog.filter.source.all')}</option>
                        {Object.keys(SOURCE_LABELS).map((key) => (
                            <option key={key} value={key}>
                                {sourceLabel(key)}
                            </option>
                        ))}
                    </Select>
                </label>

                {/*
                    `noValidate` (`docs/47` FORM-ONE-OUTCOME): tarayıcının
                    kendi doğrulaması bizim mesajlarımızı ve odak taşımamızı
                    devre dışı bırakır.
                */}
                <form
                    onSubmit={handleWorkspaceSubmit}
                    noValidate
                    className="flex flex-wrap items-end gap-[var(--space-2)]"
                >
                    <label
                        htmlFor="audit-log-workspace"
                        className="flex flex-col gap-[var(--space-1)] text-body text-fg-secondary"
                    >
                        {t('platform.auditLog.filter.workspace')}
                        <TextInput
                            id="audit-log-workspace"
                            name="audit-log-workspace"
                            type="text"
                            inputMode="numeric"
                            value={workspaceInput}
                            onChange={(event) => setWorkspaceInput(event.target.value)}
                        />
                    </label>
                    <Button type="submit">{t('platform.auditLog.filter.apply')}</Button>
                </form>
            </div>

            {state.phase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('platform.auditLog.loading')}
                </p>
            )}

            {state.phase === 'error' && (
                <div className="flex flex-col gap-[var(--space-2)]">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('platform.auditLog.error')}
                    </p>
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] self-start text-body font-medium text-fg-danger"
                        onClick={() => {
                            setState({ phase: 'loading' });
                            void load(page, source, workspace);
                        }}
                    >
                        {t('platform.auditLog.retry')}
                    </button>
                </div>
            )}

            {state.phase === 'ready' && (
                <OpsCard title={t('platform.auditLog.title')} padded={false}>
                    {state.entries.length === 0 ? (
                        <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                            {t('platform.auditLog.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse">
                                <caption className="sr-only">
                                    {t('platform.auditLog.title')}
                                </caption>
                                <thead className="bg-[var(--color-surface-subtle)]">
                                    <tr>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.when')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.source')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.action')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.subject')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.actor')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.auditLog.col.workspace')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.entries.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="border-t border-[var(--color-border)]"
                                        >
                                            <td className={cellClass}>{entry.at ?? ''}</td>
                                            <td className={cellClass}>
                                                {sourceLabel(entry.source)}
                                            </td>
                                            {/*
                                                Eylem HAM değeriyle yazılır: bu,
                                                kaydın kendi kelimesidir ve
                                                güzelleştirilmiş bir eşanlamlı,
                                                yeni bir eylem eklendiği gün
                                                sessizce yanlış olurdu.
                                            */}
                                            <td className={cellClass}>
                                                <code className="text-meta">{entry.action}</code>
                                            </td>
                                            <td className={cellClass}>{entry.subject ?? ''}</td>
                                            {/*
                                                Fail silinmişse hücre boş kalır:
                                                kaydı gizlemek yerine failin
                                                bilinmediğini söylemek dürüsttür.
                                            */}
                                            <td className={cellClass}>{entry.actor ?? ''}</td>
                                            <td className={cellClass}>
                                                {entry.workspaceName ?? ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center gap-[var(--space-3)] border-t border-[var(--color-border)] px-[var(--space-4)] py-[var(--space-3)]">
                        <button
                            type="button"
                            disabled={page <= 1}
                            onClick={() => setPage((current) => Math.max(1, current - 1))}
                            className="min-h-[var(--density-hit-area-min)] rounded-[var(--radius-md)] border border-[var(--color-border)] px-[var(--space-3)] text-body font-medium disabled:opacity-60"
                        >
                            {t('platform.auditLog.prev')}
                        </button>
                        <span className="text-meta text-fg-muted">
                            {t('platform.auditLog.page', { page: String(page) })}
                        </span>
                        <button
                            type="button"
                            disabled={!state.hasMore}
                            onClick={() => setPage((current) => current + 1)}
                            className="min-h-[var(--density-hit-area-min)] rounded-[var(--radius-md)] border border-[var(--color-border)] px-[var(--space-3)] text-body font-medium disabled:opacity-60"
                        >
                            {t('platform.auditLog.next')}
                        </button>
                    </div>
                    <div className="border-t border-[var(--color-border)]">
                        <p className={noteClass}>{t('platform.auditLog.scope')}</p>
                    </div>
                </OpsCard>
            )}
        </div>
    );
}

export default AuditLogPage;
