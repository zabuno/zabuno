import type { ReactNode } from 'react';
import { Crown, ClipboardText, PencilSimple, CookingPot, Eye } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import type { WorkspaceTranslationKey } from '../../../../i18n/workspace';

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

export type TeamRoleGuideProps = {
    /** Ekranda gerçekten karşılığı olan roller. Sıra bileşenin kendi işidir. */
    roles: TeamRoleKey[];
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
 * Cümleler deponun GERÇEK izin matrisiyle (`RolePermissions`) uyumludur:
 * Yönetici faturayı GÖRÜR ama YÖNETEMEZ, o yüzden "fatura yok" değil
 * "faturaya dokunamaz" yazar.
 */
export function TeamRoleGuide({ roles }: TeamRoleGuideProps) {
    const shown = ROLE_ORDER.filter((key) => roles.includes(key));

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <h2 className="text-subsection font-bold text-fg">
                {t('workspace.team.roleGuide.heading')}
            </h2>

            <ul className="flex flex-col gap-[var(--space-2)]">
                {shown.map((key) => {
                    const role = ROLES[key];

                    return (
                        <li key={key} className="flex items-start gap-[var(--space-2)]">
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
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default TeamRoleGuide;
