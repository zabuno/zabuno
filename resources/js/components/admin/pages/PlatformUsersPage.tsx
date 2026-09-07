import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';

import { t } from '../../../i18n/platform';
import { OpsCard } from '../../ops/OpsCard';
import { Button } from '../../catalog/forms/micro/Button';
import { TextInput } from '../../catalog/forms/micro/TextInput';

type Membership = {
    workspaceId: number;
    workspaceName: string;
    workspaceSlug: string;
    workspaceState: string;
    role: string;
};

/**
 * Oturum olgusu KOŞULLUDUR: kurulum oturumları veritabanında tutmuyorsa
 * ölçüm yoktur ve `known` yanlıştır. Bu ayrım tip düzeyinde durur ki ekran
 * "ölçülmedi" ile "sıfır"ı yanlışlıkla aynı hücreye yazamasın.
 */
type SessionFacts = { known: false } | { known: true; active: number; lastActivity: number | null };

type DirectoryUser = {
    id: number;
    name: string;
    email: string;
    emailVerifiedAt: string | null;
    createdAt: string | null;
    platformRoles: string[];
    memberships: Membership[];
    sessions: SessionFacts;
};

type State =
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; users: DirectoryUser[]; truncated: boolean };

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';
const noteClass = 'px-[var(--space-4)] py-[var(--space-3)] text-meta text-fg-muted';

/**
 * Kullanıcı görünürlüğü — `docs/122` §3 boşluk 2, dalga Y2.
 *
 * Destek çağrısı hep aynı cümleyle başlar: *"Giremiyorum."* Bu ekran o
 * cümlenin bakılacak yeridir: kişi kim, hangi çalışma alanlarında, hangi
 * rolle, adresi doğrulanmış mı, açık oturumu var mı.
 *
 * ÇİZİLMEYENLER:
 *  - **Parola sıfırlama/değiştirme yok.** İstenen görünürlüktü, müdahale
 *    değil; bir destek aracının ilk sürümüne konan yazma fiili, geri
 *    alınamayan ilk kazayı da beraberinde getirir.
 *  - **Kilit sütunu yok.** Bu üründe bugün bir kullanıcı kilidi kavramı
 *    yok — ne bir sütun, ne bir yasaklama kaydı. "Kilitli değil" rozeti,
 *    yapılmamış bir denetimi yapılmış gibi gösterirdi (`docs/109` §8.4).
 *  - **Boş oturum hücresine "0" yazılmaz.** Ölçülmemiş olmak yokluk
 *    değildir; hücrenin neden boş olduğu tablonun altında yazılıdır.
 *
 * ARAMA SUNUCUDA yapılır: tarayıcıda süzmek, yalnız ilk sayfayı süzer ve
 * "aradığım kişi yok" cevabını gerçekte "ilk yüz kişide yok" hâline
 * getirirdi.
 */
