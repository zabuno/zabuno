import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import { Button } from '../../catalog/forms/micro/Button';
import type { DashboardMenuTree } from './DashboardPage';
import { PageState } from './shared/PageState';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { RatingReplyEditor } from './ratings/RatingReplyEditor';
import {
    computedAtLabel,
    hasScore,
    scoreLabel,
    type RatingRow,
} from './ratings/ratingPresentation';

/**
 * SAHİBİN PUAN EKRANI — `docs/122` Y4, `docs/116` P5/P6/Ö3.
 *
 * Uç aylardır ayaktaydı (`GET /api/workspaces/{w}/menus/{m}/ratings`) ve
 * ekran yoktu: misafir masada oy veriyor, sahip onu hiçbir yerde göremiyordu.
 * Ölçülen bir şeyin görülememesi, ölçülmemesinden farksızdır.
 *
 * ═══ EKRAN KARAR VERMEZ, KARARI OKUR ═══
 *
 * Eşik sayısı, sinyal ağırlığı ve algoritmanın kendisi bu ekrana HİÇ
 * inmiyor. Sunucu ya bir puan gönderir ya `null`; ekranın yapabileceği tek
 * yanlış, o `null`ı sıfıra çevirmek olurdu — ve bu dosya onu yapmıyor.
 * İki yüzeye iki farklı eşik koysaydık sahip "misafir 4,2 görüyor, ben neden
 * görmüyorum?" sorusunun cevabını hiçbir yerde bulamazdı.
 *
 * ═══ EKRANDA BİR "PUANI KALDIR" DÜĞMESİ YOKTUR ═══
 *
 * Ve bu bir eksiklik değil, `docs/116` §4'ün kendisi: sahip yanıt verir,
 * kaldıramaz. Kaldırılabilen bir ortalama, misafire "bu restoranın seçtiği
 * oyların ortalaması" olarak gösterilir; yani bir ölçüm değil, bir reklam.
 * Kural sayfada BİR CÜMLEYLE de yazılıdır, çünkü sahip kaynak kodu okumaz —
 * düğmeyi arar, bulamaz ve sebebini bilmeden dolaşır.
 */

export type RatingsPageProps = {
    workspaceId: number;
    /** Puanlar bir MENÜNÜN satırlarına dayanır; menü yoksa okunacak bir şey yok. */
    menuTree: DashboardMenuTree | null;
    can: (permission: string) => boolean;
    onNavigateToSection: (section: string) => void;
};

type Status = 'loading' | 'ready' | 'error';

type RatingsBody = {
    data?: RatingRow[];
    algorithmVersion?: string;
    scaleMax?: number;
};

