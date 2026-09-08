import { useId, useMemo, useState } from 'react';
import { Keyboard, SortAscending } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace-desktop';
import { RatingReplyEditor } from '../ratings/RatingReplyEditor';
import {
    computedAtLabel,
    hasScore,
    scoreLabel,
    type RatingRow,
} from '../ratings/ratingPresentation';
import type { RatingListSurfaceContext } from '../ratings/ratingListSurface';
import { useDesktopSelection } from './useDesktopSelection';

/**
 * PUANLAR — İŞARETLEYİCİ SÜRÜMÜ (`docs/151` §B).
 *
 * ## Bu neden dokunmalı listenin geniş hâli DEĞİL
 *
 * Aynı sayılara bakan iki farklı İŞ var (`docs/153` §2):
 *
 * - **Telefondaki sahip** bir ürünü açar, puanını görür, gerekiyorsa yanıt
 *   yazar. Ekranı kartlardan oluşur ve her kartın içinde kendi yanıt
 *   kutusu vardır.
 * - **Masaüstündeki sahip** MENÜYÜ tarar: kırk ürünün hangisi düşük,
 *   hangisi hiç oy almamış, hangisine yanıt yazılmamış — ve bulduğunda
 *   yanıtı listeyi KAYBETMEDEN yazar.
 *
 * ## Burada olan, telefonda OLMAYAN şeyler
 *
 * 1. **Sıralama.** "Hangisi en düşük?" sorusu telefonda ancak kaydırarak
 *    cevaplanır. Puanı olmayan satır sıralamada EN SONA gider: eşiği
 *    geçmemiş bir ürünü "en kötü" diye başa koymak, olmayan bir ölçümü bir
 *    yargıya çevirirdi.
 * 2. **Kalıcı yanıt bölmesi.** Yanıt kutusu satırın içinde değil, sağdaki
 *    bölmededir; sahip yazarken liste yerinde durur.
 * 3. **Klavyeyle gezinen liste** (roving tabindex; ok tuşları, Home/End).
 *
 * ## Çoklu seçim YOK ve bu bilinçli
 *
 * Öteki iki masaüstü ekranında toplu işlem var, burada yok: bir yanıt
 * SAHİBİN KENDİ CÜMLESİDİR ve toplu yazılamaz. "Sekiz ürüne aynı yanıtı
 * yapıştır" bir yetenek değil, misafire yazılmış sekiz aynı cümledir.
 * Bir ekranın masaüstünde farkı yoksa yazılmaz; farkı VARSA da yalnız
 * gerçekten olan fark yazılır (`docs/153` §9).
 *
 * ## Ortak kalan
 *
 * Eşik kuralı, puanın cümlesi, sayımın yaşı (`ratingPresentation`) ve yanıt
 * yazma yolunun tamamı (`RatingReplyEditor`) İKİ yüzeyde de aynıdır. Bu
 * dosyada tek bir `fetch` yoktur ve tek bir eşik kararı verilmez
 * (`docs/153` §4).
 */

const SORT_ORDER = ['lowest', 'most', 'name'] as const;

type RatingSort = (typeof SORT_ORDER)[number];

const SORT_LABEL_KEY = {
    lowest: 'workspace.ratings.desktop.sort.lowest',
    most: 'workspace.ratings.desktop.sort.most',
    name: 'workspace.ratings.desktop.sort.name',
} as const;

/**
 * PUANI OLMAYAN SATIR HER ZAMAN EN SONDA.
 *
 * `null` bir sayı değildir ve sıfır gibi davranamaz: eşiği geçmemiş bir
 * ürünü listenin başına koymak, "en kötü ürün bu" demek olurdu. Oysa
 * sunucunun söylediği tek şey "henüz söyleyemem".
 */
function compareRatings(a: RatingRow, b: RatingRow, sort: RatingSort): number {
    if (sort === 'name') {
        return a.productName.localeCompare(b.productName);
    }

    if (sort === 'most') {
        return b.signalCount - a.signalCount;
    }

    const left = hasScore(a) ? a.score : null;
    const right = hasScore(b) ? b.score : null;

    if (left === null && right === null) return 0;
    if (left === null) return 1;
    if (right === null) return -1;

    return left - right;
}

