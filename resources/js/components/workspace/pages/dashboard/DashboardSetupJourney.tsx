import { useEffect, useState } from 'react';
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

export function DashboardSetupJourney({
    brand,
    location,
    dashboardMenuTree,
    workspaceId,
    onNavigateToSection,
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
    const rows: {
        key: string;
        label: string;
        value: string;
        done: boolean;
        section: string;
    }[] = [
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
            <section
                aria-label={t('dashboard.now.region')}
                className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-4"
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
                        <p className="text-body font-semibold text-fg">{nowLabel[nextStep.key]}</p>
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
            <h2 className="text-lg font-semibold text-fg">{t('dashboard.setup.heading')}</h2>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-3">
                {rows.map((row) => (
                    <div key={row.key} className="flex flex-col gap-1">
                        <dt className="flex items-center gap-2 text-body font-medium text-fg-muted">
                            {/*
                                Tamamlanma işareti RENKLE verilmez: yüksek
                                kontrast modunda ve renk körlüğünde kaybolur.
                                Ekran okuyucu için de metin karşılığı var.
                            */}
                            {/*
                                Genişlik KARAKTERLE ölçülür (`ch`), pikselle
                                değil: sütunun içindeki şey bir karakter ve
                                320 piksellik ekranda sabit piksel genişliği
                                yazı boyutuyla birlikte ölçeklenmez. Kolonun
                                kalıcı olması satırların kaymasını önler.
                            */}
                            <span
                                aria-hidden="true"
                                className="w-[2ch] shrink-0 text-center text-fg-secondary"
                            >
                                {row.done ? '✓' : '○'}
                            </span>
                            {onNavigateToSection ? (
                                <button
                                    type="button"
                                    onClick={() => onNavigateToSection(row.section)}
                                    className="text-start text-fg-link hover:underline"
                                >
                                    {row.label}
                                </button>
                            ) : (
                                <span>{row.label}</span>
                            )}
                            <span className="sr-only">
                                {row.done
                                    ? t('dashboard.setup.step.done')
                                    : row.key === nextStep?.key
                                      ? t('dashboard.setup.step.next')
                                      : t('dashboard.setup.step.todo')}
                            </span>
                        </dt>
                        <dd className="ps-6 text-body text-fg">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export default DashboardSetupJourney;
