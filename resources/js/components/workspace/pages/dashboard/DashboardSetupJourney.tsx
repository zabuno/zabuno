import { useEffect, useState } from 'react';
import { CaretDown, Check } from '@phosphor-icons/react';
import { cn } from '../../../../lib/utils';
import { t } from '../../../../i18n/dashboard';
import type { BrandProfile } from '../../BrandEditForm';
import type { LocationProfile } from '../../LocationEditForm';
import type { DashboardMenuTree } from '../DashboardPage';

type DashboardSetupJourneyProps = {
    brand: BrandProfile | null;
    location: LocationProfile | null;
    dashboardMenuTree: DashboardMenuTree | null;
    workspaceId?: number;
    /**
     * Adımdan hedefe GERÇEK gezinti — `docs/70`.
     *
     * Satırlar önceden `#brand`, `#locations`, `#menu` gibi bağlantılar
     * taşıyordu. Uygulama adres tabanlı gezintiye geçtiğinden beri bu
     * bağlantılar HİÇBİR ŞEY yapmıyordu: o kimlikte bir öğe yok, tarayıcı
     * hiçbir yere kaymıyor, kullanıcı tıklıyor ve ekran duruyordu.
     *
     * Ölü bağlantı, kullanıcının ilk gördüğü ekranda duruyordu.
     */
    onNavigateToSection?: (section: string) => void;
    /** Pennant `novice-home`: kiracıda kapalıysa 'şimdi' kutusu çizilmez (FF-74). */
    noviceHome?: boolean;
};

function qrLabel(count: number): string {
    return count === 1
        ? t('dashboard.setup.qr.activeCount', { count: String(count) })
        : t('dashboard.setup.qr.activeCount.plural', { count: String(count) });
}

function menuSummary(dashboardMenuTree: DashboardMenuTree | null): string {
    if (!dashboardMenuTree) {
        return t('dashboard.setup.menu.empty');
    }

    const categories = dashboardMenuTree.categories.length;
    const items = dashboardMenuTree.categories.reduce(
        (total, category) => total + category.menuItems.length,
        0,
    );

    return `${categories} categories · ${items} items`;
}

type SetupRow = {
    key: string;
    label: string;
    value: string;
    done: boolean;
    section: string;
};

/**
 * Tek bir kurulum adımı.
 *
 * DURUM ÜÇ İŞARETLE anlatılır ve hiçbiri yalnız renge dayanmaz (WCAG 1.4.1):
 * biten adım DOLU bir daire içinde onay imi taşır, sıradaki adım HALKALI bir
 * daire ve kalın bir etiket taşır, başlamamış adım BOŞ bir daire. Renk körü
 * bir kullanıcı da, yüksek kontrast modundaki bir kullanıcı da üçünü ayırt
 * eder — önceki hâlde ayrım yalnız `sr-only` metindeydi ve gözle bakan
 * hiç kimseye ulaşmıyordu.
 *
 * Adım adı MAVİ DEĞİL: ürünün tamamı nötr yüzey üzerine kurulu ve mavi
 * burada hiçbir şeyi temsil etmiyordu. Tıklanabilirlik hover'daki alt
 * çizgiyle ve tüm satırın hedef olmasıyla söylenir.
 */
