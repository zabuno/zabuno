import { useState, type ReactNode } from 'react';
import {
    Crown,
    ClipboardText,
    PencilSimple,
    CookingPot,
    Eye,
    CaretDown,
    CaretRight,
} from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import type { WorkspaceTranslationKey } from '../../../../i18n/workspace';
import matrix from '../../../../generated/role-permission-matrix.json';

/**
 * Deponun TANIDIĞI roller — `App\Domain\Tenancy\MembershipRole`.
 *
 * Kaynağın dördüncü rolü ("Mutfak") bir süre burada YOKTU ve olmaması bir
 * dürüstlüktü: ne izin listesi (`RolePermissions`) ne de bir veritabanı
 * değeri vardı, yani ekrana yazmak sahibe hiç kimseye veremeyeceği bir
 * yetkiyi göstermek olurdu. Artık dördü de gerçek; liste yine deponun
 * tanıdığı rollerle SINIRLI ve kaynaktan kopyalanmıyor.
 */
export type TeamRoleKey = 'owner' | 'manager' | 'editor' | 'kitchen' | 'member';

type RoleEntry = {
    icon: ReactNode;
    nameKey: WorkspaceTranslationKey;
    descriptionKey: WorkspaceTranslationKey;
};

/*
    SIRA YETKİDEN DARA DOĞRUDUR. Sahip her şeyi yapar, salt okunur rol hiçbir
    şeyi; aradaki roller arasındaki fark ancak yan yana okununca görünür ve
    sahibin buraya bakma sebebi tam olarak o farktır. Mutfak, editörden
    sonra gelir: ürün ve fiyata dokunmadan yalnız alerjen ve "bugün bitti"
    ile ilgilenir.
*/
const ROLE_ORDER: TeamRoleKey[] = ['owner', 'manager', 'editor', 'kitchen', 'member'];

const ROLES: Record<TeamRoleKey, RoleEntry> = {
    owner: {
        icon: <Crown size={20} weight="regular" />,
        nameKey: 'workspace.team.invite.role.owner',
        descriptionKey: 'workspace.team.roleGuide.owner',
    },
    manager: {
        icon: <ClipboardText size={20} weight="regular" />,
        nameKey: 'workspace.team.invite.role.manager',
        descriptionKey: 'workspace.team.roleGuide.manager',
    },
    editor: {
        icon: <PencilSimple size={20} weight="regular" />,
        nameKey: 'workspace.team.invite.role.editor',
        descriptionKey: 'workspace.team.roleGuide.editor',
    },
    kitchen: {
        // Kaynağın kendi ikonu (`ph-cooking-pot`).
        icon: <CookingPot size={20} weight="regular" />,
        nameKey: 'workspace.team.invite.role.kitchen',
        descriptionKey: 'workspace.team.roleGuide.kitchen',
    },
    member: {
        icon: <Eye size={20} weight="regular" />,
        nameKey: 'workspace.team.invite.role.member',
        descriptionKey: 'workspace.team.roleGuide.member',
    },
};

/**
 * KODDAN ÜRETİLEN MATRİS — `docs/139`.
 *
 * Dosyayı `php artisan authorization:matrix` yazar ve içeriği
 * `App\Domain\Authorization\RolePermissions`'tan türetilir. Bu bileşen
 * hangi rolün neyi yapabildiğini BİLMEZ ve bilmemeli: bilseydi, sunucudaki
 * izin listesiyle ekrandaki liste iki ayrı gerçek olurdu ve biri
 * değiştiğinde öteki sessizce eskirdi.
 *
 * Burada elle yazılan tek şey yeteneklerin ADIDIR (i18n kataloğunda) —
 * "kim yapabilir" değil.
 */
type MatrixRole = {
    key: string;
    granted: string[];
    denied: string[];
};

const MATRIX_ROLES = matrix.roles as MatrixRole[];

function abilityLabel(permission: string): string {
    /*
        Anahtar iznin KENDİ değerinden üretilir. Alternatifi, izin → etiket
        anahtarı diye ikinci bir elle tutulan eşleme tablosuydu; o tablo da
        bir gün matristen ayrışırdı. Eksik ya da fazla etiketi kapı yakalar
        (`TeamRoleGuide.matrix.test.tsx`), tip sistemi değil — bu yüzden
        dönüşüm burada açıkça yapılıyor ve gizlenmiyor.
    */
    return t(`workspace.team.roleGuide.ability.${permission}` as WorkspaceTranslationKey);
}

const LIST_CLASS = 'flex flex-col gap-[var(--space-1)] text-body text-fg-secondary';

function AbilityList({ heading, permissions }: { heading: string; permissions: string[] }) {
    if (permissions.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-[var(--space-1)]">
            <p className="text-body font-bold text-fg">{heading}</p>
            <ul className={LIST_CLASS}>
                {permissions.map((permission) => (
                    <li key={permission}>{abilityLabel(permission)}</li>
                ))}
            </ul>
        </div>
    );
}