export function RatingsPage({ workspaceId, menuTree, can, onNavigateToSection }: RatingsPageProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [rows, setRows] = useState<RatingRow[]>([]);
    /*
        SÜRÜM UYDURULMAZ. Sunucu söylemediyse ekran bir sürüm YAZMAZ — sahte
        bir sürüm damgası, Ö3'ün çözmek istediği sorunun daha kötüsüdür:
        "puan neden düştü?" sorusuna yanlış bir cevap verir.
    */
    const [algorithmVersion, setAlgorithmVersion] = useState<string | null>(null);

    const allowed = can('rating.view');
    const menuId = menuTree?.id ?? null;

    /*
        TAZELEME BİR SAYAÇLA İSTENİR, BİR İŞLEV ÇAĞRISIYLA DEĞİL.

        Etkinin gövdesinde EŞZAMANLI bir durum yazması yok ve olmamalı:
        React onu bir sonraki çizime zincirler, ekran boşuna iki kez çizilir.
        Yükleme durumunu bu yüzden düğmenin kendisi kuruyor (bir olay
        işleyicisi, bir etki değil) ve sayaç etkiyi yeniden çalıştırıyor.
        Sipariş şalterinde de aynı desen var.
    */
    const [reloadToken, setReloadToken] = useState(0);

    useEffect(() => {
        if (!allowed || menuId === null) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/menus/${String(menuId)}/ratings`,
                    { headers: { Accept: 'application/json' } },
                );

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as RatingsBody;

                if (cancelled) {
                    return;
                }

                setRows(Array.isArray(body.data) ? body.data : []);
                setAlgorithmVersion(
                    typeof body.algorithmVersion === 'string' && body.algorithmVersion !== ''
                        ? body.algorithmVersion
                        : null,
                );
                setStatus('ready');
            } catch {
                if (!cancelled) {
                    setStatus('error');
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [allowed, menuId, workspaceId, reloadToken]);

    const replaceReply = useCallback((productId: number, body: string | null) => {
        setRows((current) =>
            current.map((row) =>
                row.productId === productId
                    ? {
                          ...row,
                          /*
                              YAYIN ANI SUNUCUNUNDUR. Burada `new Date()` yazmak,
                              sunucunun henüz söylemediği bir zamanı ekrana
                              basmak olurdu; bir sonraki yükleme onu düzeltirdi
                              ve arada sahip yanlış bir saat okurdu.
                          */
                          reply: body === null ? null : { body, publishedAt: null },
                      }
                    : row,
            ),
        );
    }, []);

    /*
        YAPILAMAYAN İŞ ÇİZİLMEZ (`docs/59`). Bu üç hâlde tek bir istek bile
        kurulmaz: sunucu zaten 404 döner, ekranın işi o 404'ü sahibe
        yaşatmamaktır.
    */
    if (!allowed) {
        return (
            <Frame>
                <PageState
                    kind="permission"
                    screen="ratings"
                    title={t('workspace.ratings.permission.title')}
                    whyNoAction={t('workspace.ratings.permission.whyNoAction')}
                />
            </Frame>
        );
    }

    if (menuId === null) {
        return (
            <Frame>
                <PageState
                    kind="prerequisite"
                    screen="ratings"
                    title={t('workspace.ratings.prerequisite.title')}
                    description={t('workspace.ratings.prerequisite.description')}
                    action={
                        <Button size="sm" onClick={() => onNavigateToSection('menu')}>
                            {t('workspace.ratings.empty.action')}
                        </Button>
                    }
                />
            </Frame>
        );
    }

    if (status === 'loading') {
        return (
            <Frame>
                <PageState kind="loading" screen="ratings" title={t('workspace.ratings.loading')} />
            </Frame>
        );
    }

    if (status === 'error') {
        return (
            <Frame>
                <PageState
                    kind="error"
                    screen="ratings"
                    title={t('workspace.ratings.error.title')}
                    description={t('workspace.ratings.error.description')}
                    action={
                        <Button
                            size="sm"
                            onClick={() => {
                                setStatus('loading');
                                setReloadToken((token) => token + 1);
                            }}
                        >
                            {t('workspace.ratings.retry')}
                        </Button>
                    }
                />
            </Frame>
        );
    }

    if (rows.length === 0) {
        return (
            <Frame>
                <PageState
                    kind="empty"
                    screen="ratings"
                    title={t('workspace.ratings.empty.title')}
                    description={t('workspace.ratings.empty.description')}
                    action={
                        <Button size="sm" onClick={() => onNavigateToSection('menu')}>
                            {t('workspace.ratings.empty.action')}
                        </Button>
                    }
                />
            </Frame>
        );
    }

    return (
        <Frame>
            {/*
                Ö3 — HANGİ KURALIN ÇIKTISINA BAKILIYOR, SAYFANIN BAŞINDA YAZAR.
                Satır satır tekrar edilmez: sürüm bütün listeyi üreten tek
                karardır ve her satıra basmak onu okunmayan bir süse çevirirdi.
            */}
            {algorithmVersion !== null ? (
                <div className="flex flex-col gap-[var(--space-1)]">
                    <p className="text-body font-bold text-fg">
                        {t('workspace.ratings.method', { version: algorithmVersion })}
                    </p>
                    <p className="max-w-[64ch] text-meta text-fg-secondary">
                        {t('workspace.ratings.method.help')}
                    </p>
                </div>
            ) : null}

            <p className="max-w-[64ch] text-meta text-fg-secondary">
                {t('workspace.ratings.noRemoval')}
            </p>

            <ul className="flex list-none flex-col gap-[var(--space-4)] p-0">
                {rows.map((row) => (
                    <li
                        key={row.menuItemId}
                        className="flex min-w-0 flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] px-[var(--space-5)] py-[var(--space-5)]"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-[var(--space-2)]">
                            <h3 className="text-body font-bold tracking-tight text-fg">
                                {row.productName}
                            </h3>
                            <p
                                className={
                                    hasScore(row)
                                        ? 'text-body font-bold text-fg'
                                        : 'text-meta text-fg-muted'
                                }
                            >
                                {scoreLabel(row)}
                            </p>
                        </div>

                        {/*
                            SAYIM EŞİK ALTINDA DA YAZILIR. Gizlenen şey puan,
                            yani henüz güvenilmeyen türetilmiş değerdir; kaç oy
                            geldiği bilinen bir ölçümdür ve sahibin "eşiğe ne
                            kadar kaldı?" sorusunun tek cevabıdır.
                        */}
                        <div className="flex flex-wrap gap-x-[var(--space-3)] gap-y-[var(--space-1)]">
                            <span className="text-meta text-fg-secondary">
                                {t('workspace.ratings.votes', {
                                    count: String(row.signalCount),
                                })}
                            </span>
                            {/*
                                Ö3'ün ikinci yarısı: sayının YAŞI. Türetilmiş
                                puan bir işin çıktısıdır; iş çalışmadıysa
                                ekrandaki sayı dünkü sayıdır ve donmuş bir
                                ekranla dolu bir ekran aynı görünür.
                            */}
                            <span className="text-meta text-fg-secondary">
                                {computedAtLabel(row)}
                            </span>
                        </div>

                        {hasScore(row) ? null : (
                            <p className="max-w-[64ch] text-meta text-fg-muted">
                                {t('workspace.ratings.notEnough.help')}
                            </p>
                        )}

                        {/*
                            ANAHTAR YAYINDAKİ CÜMLEDİR.

                            Kutuyu sunucunun bildiği cümleye döndürmenin bir
                            etkiye ihtiyacı yok: cümle değiştiğinde React
                            kutuyu baştan kurar. Sahip YAZARKEN anahtar hiç
                            değişmez, yani yarım kalmış bir taslak kimsenin
                            elinden alınmaz.
                        */}
                        <RatingReplyEditor
                            key={row.reply?.body ?? ''}
                            workspaceId={workspaceId}
                            productId={row.productId}
                            body={row.reply?.body ?? null}
                            publishedAt={row.reply?.publishedAt ?? null}
                            onSaved={(body) => replaceReply(row.productId, body)}
                        />
                    </li>
                ))}
            </ul>
        </Frame>
    );
}

function Frame({ children }: { children: React.ReactNode }) {
    return (
        <div id="section-ratings">
            <WorkspacePageFrame
                measure="standard"
                title={t('workspace.ratings.heading')}
                description={t('workspace.ratings.description')}
            >
                {children}
            </WorkspacePageFrame>
        </div>
    );
}

export default RatingsPage;