function StepButton({
    row,
    isNext,
    onNavigateToSection,
}: {
    row: SetupRow;
    isNext: boolean;
    onNavigateToSection?: (section: string) => void;
}) {
    const marker = (
        <span
            aria-hidden="true"
            className={cn(
                'flex h-[1.5rem] w-[1.5rem] shrink-0 items-center justify-center rounded-pill border',
                row.done
                    ? 'border-transparent bg-[var(--color-fg)] text-[var(--color-surface)]'
                    : isNext
                      ? 'border-2 border-action bg-surface'
                      : 'border-border bg-surface',
            )}
        >
            {row.done ? <Check size={13} weight="bold" /> : null}
        </span>
    );

    const body = (
        <>
            {marker}
            <span className="flex min-w-0 flex-col gap-[var(--space-1)] text-start">
                <span
                    className={cn(
                        'truncate text-body',
                        // Sıradaki adım KALIN: "şimdi neredeyim" sorusunun
                        // cevabı listeyi okumadan görünmeli.
                        isNext ? 'font-semibold text-fg' : 'font-medium text-fg',
                    )}
                >
                    {row.label}
                </span>{' '}
                {/*
                    Değer İKİNCİL. Öncesinde adım adı soluk, değeri koyuydu:
                    hiyerarşi tersti ve göz önce "Zabuno" kelimesini,
                    sonra hangi adım olduğunu okuyordu.
                */}
                {/*
                    Boşluk KASITLI. Ekran okuyucu, bitişik metin düğümlerini
                    aralarına hiçbir şey koymadan birleştirir: boşluksuz
                    hâlde ad "BrandNext step" diye okunuyordu.
                */}
                <span className="truncate text-meta text-fg-muted">{row.value}</span>{' '}
            </span>{' '}
            {/*
                Durum METİNLE de söylenir. İşaretler görene yeter; ekran
                okuyucu kullanan biri için daire ile halka arasında hiçbir
                fark yoktur.
            */}
            <span className="sr-only">
                {row.done
                    ? t('dashboard.setup.step.done')
                    : isNext
                      ? t('dashboard.setup.step.next')
                      : t('dashboard.setup.step.todo')}
            </span>
        </>
    );

    const shared =
        'flex w-full items-center gap-[var(--space-2)] rounded-[var(--radius-md)] p-[var(--space-2)]';

    if (!onNavigateToSection) {
        return <span className={shared}>{body}</span>;
    }

    return (
        <button
            type="button"
            onClick={() => onNavigateToSection(row.section)}
            aria-current={isNext ? 'step' : undefined}
            className={cn(
                shared,
                'min-h-[var(--density-hit-area-min)] text-start',
                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)]',
                'hover:bg-surface-hover',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
            )}
        >
            {body}
        </button>
    );
}