function RoleDetail({ roleKey, initiallyOpen }: { roleKey: TeamRoleKey; initiallyOpen: boolean }) {
    const [open, setOpen] = useState(initiallyOpen);
    const row = MATRIX_ROLES.find((entry) => entry.key === roleKey);
    const panelId = `role-detail-${roleKey}`;

    /*
        Matriste karşılığı olmayan bir rol için AÇILIR BÖLÜM ÇİZİLMEZ.
        Ekranda bir rol adı olup koddaki matriste karşılığı olmaması
        beklenmeyen bir durumdur ve kapı onu ayrıca yakalar; yine de o gün
        boş bir liste göstermek, "bu rol hiçbir şey yapamaz" demek olurdu.
    */
    if (row === undefined) {
        return null;
    }

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <button
                type="button"
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => setOpen((current) => !current)}
                className={[
                    'flex min-h-[44px] w-full items-center gap-[var(--space-2)]',
                    'text-start text-body font-medium text-fg-secondary underline',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                ].join(' ')}
            >
                <span aria-hidden="true" className="flex-none">
                    {open ? (
                        <CaretDown size={16} weight="regular" />
                    ) : (
                        <CaretRight size={16} weight="regular" />
                    )}
                </span>
                {t('workspace.team.roleGuide.detail.show')}
            </button>

            {open && (
                <div id={panelId} className="flex flex-col gap-[var(--space-3)]">
                    <AbilityList
                        heading={t('workspace.team.roleGuide.detail.can')}
                        permissions={row.granted}
                    />
                    <AbilityList
                        heading={t('workspace.team.roleGuide.detail.cannot')}
                        permissions={row.denied}
                    />
                    <p className="text-meta text-fg-muted">
                        {t('workspace.team.roleGuide.detail.source')}
                    </p>
                </div>
            )}
        </div>
    );
}

export type TeamRoleGuideProps = {
    /** Ekranda gerçekten karşılığı olan roller. Sıra bileşenin kendi işidir. */
    roles: TeamRoleKey[];
    /**
     * Ayrıntısı AÇIK başlayacak roller. Üründe boştur.
     *
     * Var olmasının sebebi ölçümdür: dar ekran denetimi (`scripts/mobile-ux-audit`)
     * Storybook'un statik çıktısını ölçer ve bir düğmeye basmaz. Bu prop
     * olmasaydı, kartın EN GENİŞ hâli — yirmi üç satırlık açık liste — 320
     * pikselde hiç ölçülemez, sonuç "geçti" değil "bilinmiyor" olurdu
     * (`docs/48`). Ölçülemeyen bir hâl, yeşil gösterilemez.
     */
    initiallyExpanded?: TeamRoleKey[];
};

/**
 * "ROLLER NE YAPABİLİR?" — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Takım"`; cümleler `docs/109` §6.4).
 *
 * Rolün ne yapabildiği yalnız davet alanının altında, SEÇİLİ rol için tek
 * satır olarak yazıyordu. "Yönetici mi Editör mü vereyim?" sorusunun cevabı
 * iki cümlenin FARKINDAYDI ve o farkı görmek için seçeneği değiştirip
 * cümleyi yeniden okumak gerekiyordu. Kart dördünü de aynı anda gösterir.
 *
 * ═══ TARİFİN ALTINDAKİ İKİ LİSTE (`docs/139` §5) ═══
 *
 * Cümleler bir süre TEK BAŞINAYDI ve elle yazılmışlardı. O gün doğrulardı;
 * ama `RolePermissions`'a bir izin eklendiğinde sessizce eskiyorlardı ve
 * hiçbir kapı bunu görmüyordu — beş cümle, beş potansiyel yalandı.
 *
 * Cümleler kaldı çünkü bir rolün tarifi gerekir ve yirmi üç satırlık bir
 * liste onun yerine geçmez. Altlarına, KODDAN ÜRETİLEN iki liste eklendi:
 * "yapabilir" ve "yapamaz". İkisi de aynı üretimden çıkar, dolayısıyla
 * birbirinden ayrışamazlar.
 *
 * ═══ NEDEN AÇILIR KAPANIR, NEDEN IZGARA DEĞİL ═══
 *
 * Beş rolün yirmi üçer satırı aynı anda çizilseydi kart 320 pikselde ekran
 * boyu bir listeye dönerdi; sahibin sorusu ise tek bir rolle ilgilidir.
 * Izgara (rol × izin) ise dar ekranda yatay kaydırma olmadan çizilemez ve
 * yatay kaydırma, dokunmalı bir ekranda kullanıcının bir daha bulamayacağı
 * içerik demektir. Izgara karşılaştırma aracıdır ve karşılaştırma yapan
 * okuyucu `docs/139`'u okur.
 */
export function TeamRoleGuide({ roles, initiallyExpanded = [] }: TeamRoleGuideProps) {
    const shown = ROLE_ORDER.filter((key) => roles.includes(key));

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <h2 className="text-subsection font-bold text-fg">
                {t('workspace.team.roleGuide.heading')}
            </h2>

            <ul className="flex flex-col gap-[var(--space-3)]">
                {shown.map((key) => {
                    const role = ROLES[key];

                    return (
                        <li key={key} className="flex flex-col gap-[var(--space-1)]">
                            <div className="flex items-start gap-[var(--space-2)]">
                                {/*
                                    İkon SÜSLEMEDİR: rolün adı hemen yanında
                                    yazıyor ve ekran okuyucunun "taç simgesi"
                                    demesi hiçbir şey eklemezdi.
                                */}
                                <span aria-hidden="true" className="flex-none text-fg-muted">
                                    {role.icon}
                                </span>
                                <p className="text-body text-fg-secondary">
                                    <span className="font-bold text-fg">{t(role.nameKey)}</span>
                                    {' — '}
                                    <span>{t(role.descriptionKey)}</span>
                                </p>
                            </div>

                            <RoleDetail
                                roleKey={key}
                                initiallyOpen={initiallyExpanded.includes(key)}
                            />
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default TeamRoleGuide;
