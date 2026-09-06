import { useEffect, useState } from 'react';
import { ArrowRight, ArrowSquareOut, CaretDown, CheckCircle, Circle } from '@phosphor-icons/react';
import { cn } from '../../../../lib/utils';
import { t } from '../../../../i18n/dashboard';
import { trackEvent } from '../../../../lib/analytics';
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

/**
 * Sunucunun kurulum ilerlemesi (`GET /api/workspaces/{w}/setup-progress`).
 * Yalnız ekranın okuduğu alanlar; şekil `SetupProgressTest` ile donmuş.
 */
type SetupProgressResponse = {
    steps: {
        publication: { done: boolean; id?: number };
        qr: { done: boolean; activeCount: number };
    };
    /** Yalnız bir yayın VARSA gelir; yoksa anahtar hiç yoktur. */
    firstPublishedAfterMinutes?: number;
};

/**
 * "İlk yayın, çalışma alanı açıldıktan kaç dakika sonra?" — TEK cümle.
 *
 * Sayı sunucuda hesaplanır (iki damganın farkı; tarayıcı saati karışmaz) ve
 * burada yalnız okunur birime çevrilir. Tekil/çoğul ayrı anahtar: "1 minutes"
 * diye bir cümle yok. Gün için tekil gerekmez — 24-47 saat "saat" olarak
 * okunur.
 */