export function DashboardSetupJourney({
    brand,
    location,
    dashboardMenuTree,
    workspaceId,
    onNavigateToSection,
    noviceHome = true,
}: DashboardSetupJourneyProps) {
    const notConnected = t('dashboard.setup.notConnected');
    const checking = t('dashboard.setup.checking');
    const unavailable = t('dashboard.setup.statusUnavailable');

    const [publicationValue, setPublicationValue] = useState<string>(notConnected);
    const [qrValue, setQrValue] = useState<string>(notConnected);

    const menuId = dashboardMenuTree?.id;
    const locationId = dashboardMenuTree?.locationId;

    useEffect(() => {
        let cancelled = false;

        (async () => {
            if (!workspaceId || !menuId || !locationId) {
                if (cancelled) return;
                setPublicationValue(notConnected);
                setQrValue(notConnected);
                return;
            }

            setPublicationValue(checking);
            setQrValue(checking);

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/menu/${menuId}/publications/current`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (response.status === 404) {
                    if (cancelled) return;
                    setPublicationValue(notConnected);
                    setQrValue(notConnected);
                    return;
                }

                if (!response.ok) {
                    if (cancelled) return;
                    setPublicationValue(unavailable);
                    setQrValue(unavailable);
                    return;
                }

                const body = (await response.json()) as { id: number };
                if (cancelled) return;
                setPublicationValue(t('dashboard.setup.published', { id: String(body.id) }));
            } catch {
                if (cancelled) return;
                setPublicationValue(unavailable);
                setQrValue(unavailable);
                return;
            }

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/brand/locations/${locationId}/qr-codes`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    if (cancelled) return;
                    setQrValue(unavailable);
                    return;
                }

                const body = (await response.json()) as { state: string }[];
                const activeCount = body.filter((qr) => qr.state === 'active').length;
                if (cancelled) return;
                setQrValue(activeCount > 0 ? qrLabel(activeCount) : notConnected);
            } catch {
                if (cancelled) return;
                setQrValue(unavailable);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, menuId, locationId, notConnected, checking, unavailable]);

    /*
        Her adım TAMAMLANDI mı? Plan ilk kullanımda bir GÖREV LİSTESİ istiyor
        (`docs/50` §6.1): kullanıcı hangi adımların bittiğini, hangisinin
        sırada olduğunu ve oraya nasıl gideceğini görmeli.

        Önceki hâli yalnız DEĞER gösteriyordu — "Publication: Not connected
        yet" gibi. Bu bir durum bildirimi, bir yol tarifi değil.
    */
    const rows: SetupRow[] = [
        {
            key: 'brand',
            label: t('dashboard.setup.brand'),
            value: brand?.name ?? '',
            done: brand !== null,
            section: 'settings/brand',
        },
        {
            key: 'location',
            label: t('dashboard.setup.location'),
            value: location?.display_name ?? '',
            done: location !== null,
            section: 'locations',
        },
        {
            key: 'menu',
            label: t('dashboard.setup.menu'),
            value: menuSummary(dashboardMenuTree),
            /*
                Menünün VARLIĞI yetmez: içi boş bir menü yayınlanamaz ve
                misafire gösterecek bir şeyi yoktur. Adım ancak en az bir
                ürün varken tamamlanmış sayılır.
            */
            done:
                dashboardMenuTree !== null &&
                dashboardMenuTree.categories.some((category) => category.menuItems.length > 0),
            section: 'menu',
        },
        {
            key: 'publication',
            label: t('dashboard.setup.publication'),
            value: publicationValue,
            done: publicationValue !== notConnected && publicationValue !== checking,
            section: 'publication',
        },
        {
            key: 'qr',
            label: t('dashboard.setup.qr'),
            value: qrValue,
            done: qrValue !== notConnected && qrValue !== checking,
            section: 'qr-codes',
        },
    ];

    const doneCount = rows.filter((row) => row.done).length;
    const allDone = doneCount === rows.length;

    // Sırada olan adım: BİTMEMİŞ ilki. Kullanıcıya "şimdi ne yapmalıyım"
    // sorusunun cevabı budur ve listede vurgulanır.
    const nextStep = rows.find((row) => !row.done);

    /*
        `docs/101` A1: ekranda TEK "şimdi". Liste durumu gösterir; bu kutu
        ne yapılacağını FİİLLE söyler ve tek düğmeyle oraya götürür. İki
        büyük düğme "hangisi?" sorusu, o soru da donma demektir.
    */
    const nowLabel: Record<string, string> = {
        brand: t('dashboard.now.brand'),
        location: t('dashboard.now.location'),
        menu: t('dashboard.now.menu'),
        publication: t('dashboard.now.publication'),
        qr: t('dashboard.now.qr'),
    };
    const nowButtonClass =
        'inline-flex min-h-[var(--density-hit-area-min)] items-center justify-center rounded-md bg-action px-5 py-3 text-body font-semibold text-action-fg hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

    return (
        <section aria-label={t('dashboard.setup.region')} className="flex flex-col gap-3">
            {noviceHome ? (
                <section
                    aria-label={t('dashboard.now.region')}
                    // Marka şeridi: sayfadaki TEK vurgu (`docs/102` §1, `docs/101` A1).
                    className="flex flex-col gap-2 rounded-[var(--radius-md)] border border-border border-s-4 border-s-brand bg-surface p-4"
                >
                    <h2 className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                        {t('dashboard.now.heading')}
                    </h2>
                    {nextStep ? (
                        onNavigateToSection ? (
                            <button
                                type="button"
                                onClick={() => onNavigateToSection(nextStep.section)}
                                className={nowButtonClass}
                            >
                                {nowLabel[nextStep.key]}
                            </button>
                        ) : (
                            <p className="text-body font-semibold text-fg">
                                {nowLabel[nextStep.key]}
                            </p>
                        )
                    ) : (
                        <>
                            <p role="status" className="text-body text-fg">
                                {t('dashboard.now.allDone')}
                            </p>
                            {onNavigateToSection ? (
                                <button
                                    type="button"
                                    onClick={() => onNavigateToSection('qr-codes')}
                                    className={nowButtonClass}
                                >
                                    {t('dashboard.now.openQr')}
                                </button>
                            ) : null}
                        </>
                    )}
                </section>
            ) : null}
            {/*
                KURULUM ŞERİDİ — sahibin isteği (2026-09-04: "UX estetiği çok
                kötü, çok çok iyi olmalı. Burası çok önemli").

                Önceki hâlin üç somut kusuru vardı:

                  1. Beş adım BİRBİRİNİN AYNIYDI. Hangisinin bittiği yalnız
                     `sr-only` metinde söyleniyordu; gözle bakan kişi beş eşit
                     satır görüyor ve "nerede kaldım?" sorusunu ancak değerleri
                     tek tek okuyarak cevaplayabiliyordu.
                  2. Adım adları MAVİ BAĞLANTIYDI. Ürünün tamamı nötr yüzey +
                     tek bir marka vurgusu üzerine kurulu; mavi burada hiçbir
                     şeyi temsil etmiyor, yalnız tarayıcı varsayılanını taşıyordu.
                  3. Kurulum BİTTİKTEN SONRA da kart her gün aynı yeri
                     kaplıyordu. Bir kez yapılıp bir daha dönülmeyen bir liste,
                     günlük ekranın ortasında kalıcı gürültüdür.

                Şimdi: tek satırlık ilerleme + ince bir çubuk, adımlar durum
                işaretiyle (biten dolu, sıradaki halkalı, başlamamış boş), ve
                kurulum bitince şerit KENDİLİĞİNDEN KAPANIR — açmak isteyen
                açar.
            */}
            <details
                open={!allDone}
                className="group rounded-[var(--radius-md)] border border-border bg-surface"
            >
                <summary className="flex cursor-pointer flex-wrap items-center gap-[var(--space-3)] p-4">
                    <h2 className="text-section font-semibold text-fg">
                        {allDone ? t('dashboard.setup.complete') : t('dashboard.setup.heading')}
                    </h2>

                    {/*
                        İLERLEME BİR CÜMLE, bir de çubuk. Cümle ekran okuyucu
                        ve düşünen göz için; çubuk, bakmadan anlayan göz için.
                        Çubuk `aria-hidden`: aynı olguyu iki kez duyurmak
                        gürültüdür.
                    */}
                    <span className="text-meta text-fg-muted">
                        {t('dashboard.setup.progress', {
                            done: String(doneCount),
                            total: String(rows.length),
                        })}
                        {nextStep
                            ? ` · ${t('dashboard.setup.progress.next', {
                                  step: nextStep.label,
                              })}`
                            : ''}
                    </span>

                    <span
                        aria-hidden="true"
                        className="h-[0.25rem] min-w-[6rem] flex-1 overflow-hidden rounded-pill bg-[var(--color-surface-active)]"
                    >
                        <span
                            className={cn(
                                'block h-full rounded-pill',
                                'transition-[width] duration-[var(--duration-slow)] ease-[var(--easing-inout)]',
                                /*
                                    Bitmiş kurulumun çubuğu MARKA RENGİNDE
                                    DEĞİL. Marka vurgusu sayfadaki tek
                                    eyleme ayrılmıştır (`docs/101` A1) ve
                                    hemen üstteki "şimdi" düğmesi onu zaten
                                    kullanıyor. Biten bir işi ikinci kez
                                    bağırmak, asıl eylemi gölgeler.
                                */
                                allDone ? 'bg-[var(--color-border-strong)]' : 'bg-action',
                            )}
                            style={{ width: `${(doneCount / rows.length) * 100}%` }}
                        />
                    </span>

                    {/*
                        AÇILIR olduğunu söyleyen işaret. `<summary>` üzerinde
                        `flex` kullanınca tarayıcının kendi üçgeni kaybolur;
                        yerine hiçbir şey koymamak, kartın tıklanabilir
                        olduğunu yalnız deneyerek keşfedilir hâle getirirdi.
                    */}
                    <CaretDown
                        aria-hidden="true"
                        size={16}
                        weight="bold"
                        className="shrink-0 text-fg-muted transition-transform duration-[var(--duration-base)] ease-[var(--easing-inout)] group-open:rotate-180"
                    />
                    <span className="sr-only">{t('dashboard.setup.toggle')}</span>
                </summary>

                {allDone ? (
                    <p className="px-4 pb-2 text-body text-fg-secondary">
                        {t('dashboard.setup.complete.summary')}
                    </p>
                ) : null}

                <ol
                    className={cn(
                        // Beş adım masaüstünde TEK SIRADA okunur: yolculuk bir
                        // sıradır ve ikinci satıra düşen adım, sıranın parçası
                        // gibi görünmez. Dar ekranda kendiliğinden alt alta iner.
                        'grid grid-cols-[repeat(auto-fit,minmax(min(100%,8rem),1fr))]',
                        'gap-[var(--space-2)] p-4 pt-0',
                    )}
                >
                    {rows.map((row) => {
                        const isNext = row.key === nextStep?.key;

                        return (
                            <li key={row.key}>
                                <StepButton
                                    row={row}
                                    isNext={isNext}
                                    onNavigateToSection={onNavigateToSection}
                                />
                            </li>
                        );
                    })}
                </ol>
            </details>
        </section>
    );
}

export default DashboardSetupJourney;
