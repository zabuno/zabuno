import { useCallback, useId, useState } from 'react';
import { Keyboard, Trash, UserSwitch } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace-desktop';
import { PageState } from '../shared/PageState';
import type { TeamMember, TeamMemberListSurfaceContext } from '../team/memberListSurface';
import {
    DesktopContextMenu,
    DesktopMenuItem,
    isContextMenuKey,
    menuPositionFor,
    type DesktopMenuPosition,
} from './DesktopContextMenu';
import { useDesktopSelection } from './useDesktopSelection';

/**
 * EKİP ÜYELERİ — İŞARETLEYİCİ SÜRÜMÜ (`docs/151` §B).
 *
 * ## Bu neden dokunmalı listenin geniş hâli DEĞİL
 *
 * Aynı kişilere bakan iki farklı İŞ var (`docs/153` §2):
 *
 * - **Telefondaki sahip** tek bir kişiyle ilgilenir: birini çıkarır ya da
 *   birinin rolünü düzeltir. Ekranı bir kart listesidir ve her kartta
 *   onayı, sonucu ve geri alma yolu vardır.
 * - **Masaüstündeki sahip** EKİBİ yönetir: sezon başında sekiz kişinin
 *   rolünü aynı anda değiştirir, ayrılan üçünü birden çıkarır. İşi tarama
 *   ve toplu işlemdir.
 *
 * ## Burada olan, telefonda OLMAYAN şeyler
 *
 * 1. **Çok sütunlu tablo** — ad, e-posta ve rol AYNI satırda okunur;
 *    telefonda üçü alt alta durur çünkü orada üç sütunluk yer yoktur.
 * 2. **Klavyeyle gezinen liste** (roving tabindex; ok tuşları, Home/End).
 * 3. **Çoklu seçim:** Boşluk, Shift+ok aralık, Ctrl/Cmd+A.
 * 4. **Toplu rol değişimi ve toplu çıkarma.**
 * 5. **Sağ tık bağlam menüsü ve klavye karşılığı** (Shift+F10 / Menü tuşu).
 *
 * ## Sınırlar AYNEN korunur
 *
 * Bu ekran yeni bir yetki AÇMAZ. Sunucunun kuralları neyse o çizilir:
 *
 * - **Sahip satırı hiçbir toplu işleme girmez.** Sahiplik silinmez,
 *   DEVREDİLİR — ve devir tek tek, onayla yapılan bir iştir.
 * - **Yalnız çalışma alanının sahibi** çıkarabilir (`viewerIsOwner`);
 *   yönetici bu düğmeyi hiç görmez, çünkü uç nokta ona 403 döner.
 * - **Yalnız sunucunun çıkarabildiği roller** çıkarılabilir
 *   (`removableRoles`).
 *
 * Toplu işlem bu üç sınırı toplu hâlde de uygular ve ATLADIĞINI SÖYLER:
 * sessizce atlanan bir satır, sahibe yapılmamış bir işi yapılmış gösterir.
 *
 * ## Ortak kalan
 *
 * Veri (`TeamPage`), yazma yolları (`onRemoveMember`, `onChangeRole`,
 * `onTransferOwnership`) ve SONUÇ CÜMLELERİ İKİ yüzeyde de aynıdır. Bu
 * dosyada tek bir `fetch` yoktur ve tek bir sonuç cümlesi seçilmez —
 * hangi cümlenin hangi sonuca yazılacağı bir ürün kararıdır ve bağlamdan
 * gelir (`docs/153` §4).
 */

type Menu = { memberId: number } & DesktopMenuPosition;

type Outcome = { ok: number; failed: number };