export function PlatformUsersPage() {
    const [query, setQuery] = useState('');
    const [state, setState] = useState<State>({ phase: 'loading' });
    const requestRef = useRef(0);

    const load = useCallback(async (search: string) => {
        const requestId = ++requestRef.current;

        try {
            const response = await fetch(`/api/admin/users?query=${encodeURIComponent(search)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (requestRef.current !== requestId) return;

            if (!response.ok) {
                setState({ phase: 'error' });

                return;
            }

            const body = (await response.json()) as {
                users?: DirectoryUser[];
                truncated?: boolean;
            };

            if (requestRef.current !== requestId) return;

            setState({
                phase: 'ready',
                users: body.users ?? [],
                truncated: body.truncated === true,
            });
        } catch {
            if (requestRef.current === requestId) setState({ phase: 'error' });
        }
    }, []);

    /*
        Yükleme durumu EFEKTTE kurulmaz: ilk durum zaten `loading` ve efektin
        içindeki eşzamanlı `setState` gereksiz bir ikinci render tetikler
        (`react-hooks/set-state-in-effect`). Aramayı BAŞLATAN olay ise durumu
        kendisi kurar; kullanıcı düğmeye bastığında bir şey olduğunu görmeli.
    */
    useEffect(() => {
        void (async () => {
            await load('');
        })();
    }, [load]);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setState({ phase: 'loading' });
        void load(query.trim());
    }

    return (
        <div className="flex flex-col gap-[var(--space-5)]">
            <p className="text-body text-fg-secondary">{t('platform.users.intro')}</p>

            {/*
                `noValidate` (`docs/47` FORM-ONE-OUTCOME): tarayıcının kendi
                doğrulaması bizim mesajlarımızı ve odak taşımamızı devre dışı
                bırakır ve kullanıcıya uygulamanın dilinde olmayan bir
                baloncuk gösterir.
            */}
            <form
                onSubmit={handleSubmit}
                noValidate
                className="flex max-w-content flex-col gap-[var(--space-2)]"
            >
                <label
                    htmlFor="platform-user-search"
                    className="flex flex-col gap-[var(--space-1)] text-body text-fg-secondary"
                >
                    {t('platform.users.search.label')}
                    <TextInput
                        id="platform-user-search"
                        name="platform-user-search"
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </label>
                <Button type="submit" className="self-start">
                    {t('platform.users.search.submit')}
                </Button>
            </form>

            {state.phase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('platform.users.loading')}
                </p>
            )}

            {state.phase === 'error' && (
                <div className="flex flex-col gap-[var(--space-2)]">
                    {/*
                        OKUNAMADI, BOŞ DEĞİL: boş bir dizin çizmek "böyle bir
                        kullanıcı yok" derdi ve destek görevlisi çağrıyı yanlış
                        kapatırdı.
                    */}
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('platform.users.error')}
                    </p>
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] self-start text-body font-medium text-fg-danger"
                        onClick={() => {
                            setState({ phase: 'loading' });
                            void load(query.trim());
                        }}
                    >
                        {t('platform.users.retry')}
                    </button>
                </div>
            )}

            {state.phase === 'ready' && (
                <OpsCard title={t('platform.users.region.label')} padded={false}>
                    {state.users.length === 0 ? (
                        <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                            {t('platform.users.empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse">
                                <caption className="sr-only">
                                    {t('platform.users.region.label')}
                                </caption>
                                <thead className="bg-[var(--color-surface-subtle)]">
                                    <tr>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.name')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.email')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.verified')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.platformRole')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.sessions')}
                                        </th>
                                        <th scope="col" className={headClass}>
                                            {t('platform.users.col.memberships')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {state.users.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-t border-[var(--color-border)]"
                                        >
                                            <td className={cellClass}>{user.name}</td>
                                            <td className={cellClass}>{user.email}</td>
                                            {/*
                                                Doğrulanmamış adres ÖLÇÜLMÜŞ bir
                                                olgudur (alan boş), o yüzden
                                                cümleyle söylenir; doğrulanmışta
                                                tarihin kendisi yazılır.
                                            */}
                                            <td className={cellClass}>
                                                {user.emailVerifiedAt ??
                                                    t('platform.users.verified.no')}
                                            </td>
                                            <td className={cellClass}>
                                                {user.platformRoles.map((role) => (
                                                    <code key={role} className="text-meta">
                                                        {role}
                                                    </code>
                                                ))}
                                            </td>
                                            <td className={cellClass}>
                                                {user.sessions.known
                                                    ? String(user.sessions.active)
                                                    : ''}
                                            </td>
                                            <td className={cellClass}>
                                                {user.memberships.length === 0 ? (
                                                    t('platform.users.memberships.none')
                                                ) : (
                                                    <span className="flex flex-col gap-[var(--space-1)]">
                                                        {user.memberships.map((membership) => (
                                                            <span key={membership.workspaceId}>
                                                                {membership.workspaceName} —{' '}
                                                                {membership.role}
                                                            </span>
                                                        ))}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="border-t border-[var(--color-border)]">
                        <p className={noteClass}>{t('platform.users.sessions.note')}</p>
                        <p className={noteClass}>{t('platform.users.lock.note')}</p>
                        {state.truncated ? (
                            <p className={noteClass}>{t('platform.users.truncated')}</p>
                        ) : null}
                    </div>
                </OpsCard>
            )}
        </div>
    );
}

export default PlatformUsersPage;
