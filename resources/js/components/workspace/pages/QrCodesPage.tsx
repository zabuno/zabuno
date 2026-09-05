import { useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import type { DashboardMenuTree } from './DashboardPage';
import { BulkQrWizardFields } from './publication/BulkQrWizardFields';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import { QrSelectedCodePanel } from './publication/QrSelectedCodePanel';
import { QrTableCardGrid, type QrScreenCode } from './publication/QrTableCardGrid';
import type { QrCreateReasonKind } from './publication/QrDestinationFieldsRegion';
import { useCurrentPublication } from './qr/useCurrentPublication';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';
import { PageState } from './shared/PageState';
import { ActionLink } from '../../catalog/navigation/micro/ActionLink';
import { Button } from '../../catalog/forms/micro/Button';

export type QrCodesPageProps = {
    workspaceId?: number;
    dashboardMenuTree?: DashboardMenuTree | null;
    onNavigateToSection?: (section: string) => void;
    /** Markanın ana rengi — kartın şeridi/çerçevesi bunu kullanır (FF-112). */
    brandPrimaryColor?: string | null;
};

/**
 * Sunucu tek istekte en fazla bu kadar kart basar
 * (`App\Domain\QrDestination\QrPrintSheet::CARDS_PER_REQUEST`). Sayı burada da
 * biliniyor ki ekran sessizce kırpılmış bir PDF vermek yerine "3 parçadan 1."
 * diyebilsin.
 */
const CARDS_PER_REQUEST = 48;

function printSheetUrl(workspaceId: number, locationId: number, chunk: number): string {
    const base = `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/qr-codes/print.pdf`;

    return chunk > 1 ? `${base}?chunk=${String(chunk)}` : base;
}

type QrCodeListState = {
    codes: QrScreenCode[];
    loading: boolean;
    failed: boolean;
};

/**
 * Şubenin kodları — ADRESLE BİRLİKTE saklanır.
 *
 * Anahtarı durumun içine koymak, sahip başka bir şubeye geçtiğinde eski
 * şubenin kartlarının ekranda kalmasını imkânsız kılar: elimizdeki cevap şu
 * anki adrese ait değilse liste "henüz yüklenmedi" sayılır. Aynı desen
 * `useCurrentPublication` ve `QrDestinationRegion`'da da var.
 */
function useQrCodeList(
    workspaceId: number | undefined,
    locationId: number | null,
    reloadToken: number,
): QrCodeListState {
    const enabled = workspaceId !== undefined && locationId !== null;
    const listUrl = enabled
        ? `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/qr-codes`
        : '';
    const requestKey = `${listUrl}#${String(reloadToken)}`;

    const [resolved, setResolved] = useState<{
        key: string;
        codes: QrScreenCode[];
        failed: boolean;
    } | null>(null);

    useEffect(() => {
        if (!enabled) return;

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(listUrl, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled) return;

                const body: unknown = response.ok ? await response.json() : null;

                if (cancelled) return;

                if (Array.isArray(body)) {
                    setResolved({ key: requestKey, codes: body as QrScreenCode[], failed: false });
                } else {
                    setResolved({ key: requestKey, codes: [], failed: true });
                }
            } catch {
                if (!cancelled) setResolved({ key: requestKey, codes: [], failed: true });
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [enabled, listUrl, requestKey]);

    const current = resolved?.key === requestKey ? resolved : null;

    return {
        codes: current?.codes ?? [],
        // `loading` TÜRETİLİR, efekt içinde ayarlanmaz: fazladan bir render
        // turu üretmeden, istek parametreleri değişince kendiliğinden yeniden
        // "yükleniyor" olur.
        loading: enabled && current === null,
        failed: current?.failed ?? false,
    };
}

/**
 * QR KODLAR — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Sahibin cümlesi: *"benzetmek değil DEĞİŞTİRMEKTİR."*
 *
 * Bu ekran eskiden tek sütunlu bir bölge yığınıydı: kod listesi, salon
 * bölümleri, kart sihirbazı, deste, tek kod ve toplu sihirbaz alt alta. Her
 * bölüm ayrı ayrı doğruydu ama sıra yanlıştı — sahip buraya bir ayar yapmaya
 * değil, bir masaya kart koymaya gelir ve ilk sorusu "hangi masa"dır.
 *
 * Kaynağın düzeni o soruyu öne alır: solda masa kartları IZGARASI (tarama
 * sayısıyla birlikte), sağda seçili kodun paneli. Kırk masalı bir restoranda
 * "Masa 17 hiç okutulmamış" bilgisi ancak böyle tek bakışta görünür; eski
 * düzende hiç görünmüyordu.
 *
 * Kaldırılmayan şey: kodu kapatma/açma, başka şubeye taşıma ve gelişmiş
 * baskı. Kaynağın ekranında yoklar ama ürünün gerçek yetenekleri ve bir
 * yerden erişilebilir olmaları gerekiyor — kapalı bir "Kod yönetimi"
 * bölümüne indiler, silinmediler.
 */
export function QrCodesPage({
    workspaceId,
    dashboardMenuTree = null,
    onNavigateToSection,
    brandPrimaryColor = null,
}: QrCodesPageProps) {
    const menuId = dashboardMenuTree?.id ?? null;
    const locationId = dashboardMenuTree?.locationId ?? null;
    const { current, loading, loadError } = useCurrentPublication(workspaceId, menuId);

    const [reloadToken, setReloadToken] = useState(0);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    /*
        Gelişmiş bölüm AÇILINCA doğar. `<details>` içeriği kapalıyken de
        DOM'da kalır ve `QrDestinationRegion` kendi listesini kendi çeker —
        yani her sayfa açılışında aynı liste iki kez istenirdi. Sahibin
        çoğu ziyaretinde bu bölüm hiç açılmaz; açılmayan bir bölümün ağ
        bedelini ödetmek, en çok masası olan müşteriye en pahalıya gelir.
    */
    const [advancedOpen, setAdvancedOpen] = useState(false);

    const {
        codes,
        loading: listLoading,
        failed: listFailed,
    } = useQrCodeList(workspaceId, locationId, reloadToken);

    const activeCodes = codes.filter((code) => code.state === 'active');
    const selected = activeCodes.find((code) => code.id === selectedId) ?? activeCodes[0] ?? null;
    const chunkCount = Math.max(1, Math.ceil(activeCodes.length / CARDS_PER_REQUEST));

    const reasonKind: QrCreateReasonKind = loading
        ? 'loading'
        : loadError
          ? 'unknown'
          : 'notPublished';

    const ready = workspaceId !== undefined && locationId !== null && menuId !== null;

    /*
        SAYFANIN BİRİNCİL EYLEMİ İNDİRMEDİR. Sahip buraya yayınlamak için
        değil, BASMAK için gelir. Uç uydurulmadı: depoda zaten var olan deste
        PDF'i (`ExportQrPrintSheetController`) kullanılıyor — sayfa başına on
        iki kart, kesme çizgileriyle. Toplu ZIP arşivi bu düğmenin işi
        değildir: o, matbaaya giden ve her kartı ayrı dosya isteyen bir
        çıktıdır ve gelişmiş baskıda kalır.
    */
    const downloadAll =
        ready && activeCodes.length > 0 ? (
            <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                {Array.from({ length: chunkCount }, (_unused, index) => (
                    <ActionLink
                        key={index}
                        variant={index === 0 ? 'primary' : 'secondary'}
                        href={printSheetUrl(workspaceId, locationId, index + 1)}
                    >
                        {chunkCount === 1
                            ? t('workspace.publication.qrScreen.downloadAll')
                            : t('workspace.publication.qrScreen.downloadAllPart', {
                                  part: String(index + 1),
                                  total: String(chunkCount),
                              })}
                    </ActionLink>
                ))}
            </span>
        ) : undefined;

    return (
        <div id="section-qr-codes">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.shell.nav.qrCodes')}
                /*
                    KAYNAĞIN KENDİ CÜMLESİ, ürünün en güçlü argümanı: basılı
                    kod hiç değişmez. Bu sektördeki en pahalı arıza, üçüncü
                    taraf bir kısaltıcıya bağlanmış kodların bir gün ölmesidir
                    — masadaki kırk kart aynı anda çöp olur ve restoran bunu
                    misafirden öğrenir.
                */
                description={t('workspace.publication.qrScreen.description')}
                actions={downloadAll}
            >
                {!ready ? (
                    /*
                        ÖN KOŞUL durumu — hata değil. QR kodu yayınlanmış bir
                        menüye işaret eder; menü yoksa bozulmuş bir şey yoktur,
                        yalnız sıradaki adım henüz yapılmamıştır. `role="alert"`
                        ile sunmak aciliyet bildirir ve kullanıcıyı olmayan bir
                        arızayı aramaya iter (docs/59).
                    */
                    <PageState
                        kind="prerequisite"
                        title={t('workspace.qrCodes.empty.needsMenu')}
                        description={t('workspace.qrCodes.empty.needsMenu.why')}
                        action={
                            <button
                                type="button"
                                onClick={() => onNavigateToSection?.('menu')}
                                /*
                                    AĞIRLIK ÖLÇEĞİ ÜÇ BASAMAKLIDIR: 400 gövde,
                                    500 vurgulu satır, 700 başlık ve birincil
                                    eylem. 600 (`font-semibold`) AEP ölçeğinde
                                    YOKTUR — tarayıcı onu 500 ile 700 arasından
                                    seçer ya da sentetik olarak uydurur ve aynı
                                    ekran iki makinede iki farklı ağırlıkta
                                    çizilir.
                                */
                                className="min-h-[var(--control-height)] rounded-md border border-action bg-action px-4 py-2 text-body font-bold text-action-fg"
                            >
                                {t('workspace.qrCodes.empty.goToMenu')}
                            </button>
                        }
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap items-start gap-[var(--space-4)]">
                            <div className="flex min-w-[16rem] flex-[3_1_20rem] flex-col gap-[var(--space-4)]">
                                {listLoading ? (
                                    <p role="status" className="text-body text-fg-muted">
                                        {t('workspace.publication.qrScreen.loading')}
                                    </p>
                                ) : null}

                                {/*
                                    HATANIN BİR ÇIKIŞI OLMALI. Liste
                                    çekilemediğinde ekranda yalnız bir cümle
                                    kalırsa kullanıcının yapabileceği tek şey
                                    sayfayı yenilemektir ve bunu ona kimse
                                    söylemez.
                                */}
                                {listFailed ? (
                                    <div className="flex flex-col items-start gap-[var(--space-2)]">
                                        <p role="alert" className="text-body text-fg-danger">
                                            {t('workspace.publication.qrScreen.loadError')}
                                        </p>
                                        <Button
                                            type="button"
                                            color="light"
                                            onClick={() => setReloadToken((token) => token + 1)}
                                        >
                                            {t('workspace.publication.qrScreen.retry')}
                                        </Button>
                                    </div>
                                ) : null}

                                {!listLoading && !listFailed && activeCodes.length === 0 ? (
                                    <p className="text-body text-fg-muted">
                                        {t('workspace.publication.qrScreen.empty')}
                                    </p>
                                ) : null}

                                {activeCodes.length > 0 ? (
                                    <QrTableCardGrid
                                        codes={activeCodes}
                                        selectedId={selected?.id ?? null}
                                        onSelect={setSelectedId}
                                    />
                                ) : null}

                                {/*
                                    YENİ MASALAR İÇİN TOPLU KOD — kaynağın
                                    ızgaranın hemen altındaki bölümü. Tek soru
                                    sorar ("kaç masa?"); varsayılanı olan her
                                    şey "ileri ayarlar" altında durur.
                                */}
                                <PanelCard>
                                    <BulkQrWizardFields
                                        workspaceId={workspaceId}
                                        locationId={locationId}
                                        menuId={menuId}
                                        hasCurrentPublication={current !== null}
                                        unavailableReason={reasonKind}
                                        onCreated={() => setReloadToken((token) => token + 1)}
                                        onUpgrade={() => onNavigateToSection?.('billing')}
                                    />
                                </PanelCard>
                            </div>

                            {selected !== null ? (
                                <div className="min-w-[16rem] flex-[1_1_20rem]">
                                    <QrSelectedCodePanel code={selected} />
                                </div>
                            ) : null}
                        </div>

                        <details
                            open={advancedOpen}
                            onToggle={(event) =>
                                setAdvancedOpen((event.currentTarget as HTMLDetailsElement).open)
                            }
                            className="rounded-[var(--radius-md)] border border-border p-[var(--space-3)]"
                        >
                            <summary className="cursor-pointer text-body font-medium text-fg-secondary">
                                {t('workspace.publication.qrScreen.advanced')}
                            </summary>
                            <div className="flex flex-col gap-[var(--space-3)] pt-[var(--space-3)]">
                                {advancedOpen ? (
                                    <QrDestinationRegion
                                        workspaceId={workspaceId}
                                        locationId={locationId}
                                        menuId={menuId}
                                        hasCurrentPublication={current !== null}
                                        publicationLoading={loading}
                                        publicationLoadFailed={loadError}
                                        onUpgrade={() => onNavigateToSection?.('billing')}
                                        brandPrimaryColor={brandPrimaryColor}
                                        onEditBrand={() => onNavigateToSection?.('brand')}
                                        /*
                                            Toplu sihirbaz ekranın kendi sol
                                            sütununda duruyor; burada ikinci bir
                                            kopyası çizilirse aynı formun iki
                                            hâli ayrışır ve sahip hangisine
                                            yazdığını bilemez.
                                        */
                                        showBulkWizard={false}
                                    />
                                ) : null}
                            </div>
                        </details>
                    </>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default QrCodesPage;