export function RatingListDesktop({ workspaceId, rows, onReplySaved }: RatingListSurfaceContext) {
    const listLabelId = useId();
    const [sort, setSort] = useState<RatingSort>('lowest');

    const ordered = useMemo(
        () => [...rows].sort((a, b) => compareRatings(a, b, sort)),
        [rows, sort],
    );

    const ids = ordered.map((row) => row.menuItemId);
    const selection = useDesktopSelection(ids);
    const activeRow = ordered.find((row) => row.menuItemId === selection.activeId) ?? null;

    return (
        <section
            aria-label={t('workspace.ratings.desktop.list')}
            className="dk-frame flex flex-col gap-[var(--space-3)]"
        >
            <div className="dk-toolbar">
                <p className="text-meta text-fg-secondary" id={listLabelId}>
                    {t('workspace.ratings.desktop.list')}
                </p>

                <div className="flex items-center gap-[var(--space-3)]">
                    {/*
                        SIRALAMA TEK DÜĞMEDE DÖNER — üç seçenek için açılır
                        liste açmak, bir tıklamayı ikiye çıkarırdı.
                    */}
                    <button
                        type="button"
                        className="dk-btn"
                        onClick={() =>
                            setSort(
                                (current) =>
                                    SORT_ORDER[
                                        (SORT_ORDER.indexOf(current) + 1) % SORT_ORDER.length
                                    ] ?? 'lowest',
                            )
                        }
                    >
                        <SortAscending size={16} weight="regular" aria-hidden="true" />
                        {t('workspace.ratings.desktop.sort', {
                            label: t(SORT_LABEL_KEY[sort]),
                        })}
                    </button>

                    <p className="dk-hint text-meta">
                        <Keyboard size={16} weight="regular" aria-hidden="true" />
                        {t('workspace.ratings.desktop.shortcuts')}
                    </p>
                </div>
            </div>

            <div className="dk-split">
                <ul role="listbox" aria-labelledby={listLabelId} className="dk-listbox">
                    {ordered.map((row) => (
                        <li
                            key={row.menuItemId}
                            ref={selection.registerRef(row.menuItemId)}
                            role="option"
                            aria-selected={row.menuItemId === selection.activeId}
                            /*
                                ROVING TABINDEX: listeye Tab ile BİR KEZ
                                girilir, içinde oklarla gezinilir. Kırk
                                ürünlük bir menüde her satır odaklanabilir
                                olsaydı, yanıt kutusuna ulaşmak kırk Tab
                                demekti.
                            */
                            tabIndex={row.menuItemId === selection.activeId ? 0 : -1}
                            data-active={row.menuItemId === selection.activeId ? 'true' : 'false'}
                            data-columns="ratings"
                            className="dk-row"
                            onClick={() => selection.activate(row.menuItemId, false, false)}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    selection.move(1, false);

                                    return;
                                }

                                if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    selection.move(-1, false);

                                    return;
                                }

                                if (event.key === 'Home') {
                                    event.preventDefault();
                                    selection.move(-ordered.length, false);

                                    return;
                                }

                                if (event.key === 'End') {
                                    event.preventDefault();
                                    selection.move(ordered.length, false);
                                }
                            }}
                        >
                            <span className="truncate text-body font-medium text-fg">
                                {row.productName}
                            </span>
                            <span
                                className={
                                    hasScore(row)
                                        ? 'text-body font-bold text-fg'
                                        : 'text-meta text-fg-muted'
                                }
                            >
                                {scoreLabel(row)}
                            </span>
                            {/*
                                SAYIM EŞİK ALTINDA DA YAZILIR. Gizlenen şey
                                puandır — henüz güvenilmeyen türetilmiş
                                değer; kaç oy geldiği bilinen bir ölçümdür ve
                                "eşiğe ne kadar kaldı?" sorusunun tek
                                cevabıdır.
                            */}
                            <span className="text-meta text-fg-secondary">
                                {t('workspace.ratings.votes', {
                                    count: String(row.signalCount),
                                })}
                            </span>
                        </li>
                    ))}
                </ul>

                <aside
                    aria-label={t('workspace.ratings.desktop.detail.region')}
                    className="dk-pane"
                >
                    {activeRow === null ? (
                        <p className="text-meta text-fg-muted">
                            {t('workspace.ratings.desktop.detail.empty')}
                        </p>
                    ) : (
                        <div className="flex flex-col gap-[var(--space-2)]">
                            <h3 className="text-section font-bold text-fg">
                                {activeRow.productName}
                            </h3>
                            <p
                                className={
                                    hasScore(activeRow)
                                        ? 'text-body font-bold text-fg'
                                        : 'text-meta text-fg-muted'
                                }
                            >
                                {scoreLabel(activeRow)}
                            </p>
                            <p className="text-meta text-fg-secondary">
                                {computedAtLabel(activeRow)}
                            </p>

                            {hasScore(activeRow) ? null : (
                                <p className="text-meta text-fg-muted">
                                    {t('workspace.ratings.notEnough.help')}
                                </p>
                            )}

                            {/*
                                ANAHTAR YAYINDAKİ CÜMLEDİR — dokunmalı
                                sürümdeki kararın aynısı. Sahip YAZARKEN
                                anahtar değişmez, yani yarım kalmış bir
                                taslak kimsenin elinden alınmaz. Ürün
                                kimliği de anahtara girer: bölme kalıcı
                                olduğu için başka bir satıra geçildiğinde
                                kutunun taslağı taşınmamalı.
                            */}
                            <RatingReplyEditor
                                key={`${String(activeRow.productId)}:${activeRow.reply?.body ?? ''}`}
                                workspaceId={workspaceId}
                                productId={activeRow.productId}
                                body={activeRow.reply?.body ?? null}
                                publishedAt={activeRow.reply?.publishedAt ?? null}
                                onSaved={(body) => onReplySaved(activeRow.productId, body)}
                            />
                        </div>
                    )}
                </aside>
            </div>
        </section>
    );
}

export default RatingListDesktop;