function firstPublishedSentence(minutes: number): string {
    if (minutes < 1) return t('dashboard.setup.firstPublished.underMinute');
    if (minutes === 1) return t('dashboard.setup.firstPublished.minute');
    if (minutes < 60) {
        return t('dashboard.setup.firstPublished.minutes', { count: String(minutes) });
    }
    if (minutes < 120) return t('dashboard.setup.firstPublished.hour');
    if (minutes < 48 * 60) {
        return t('dashboard.setup.firstPublished.hours', {
            count: String(Math.floor(minutes / 60)),
        });
    }

    return t('dashboard.setup.firstPublished.days', {
        count: String(Math.floor(minutes / (24 * 60))),
    });
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
    /*
        DURUM İŞARETİ — AEP `DESIGN_SPEC` §2 "Kurulum kartı": biten adım DOLU
        bir onay dairesi, bekleyen adım BOŞ bir daire.

        Elle kurulan daire+onay yerine paketin adlandırdığı iki simge
        kullanılıyor. Üç hâl hâlâ üç AYRI biçimle ayrışıyor ve hiçbiri yalnız
        renge dayanmıyor (WCAG 1.4.1): dolu gövde, kalın halka, ince halka.
        Renk körü bir kullanıcı da, yüksek kontrast modundaki biri de üçünü
        ayırt eder.

        Sıradaki adımın halkası MARKA RENGİNDE DEĞİL. Marka vurgusu artık
        hemen üstteki "şimdi" kartının ZEMİNİ; aynı rengi burada ikinci kez
        kullanmak, sayfadaki tek eylemin ne olduğunu bulanıklaştırırdı.
    */
    const marker = row.done ? (
        <CheckCircle
            aria-hidden="true"
            size={22}
            weight="fill"
            className="shrink-0 text-fg-success"
        />
    ) : (
        <Circle
            aria-hidden="true"
            size={22}
            weight={isNext ? 'bold' : 'regular'}
            className={cn('shrink-0', isNext ? 'text-fg' : 'text-fg-muted')}
        />
    );

    const body = (
        <>
            {marker}
            <span className="flex min-w-0 flex-col gap-[var(--space-1)] text-start">
                <span
                    className={cn(
                        'truncate text-body',
                        /*
                            BİTEN ADIM soluk VE üstü çizili (`DESIGN_SPEC` §2).
                            Üstü çizgi, "bu iş kapandı"yı renkten bağımsız
                            söyleyen ikinci işarettir; tek başına soluk renk,
                            yüksek kontrast modunda kaybolur.

                            Sıradaki adım KALIN: "şimdi neredeyim" sorusunun
                            cevabı listeyi okumadan görünmeli. Ağırlık 700 —
                            AEP merdiveni 400/500/700; 600 o merdivende yok ve
                            tarayıcı tarafından sentezleniyordu.
                        */
                        row.done
                            ? 'font-medium text-fg-secondary line-through'
                            : isNext
                              ? 'font-bold text-fg'
                              : 'font-medium text-fg',
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
                // Satır yüksekliği yoğunluk jetonundan (`DESIGN_SPEC` §1):
                // kompakt modda satır alçalır, dokunma hedefi ASLA küçülmez.
                'min-h-[var(--control-height)] text-start',
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
    /*
        İLK YAYINA KADAR GEÇEN SÜRE (FF-202, `docs/110` §7, `docs/107` 1.7).
        `null` = "bilinmiyor ya da henüz yayın yok"; o zaman cümle HİÇ
        çizilmez. Sıfır bir ölçümdür, bilinmeyenin yerine geçemez.
    */
    const [firstPublishedAfterMinutes, setFirstPublishedAfterMinutes] = useState<number | null>(
        null,
    );

    const menuId = dashboardMenuTree?.id;
    const locationId = dashboardMenuTree?.locationId;

    useEffect(() => {
        let cancelled = false;

        (async () => {
            if (!workspaceId || !menuId || !locationId) {
                if (cancelled) return;
                setPublicationValue(notConnected);
                setQrValue(notConnected);
                setFirstPublishedAfterMinutes(null);
                return;
            }

            setPublicationValue(checking);
            setQrValue(checking);

            /*
                TEK İSTEK (FF-202). Önceden yayın ve karekod durumu iki ayrı
                uçtan okunuyordu ve "ilk yayına kaç dakikada ulaşıldı" hiçbir
                uçtan gelmiyordu. Kurulum ilerlemesi artık sunucunun tek bir
                cevabıdır; süre de oradan gelir, tarayıcıda hesaplanmaz.

                Gövde beklenen şekilde değilse (`steps` yok) okuma fırlatır ve
                aynı `catch`e düşer: ekran "durum okunamadı" der, uydurmaz.
            */
            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/setup-progress`, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    if (cancelled) return;
                    setPublicationValue(unavailable);
                    setQrValue(unavailable);
                    setFirstPublishedAfterMinutes(null);
                    return;
                }

                const body = (await response.json()) as SetupProgressResponse;
                const publication = body.steps.publication;
                const qr = body.steps.qr;
                if (cancelled) return;

                setPublicationValue(
                    publication.done && publication.id !== undefined
                        ? t('dashboard.setup.published', { id: String(publication.id) })
                        : notConnected,
                );
                setQrValue(qr.done && qr.activeCount > 0 ? qrLabel(qr.activeCount) : notConnected);
                setFirstPublishedAfterMinutes(
                    typeof body.firstPublishedAfterMinutes === 'number'
                        ? body.firstPublishedAfterMinutes
                        : null,
                );
            } catch {
                if (cancelled) return;
                setPublicationValue(unavailable);
                setQrValue(unavailable);
                setFirstPublishedAfterMinutes(null);
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
            /*
                ÖLÇÜLDÜ (2026-09-06, FF-202): marka YOKKEN `settings/brand`
                hedefi, Ayarlar'ın marka sekmesinde "Loading your brand…"
                yazan ve sıfır alan içeren bir ekrana çıkıyordu — yolculuğun
                İLK dokunuşu bir çıkmaz sokaktı. Marka oluşturma formu yalnız
                `brand` bölümünde çizilir; düzenleme ise Ayarlar'da. Hedef,
                adımın hâline göre seçilir.
            */
            section: brand === null ? 'brand' : 'settings/brand',
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
    /*
        DÜĞME MÜREKKEP DOLGULU. Kart artık marka zeminli olduğu için, marka
        zeminli bir düğme onun üstünde KAYBOLURDU — sarı üstünde sarı. AEP
        referansı da tam bunu yapıyor: zemin marka, birincil eylem ters
        (mürekkep dolgu, yüzey rengi metin).
    */
    const nowButtonClass =
        'inline-flex min-h-[var(--control-height)] items-center justify-center gap-[var(--space-2)] rounded-[var(--radius-lg)] bg-[var(--color-fg)] px-[var(--space-5)] py-[var(--space-3)] text-section font-bold text-[var(--color-surface)] transition-opacity duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

    return (
        <section aria-label={t('dashboard.setup.region')} className="flex flex-col gap-3">
            {noviceHome ? (
                <section
                    aria-label={t('dashboard.now.region')}
                    /*
                        MARKA ZEMİN, ince şerit değil (FF-131, AEP
                        `DESIGN_SPEC` §2 "Şimdi kartı").

                        Önceki hâl nötr bir yüzeyin sol kenarında 4 piksellik
                        bir marka şeridiydi. Ölçülen sonuç: ekranın TEK eylemi,
                        hemen altındaki beş kurulum satırıyla aynı görsel
                        ağırlıktaydı — ikisi de beyaz kart, ikisi de aynı
                        kenarlık. "Önce neye bakayım?" sorusu cevapsız
                        kalıyordu ve cevapsız kalan o soru, kullanıcının
                        donduğu andır.

                        Külliyat sarıyı YAPISAL vurgu olarak dondurur: açık
                        zeminde küçük sarı metin asla, ama tam bir zemin evet.
                        Sarı üstünde mürekkep metin, panelin en yüksek
                        kontrastlı yüzeyidir.
                    */
                    className="flex flex-col items-start gap-[var(--space-3)] rounded-[var(--radius-lg)] bg-action p-[var(--space-fluid-md)] text-action-fg"
                >
                    <h2 className="text-meta font-bold">{t('dashboard.now.heading')}</h2>
                    {nextStep ? (
                        onNavigateToSection ? (
                            <button
                                type="button"
                                onClick={() => onNavigateToSection(nextStep.section)}
                                className={nowButtonClass}
                            >
                                {nowLabel[nextStep.key]}
                                <ArrowRight aria-hidden="true" size={20} weight="bold" />
                            </button>
                        ) : (
                            /*
                                Gezinti bağlı değilse düğme çizilmez: hiçbir
                                yere götürmeyen bir düğme, tıklanana kadar
                                çalışıyor görünür. Cümle kalır, eylem kalmaz.
                            */
                            <p className="text-section font-bold">{nowLabel[nextStep.key]}</p>
                        )
                    ) : (
                        <>
                            <p role="status" className="text-body">
                                {t('dashboard.now.allDone')}
                            </p>
                            {onNavigateToSection ? (
                                <button
                                    type="button"
                                    onClick={() => onNavigateToSection('qr-codes')}
                                    className={nowButtonClass}
                                >
                                    {t('dashboard.now.openQr')}
                                    <ArrowRight aria-hidden="true" size={20} weight="bold" />
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
                className="group rounded-[var(--radius-lg)] border border-border bg-surface"
            >
                <summary className="flex cursor-pointer flex-wrap items-center gap-[var(--space-3)] p-[var(--space-fluid-md)]">
                    <h2 className="text-section font-bold text-fg">
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

                    {/*
                        İLK YAYINA KADAR GEÇEN SÜRE — yalnız GERÇEKLEŞTİYSE.
                        Sunucu hesaplar (`workspaces.created_at` → ilk
                        `menu_publications.published_at`); ekran yalnız okur.
                        `docs/110` §7'nin "5 dakika mı 15 dakika mı" sorusu
                        artık her restoranda ölçülen bir sayıdır.
                    */}
                    {firstPublishedAfterMinutes !== null ? (
                        <span className="text-meta text-fg-muted">
                            {firstPublishedSentence(firstPublishedAfterMinutes)}
                        </span>
                    ) : null}

                    <span
                        aria-hidden="true"
                        className="h-[0.375rem] min-w-[6rem] flex-1 overflow-hidden rounded-pill bg-[var(--color-surface-active)]"
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
                    <p className="px-[var(--space-fluid-md)] pb-[var(--space-2)] text-body text-fg-secondary">
                        {t('dashboard.setup.complete.summary')}
                    </p>
                ) : null}

                <ol
                    className={cn(
                        // Beş adım masaüstünde TEK SIRADA okunur: yolculuk bir
                        // sıradır ve ikinci satıra düşen adım, sıranın parçası
                        // gibi görünmez. Dar ekranda kendiliğinden alt alta iner.
                        'grid grid-cols-[repeat(auto-fit,minmax(min(100%,8rem),1fr))]',
                        'gap-[var(--space-2)] p-[var(--space-fluid-md)] pt-0',
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

                {/*
                    TAKILMA ÇIKIŞI (FF-202). Ölçüm: panelden yardım makalesine
                    giden bağlantı sayısı SIFIRDI. Makale (`/help`) tam olarak
                    bu yolculuğu anlatıyor; kurulum bitince de sıradaki günlük
                    işi ("fiyat değiştir" bölümü). Yeni sekmede açılır: yardım
                    kamu sayfasıdır ve paneli terk ettirmez. Olay hangi adımda
                    yardıma gidildiğini ölçer (`docs/112` §4.3).
                */}
                <a
                    href={allDone ? '/help#help-price' : '/help'}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={() =>
                        trackEvent('setup_help_opened', { step: nextStep?.key ?? 'done' })
                    }
                    className="mx-[var(--space-fluid-md)] mb-[var(--space-2)] inline-flex min-h-[var(--density-hit-area-min)] items-center gap-[var(--space-1)] self-start text-body font-medium text-fg-link underline underline-offset-2 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {allDone
                        ? t('dashboard.setup.help.afterSetup')
                        : t('dashboard.setup.help.stuck')}
                    <ArrowSquareOut aria-hidden="true" size={16} weight="bold" />
                    <span className="sr-only">({t('dashboard.firstRun.help.newTab')})</span>
                </a>
            </details>
        </section>
    );
}

export default DashboardSetupJourney;
