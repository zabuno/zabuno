import { useId, useMemo, useState } from 'react';
import { Label, TextInput } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { DrawerPanel } from '../../catalog/overlays/compound/DrawerPanel';

export type OmniboxEntry = {
    key: string;
    label: string;
    /** İkincil satır: kaydın nerede olduğunu söyler. */
    detail?: string;
    onSelect: () => void;
};

export type OmniboxGroup = {
    key: string;
    label: string;
    entries: OmniboxEntry[];
};

export type OmniboxProps = {
    open: boolean;
    onClose: () => void;
    /** Görünür KAPSAM: komutun hangi çalışma alanı ve şube üzerinde çalıştığı. */
    workspaceName: string;
    locationDisplayName: string | null;
    groups: OmniboxGroup[];
};

/**
 * Omnibox — `docs/50` §11, `docs/65`.
 *
 * Tek bir kutu, AÇIK modlar: git, oluştur, ara. Üçü de deterministiktir.
 *
 * **Varsayılan mod deterministiktir ve bu kuralın kendisidir.** Kullanıcının
 * yazdığı metin sessizce bir AI istemine dönüşmez; ne aradığını bilen biri,
 * cevabın nereden geldiğini de bilmelidir.
 *
 * **AI modu YOK.** Plan onu bir mod olarak tarif ediyor, ama bu üründe bağlı
 * bir AI sağlayıcısı bulunmuyor. Bağlı olmayan bir modu göstermek, planın
 * kendi kuralını çiğnerdi: "AI sağlayıcısı bağlı değilse AI girişi
 * gösterilmez" (`docs/50` §17). Sağlayıcı geldiği gün buraya dördüncü bir
 * grup olarak girer.
 *
 * **Kapsam görünürdür.** Kullanıcı, seçtiği şeyin hangi çalışma alanı ve
 * hangi şube üzerinde iş göreceğini tahmin etmek zorunda kalmaz.
 */
export function Omnibox({
    open,
    onClose,
    workspaceName,
    locationDisplayName,
    groups,
}: OmniboxProps) {
    const inputId = useId();
    const [query, setQuery] = useState('');

    const normalized = query.trim().toLocaleLowerCase('tr');

    const visible = useMemo(() => {
        if (normalized === '') {
            /*
                Sorgu boşken KAYIT gösterilmez: bir çalışma alanındaki bütün
                ürünleri listelemek bir cevap değil, ikinci bir liste ekranıdır.
                Boş hâlde yalnız gidilecek yerler ve oluşturulabilecek şeyler
                durur — ikisi de kısa ve sabittir.
            */
            return groups.filter((group) => group.key !== 'records');
        }

        return groups
            .map((group) => ({
                ...group,
                entries: group.entries.filter((entry) =>
                    `${entry.label} ${entry.detail ?? ''}`
                        .toLocaleLowerCase('tr')
                        .includes(normalized),
                ),
            }))
            .filter((group) => group.entries.length > 0);
    }, [groups, normalized]);

    const total = visible.reduce((count, group) => count + group.entries.length, 0);

    return (
        <DrawerPanel open={open} onClose={onClose} title={t('workspace.omnibox.title')}>
            <div className="flex flex-col gap-[var(--space-fluid-md)]">
                {/*
                    KAPSAM. Plan bunu açıkça istiyor (`docs/50` §11): kullanıcı
                    komutun hangi kiracı ve hangi şube üzerinde çalışacağını
                    tahmin etmemelidir.
                */}
                <div className="flex flex-col gap-1">
                    <p className="text-body font-medium text-fg">{workspaceName}</p>
                    {locationDisplayName !== null ? (
                        <p className="text-body text-fg-secondary">{locationDisplayName}</p>
                    ) : null}
                </div>

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor={inputId}>{t('workspace.omnibox.input.label')}</Label>
                    </div>
                    <TextInput
                        id={inputId}
                        value={query}
                        autoComplete="off"
                        placeholder={t('workspace.omnibox.input.placeholder')}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                {visible.map((group) => (
                    <section key={group.key} className="flex flex-col gap-1">
                        <h3 className="text-meta text-fg-muted">{group.label}</h3>
                        <ul className="flex flex-col">
                            {group.entries.map((entry) => (
                                <li key={entry.key}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            entry.onSelect();
                                            onClose();
                                        }}
                                        className="flex min-h-[var(--density-hit-area-min)] w-full flex-col items-start rounded-md px-2 py-1.5 text-start hover:bg-surface-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus"
                                    >
                                        <span className="text-body text-fg">{entry.label}</span>
                                        {entry.detail !== undefined ? (
                                            <span className="text-meta text-fg-muted">
                                                {entry.detail}
                                            </span>
                                        ) : null}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}

                {/*
                    Boş sonuç bir durumdur ve söylenmelidir. Sessizce boş kalan
                    bir liste, kullanıcıya aramanın çalışmadığını mı yoksa
                    sonucun olmadığını mı anlatır — ayırt edilemez.
                */}
                {total === 0 ? (
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.omnibox.empty')}
                    </p>
                ) : null}
            </div>
        </DrawerPanel>
    );
}