export function TeamMemberTableDesktop({
    status,
    members,
    label,
    loadingText,
    errorText,
    emptyText,
    onRemoveMember,
    onChangeRole,
    assignableRoles,
    roleLabelFor,
    roleErrorText,
    removeButtonText,
    removeErrorText,
    removeForbiddenText,
    removeMissingText,
    removeSuccessText,
    removableRoles,
    viewerIsOwner,
}: TeamMemberListSurfaceContext) {
    const listLabelId = useId();
    const roleFieldId = useId();
    const [menu, setMenu] = useState<Menu | null>(null);
    const [busyIds, setBusyIds] = useState<number[]>([]);
    const [outcome, setOutcome] = useState<Outcome | null>(null);
    const [keptCount, setKeptCount] = useState(0);
    const [notice, setNotice] = useState<string | null>(null);

    const ids = members.map((member) => member.id);
    const selection = useDesktopSelection(ids);

    const removable = useCallback(
        (member: TeamMember) => viewerIsOwner && removableRoles.includes(member.role),
        [removableRoles, viewerIsOwner],
    );

    const runBulk = useCallback(
        async (targets: number[], action: 'remove' | { role: string }): Promise<void> => {
            const rows = targets
                .map((id) => members.find((member) => member.id === id))
                .filter((member): member is TeamMember => member !== undefined);

            /*
                SAHİP SATIRI VE ÇIKARILAMAYAN ROLLER ÖNCE AYRILIR.

                Sunucuya gönderilip 403 almak da "çalışırdı" ama sahibe
                hiçbir şey anlatmazdı: sekiz istekten üçü sessizce
                reddedilir ve ekranda yalnız bir sayı değişirdi.
            */
            const eligible =
                action === 'remove'
                    ? rows.filter((member) => removable(member))
                    : rows.filter((member) => member.role !== 'owner');
            const kept = rows.length - eligible.length;

            if (eligible.length === 0) {
                setKeptCount(kept);
                setOutcome({ ok: 0, failed: 0 });

                return;
            }

            setBusyIds(eligible.map((member) => member.id));
            setNotice(null);

            const summary: Outcome = { ok: 0, failed: 0 };
            let lastNotice: string | null = null;

            for (const member of eligible) {
                const result =
                    action === 'remove'
                        ? await onRemoveMember(member.id)
                        : await onChangeRole(member.id, action.role);

                if (result === 'success') {
                    summary.ok += 1;
                    lastNotice = action === 'remove' ? removeSuccessText : null;

                    continue;
                }

                summary.failed += 1;

                /*
                    SONUÇ CÜMLESİ SUNUCUNUN CEVABINDAN SEÇİLİR ve üç ayrı
                    cevap üç ayrı cümledir (FF-138d): geçici arıza tekrar
                    denemeye çağırır, "yetkin yok" ve "o üyelik yok"
                    çağırmaz. Tek bir "olmadı" cümlesi, sahibi sonu olmayan
                    bir döngüye sokardı.
                */
                if (result === 'forbidden') {
                    lastNotice = removeForbiddenText;
                } else if (result === 'missing') {
                    lastNotice = removeMissingText;
                } else if (action === 'remove') {
                    lastNotice = removeErrorText;
                } else {
                    lastNotice = roleErrorText;
                }
            }

            setBusyIds([]);
            setKeptCount(kept);
            setOutcome(summary);
            setNotice(lastNotice);
            selection.clear();
        },
        [
            members,
            onChangeRole,
            onRemoveMember,
            removable,
            removeErrorText,
            removeForbiddenText,
            removeMissingText,
            removeSuccessText,
            roleErrorText,
            selection,
        ],
    );

    if (status === 'loading') {
        return <PageState kind="loading" screen="team_members" title={loadingText} />;
    }

    if (status === 'error') {
        return (
            <PageState
                kind="error"
                screen="team_members"
                title={errorText}
                whyNoAction={errorText}
            />
        );
    }

    if (members.length === 0) {
        return (
            <PageState
                kind="empty"
                screen="team_members"
                title={emptyText}
                whyNoAction={emptyText}
            />
        );
    }

    const menuTargets = menu === null ? [] : selection.targetsFor(menu.memberId);
    const menuMembers = menuTargets
        .map((id) => members.find((member) => member.id === id))
        .filter((member): member is TeamMember => member !== undefined);
    const menuRemovable =
        menuMembers.length > 0 && menuMembers.every((member) => removable(member));

    return (
        <section aria-label={label} className="dk-frame flex flex-col gap-[var(--space-3)]">
            <div className="dk-toolbar">
                <p className="text-meta text-fg-secondary" id={listLabelId}>
                    {label}
                </p>
                {/*
                    KISAYOLLAR YAZILI DURUR: klavyeyle çalışan bir liste,
                    varlığı söylenmedikçe yoktur. Telefonda bu satır hiç
                    çizilmez.
                */}
                <p className="dk-hint text-meta">
                    <Keyboard size={16} weight="regular" aria-hidden="true" />
                    {t('workspace.team.members.desktop.shortcuts')}
                </p>
            </div>

            {selection.selectedIds.length > 0 ? (
                <div
                    role="group"
                    aria-label={t('workspace.team.members.desktop.bulkRegion')}
                    className="dk-bulkbar"
                >
                    <p className="text-body font-medium text-fg">
                        {t('workspace.team.members.desktop.selected', {
                            count: String(selection.selectedIds.length),
                        })}
                    </p>

                    <label htmlFor={roleFieldId} className="text-meta text-fg-secondary">
                        {t('workspace.team.members.desktop.bulkRole', {
                            count: String(selection.selectedIds.length),
                        })}
                    </label>
                    {/*
                        AÇILIR LİSTE SEÇİM YAPMAZ, İŞİ YAPAR: rol seçilir
                        seçilmez uygulanır. İkinci bir "Uygula" düğmesi,
                        toplu işlemin kazandırdığı adımı geri alırdı.
                    */}
                    <select
                        id={roleFieldId}
                        className="dk-btn"
                        value=""
                        onChange={(event) => {
                            const role = event.target.value;

                            if (role !== '') {
                                void runBulk(selection.selectedIds, { role });
                            }
                        }}
                    >
                        <option value="">{t('workspace.team.members.desktop.column.role')}</option>
                        {assignableRoles.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    {viewerIsOwner ? (
                        <button
                            type="button"
                            data-tone="danger"
                            className="dk-btn"
                            onClick={() => void runBulk(selection.selectedIds, 'remove')}
                        >
                            <Trash size={16} weight="bold" aria-hidden="true" />
                            {removeButtonText}
                        </button>
                    ) : null}

                    <button type="button" className="dk-btn" onClick={selection.clear}>
                        {t('workspace.team.members.desktop.clearSelection')}
                    </button>
                </div>
            ) : null}

            {outcome !== null ? (
                /*
                    ÜÇ AYRI CÜMLE, ÜÇ AYRI SATIR — tek dizede birleştirilmez.
                    Birleştirme ayıracı çeviriden kaçırır ve sağdan sola
                    yazılan dillerde parçaların sırasını dondurur (FF-213).
                */
                <div role="status" className="flex flex-col gap-1">
                    <p className="text-body text-fg-secondary">
                        {t('workspace.team.members.desktop.outcome', {
                            ok: String(outcome.ok),
                            failed: String(outcome.failed),
                        })}
                    </p>
                    {keptCount > 0 ? (
                        <p className="text-body text-fg-secondary">
                            {t('workspace.team.members.desktop.kept', {
                                count: String(keptCount),
                            })}
                        </p>
                    ) : null}
                    {notice === null ? null : (
                        <p className="text-body text-fg-secondary">{notice}</p>
                    )}
                </div>
            ) : null}

            <ul
                role="listbox"
                aria-multiselectable="true"
                aria-labelledby={listLabelId}
                className="dk-listbox"
            >
                {/*
                    SÜTUN BAŞLIKLARI EKRAN OKUYUCUYA GİZLİ.

                    Liste `listbox` semantiğinde ve orada başlık satırı diye
                    bir şey yoktur; her satır kendi erişilebilir adını
                    taşır. Başlıklar GÖRSEL bir yardımdır — görünmez bir
                    tablo semantiği taklit etmek, ekran okuyucuya olmayan bir
                    yapı anlatırdı.

                    `role="presentation"` VE `aria-hidden` birlikte yazılır
                    ve ikisi de gerekli: ilki bu satırı listenin "yalnız
                    seçenek taşır" kuralının dışına çıkarır (aksi hâlde yapı
                    geçersiz olurdu), ikincisi metnini hiç okutmaz.
                */}
                <li role="presentation" aria-hidden="true" className="dk-row" data-columns="team">
                    <span className="text-meta font-medium text-fg-secondary">
                        {t('workspace.team.members.desktop.column.name')}
                    </span>
                    <span className="text-meta font-medium text-fg-secondary">
                        {t('workspace.team.members.desktop.column.email')}
                    </span>
                    <span className="text-meta font-medium text-fg-secondary">
                        {t('workspace.team.members.desktop.column.role')}
                    </span>
                    <span />
                </li>

                {members.map((member) => (
                    <TeamRowDesktop
                        key={member.id}
                        member={member}
                        active={member.id === selection.activeId}
                        selected={selection.isSelected(member.id)}
                        busy={busyIds.includes(member.id)}
                        canRemove={removable(member)}
                        roleLabel={roleLabelFor(member.name)}
                        registerRef={selection.registerRef(member.id)}
                        onActivate={(additive, range) =>
                            selection.activate(member.id, additive, range)
                        }
                        onRemove={() => void runBulk(selection.targetsFor(member.id), 'remove')}
                        onContextMenu={(position) => {
                            selection.activate(member.id, false, false);
                            setMenu({ memberId: member.id, ...position });
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                selection.move(1, event.shiftKey);

                                return;
                            }

                            if (event.key === 'ArrowUp') {
                                event.preventDefault();
                                selection.move(-1, event.shiftKey);

                                return;
                            }

                            if (event.key === 'Home') {
                                event.preventDefault();
                                selection.move(-members.length, event.shiftKey);

                                return;
                            }

                            if (event.key === 'End') {
                                event.preventDefault();
                                selection.move(members.length, event.shiftKey);

                                return;
                            }

                            if (event.key === ' ') {
                                event.preventDefault();
                                selection.toggle(member.id);

                                return;
                            }

                            if (
                                (event.ctrlKey || event.metaKey) &&
                                event.key.toLowerCase() === 'a'
                            ) {
                                event.preventDefault();
                                selection.selectAll();

                                return;
                            }

                            if (isContextMenuKey(event)) {
                                event.preventDefault();
                                setMenu({
                                    memberId: member.id,
                                    ...menuPositionFor(selection.nodeFor(member.id)),
                                });

                                return;
                            }

                            if (event.key === 'Escape') {
                                selection.clear();
                            }
                        }}
                    />
                ))}
            </ul>

            {menu !== null ? (
                <DesktopContextMenu
                    label={t('workspace.team.members.desktop.menu')}
                    position={{ x: menu.x, y: menu.y }}
                    onClose={() => {
                        const target = menu.memberId;

                        setMenu(null);
                        selection.focusRow(target);
                    }}
                >
                    {assignableRoles.map((option, index) => (
                        <DesktopMenuItem
                            key={option.value}
                            autoFocus={index === 0}
                            onSelect={() => {
                                setMenu(null);
                                void runBulk(menuTargets, { role: option.value });
                            }}
                        >
                            <UserSwitch size={16} weight="regular" aria-hidden="true" />
                            {option.label}
                        </DesktopMenuItem>
                    ))}

                    {/*
                        ÇIKARMA MENÜDE YALNIZ GERÇEKTEN YAPILABİLİYORSA
                        DURUR: yapılamayan iş çizilmez (`docs/98` FF-74).
                        Sahip satırı seçime karışmışsa madde hiç görünmez —
                        görünseydi tıklayan kişi hiçbir şey olmadığını
                        görürdü.
                    */}
                    {menuRemovable ? (
                        <DesktopMenuItem
                            tone="danger"
                            onSelect={() => {
                                setMenu(null);
                                void runBulk(menuTargets, 'remove');
                            }}
                        >
                            <Trash size={16} weight="bold" aria-hidden="true" />
                            {removeButtonText}
                        </DesktopMenuItem>
                    ) : null}
                </DesktopContextMenu>
            ) : null}
        </section>
    );
}

