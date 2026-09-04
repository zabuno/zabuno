import { useId, useMemo, useState } from 'react';
import { MagnifyingGlass } from '@phosphor-icons/react';
import { t } from '../../../i18n/workspace';
import { Label } from '../../catalog/forms/micro/Label';
import { TextInput } from '../../catalog/forms/micro/TextInput';
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

/*
    HAP KONTROL — kısa hedefler için.

    Referans paket ("Restoran Paneli v2") "Git" maddelerini sarmalanan bir
    hap şeridi olarak çiziyor. Sebep ölçülebilir: bu maddeler bir iki
    sözcüktür ve tam genişlikte satır olarak çizildiklerinde panelin sağ
    yarısı boş kalır, göz her madde için satırı baştan sona tarar. Hap,
    sözcüğün kendi genişliği kadar yer kaplar; altı hedef tek bakışta
    görülür.

    Yükseklik JETONDAN gelir (`--control-height`): yoğunluk modu değişince
    dokunma hedefi de birlikte değişir, elle yazılmış bir piksel takılı
    kalmaz. Hap biçimi `rounded-pill` ile yazılır; Tailwind'in sabit değerli
    hap sınıfı derlenmiş CSS'te bir sayıya çözülür ve token kökünden geçmez
    (DS-RADIUS-ROOT-02).
*/
const CHIP_CLASS = [
    'inline-flex min-h-[var(--control-height)] items-center rounded-pill',
    'border border-border px-[var(--space-4)] py-[var(--space-1)]',
    'text-body font-medium whitespace-nowrap text-fg',
    'hover:bg-surface-hover',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus',
].join(' ');

/*
    KAYIT SATIRI — kart değil.

    Kart, her sonucu ayrı bir NESNE gibi gösterir ve arama sonucunu ikinci
    bir liste ekranına çevirir. Oysa bu liste okunmak için değil, içinden
    biri SEÇİLMEK için var: satırlar arasındaki tek ayrım ince bir çizgidir,
    kenarlıklı bir kutu değil. Ayracı listenin kendisi taşır (`divide-y`),
    böylece son satırın altında asılı kalan bir çizgi olmaz.
*/
const RECORD_ROW_CLASS = [
    'flex min-h-[var(--control-height)] w-full flex-col items-start justify-center gap-[var(--space-1)]',
    'px-[var(--space-2)] py-[var(--space-2)] text-start',
    'hover:bg-surface-hover',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus',
].join(' ');

/**
 * Omnibox — `docs/50` §11, `docs/65`, AEP teslim paketi.
 *
 * Tek bir kutu, AÇIK modlar: git, oluştur, ara. Üçü de deterministiktir.
 *
 * **Varsayılan mod deterministiktir ve bu kuralın kendisidir.** Kullanıcının
 * yazdığı metin sessizce bir AI istemine dönüşmez; ne aradığını bilen biri,
 * cevabın nereden geldiğini de bilmelidir.
 *
 * **AI modu YOK.** Referans paket üst çubukta bir pırıltı ve "Söyle, yapayım"
 * gösteriyor; bu üründe ise bağlı bir AI sağlayıcısı bulunmuyor. Bağlı
 * olmayan bir modu göstermek, planın kendi kuralını çiğnerdi: "AI sağlayıcısı
 * bağlı değilse AI girişi gösterilmez" (`docs/50` §17). Sahte bir öneri
 * satırı, sahte bir "düşünüyor" animasyonu ya da devre dışı bir "AI ile yap"
 * düğmesi, olmayan bir yeteneği varmış gibi gösterirdi. Sağlayıcı geldiği gün
 * buraya dördüncü bir grup olarak girer.
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

                    TEK SATIR, referanstaki gibi: "Paşa Döner · Kadıköy". İki
                    ayrı paragraf, panelin en üstünde iki başlık gibi okunup
                    asıl işi — yazmayı — aşağı itiyordu. Ayraç `aria-hidden`:
                    ekran okuyucunun "orta nokta" diye seslendireceği bir
                    bilgi değil, görsel bir işaret.
                */}
                <p
                    data-testid="omnibox-scope"
                    className="flex flex-wrap items-center gap-[var(--space-2)] text-body text-fg-secondary"
                >
                    <span className="font-medium text-fg">{workspaceName}</span>
                    {locationDisplayName !== null ? (
                        <>
                            <span aria-hidden="true" className="text-fg-muted">
                                ·
                            </span>
                            <span>{locationDisplayName}</span>
                        </>
                    ) : null}
                </p>

                <div className="flex flex-col gap-[var(--space-2)]">
                    <Label htmlFor={inputId} className="text-body">
                        {t('workspace.omnibox.input.label')}
                    </Label>
                    {/*
                        BÖLÜMLÜ KONTROL: büyeç alanın İÇİNDE durur. Referansın
                        komut çubuğunda kutunun ne işe yaradığı yazmadan önce
                        görülüyor; ikon alanın dışına konsaydı bir süs olurdu,
                        içindeyken alanın kendi rolünü söyler.
                    */}
                    <TextInput
                        id={inputId}
                        value={query}
                        autoComplete="off"
                        icon={MagnifyingGlass}
                        placeholder={t('workspace.omnibox.input.placeholder')}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                {visible.map((group) => {
                    /*
                        Biçimi grubun İŞİ belirler, adı değil: "git" ve
                        "oluştur" kısa hedeflerdir (hap), "records" ise
                        değişken uzunlukta kayıtlardır (ayraçlı satır).
                    */
                    const isRecords = group.key === 'records';

                    return (
                        <section key={group.key} className="flex flex-col gap-[var(--space-2)]">
                            {/*
                                Hiyerarşi büyük harfle değil AĞIRLIK ve RENKLE
                                kurulur (DS-NO-UPPERCASE-12). Ağırlık ölçeği
                                400/500/700; 600 yok.
                            */}
                            <h3 className="text-body font-bold text-fg-muted">{group.label}</h3>

                            {isRecords ? (
                                <ul className="flex flex-col divide-y divide-[var(--color-border)]">
                                    {group.entries.map((entry) => (
                                        <li key={entry.key}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    entry.onSelect();
                                                    onClose();
                                                }}
                                                className={RECORD_ROW_CLASS}
                                            >
                                                <span className="text-body font-medium text-fg">
                                                    {entry.label}
                                                </span>
                                                {entry.detail !== undefined ? (
                                                    /*
                                                        İkincil satır bir zaman
                                                        damgası ya da sayaç
                                                        değil, kaydın nerede
                                                        durduğunu söyleyen bir
                                                        ETİKETTİR: gövde
                                                        tabanında yazılır,
                                                        ayrımı renk taşır.
                                                    */
                                                    <span className="text-body text-fg-secondary">
                                                        {entry.detail}
                                                    </span>
                                                ) : null}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <ul className="flex flex-wrap gap-[var(--space-2)]">
                                    {group.entries.map((entry) => (
                                        <li key={entry.key}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    entry.onSelect();
                                                    onClose();
                                                }}
                                                className={CHIP_CLASS}
                                            >
                                                {entry.label}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    );
                })}

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