function TeamRowDesktop({
    member,
    active,
    selected,
    busy,
    canRemove,
    roleLabel,
    registerRef,
    onActivate,
    onRemove,
    onContextMenu,
    onKeyDown,
}: {
    member: TeamMember;
    active: boolean;
    selected: boolean;
    busy: boolean;
    canRemove: boolean;
    roleLabel: string;
    registerRef: (node: HTMLElement | null) => void;
    onActivate: (additive: boolean, range: boolean) => void;
    onRemove: () => void;
    onContextMenu: (position: DesktopMenuPosition) => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLLIElement>) => void;
}) {
    return (
        <li
            ref={registerRef}
            role="option"
            aria-selected={selected}
            aria-label={t('workspace.team.members.desktop.row.label', {
                name: member.name,
                email: member.email,
                role: member.role,
            })}
            /*
                ROVING TABINDEX: listeye Tab ile BİR KEZ girilir, içinde
                oklarla gezinilir. Yirmi kişilik bir ekipte her satır
                odaklanabilir olsaydı, listeyi geçmek yirmi Tab demekti.
            */
            tabIndex={active ? 0 : -1}
            data-active={active ? 'true' : 'false'}
            data-columns="team"
            className="dk-row"
            onKeyDown={onKeyDown}
            onClick={(event) => onActivate(event.ctrlKey || event.metaKey, event.shiftKey)}
            onContextMenu={(event) => {
                event.preventDefault();
                onContextMenu({ x: event.clientX, y: event.clientY });
            }}
        >
            <span className="truncate text-body font-medium text-fg">{member.name}</span>
            <span className="truncate text-body text-fg-secondary">{member.email}</span>
            <span className="truncate text-body text-fg-secondary" aria-label={roleLabel}>
                {member.role}
            </span>

            {/*
                SATIR EYLEMİ `hover` ya da ODAKLA görünür — ve aynı eylem
                bağlam menüsünde ve toplu şeritte de var. `hover` bir kısa
                yoldur, tek yol değil.
            */}
            <span className="dk-rowactions">
                {canRemove ? (
                    <button
                        type="button"
                        tabIndex={-1}
                        disabled={busy}
                        onClick={(event) => {
                            event.stopPropagation();
                            onRemove();
                        }}
                        aria-label={t('workspace.team.members.desktop.remove.named', {
                            name: member.name,
                        })}
                        className="dk-btn"
                        data-tone="danger"
                    >
                        <Trash size={16} weight="bold" aria-hidden="true" />
                    </button>
                ) : null}
            </span>
        </li>
    );
}

export default TeamMemberTableDesktop;
