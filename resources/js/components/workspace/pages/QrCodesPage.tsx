import { useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import type { DashboardMenuTree } from './DashboardPage';
import { BulkQrWizardFields } from './publication/BulkQrWizardFields';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import type { QrScreenCode } from './publication/QrTableCardGrid';
import type { QrCreateReasonKind } from './publication/QrDestinationFieldsRegion';
import { useCurrentPublication } from './qr/useCurrentPublication';
import { QrPrintActionBar } from './qr/QrPrintActionBar';
import { QrPrintLookStep } from './qr/QrPrintLookStep';
import { QrPrintPreviewPanel } from './qr/QrPrintPreviewPanel';
import { QrPrintScopeStep } from './qr/QrPrintScopeStep';
import { QrPrintTargetStep } from './qr/QrPrintTargetStep';
import {
    areasOf,
    codeName,
    codesInScope,
    INITIAL_QR_PRINT_PLAN,
    printSheetUrl,
    type QrPrintPlan,
} from './qr/qrPrintPlan';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
import { ActionLink } from '../../catalog/navigation/micro/ActionLink';
import { Button } from '../../catalog/forms/micro/Button';

export type QrCodesPageProps = {
    workspaceId?: number;
    dashboardMenuTree?: DashboardMenuTree | null;
    onNavigateToSection?: (section: string) => void;
    /** Markanın ana rengi — "markalı" ve "tabela" tasarımları bunu kullanır. */
    brandPrimaryColor?: string | null;
};

/**
 * Sunucu tek istekte en fazla bu kadar kart basar
 * (`App\Domain\QrDestination\QrPrintSheet::CARDS_PER_REQUEST`). Sayı burada da
 * biliniyor ki tabaka bağlantısı sessizce kırpılmış bir PDF vermek yerine
 * "3 parçadan 1." diyebilsin.
 */
const CARDS_PER_REQUEST = 48;

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
 * QR KODLAR — panel v3.1 kanonik kaynağı (`docs/reference/panel-v3/
 * panel-v3.1.dc.html`, QR bölümü).
 *
 * Sahibin kuralı (2026-09-05): *"eğer ben tasarım veriyorsam zaten asla eski
 * dökümanlara bağımlı kalmadan yapmalısın."* Bu ekran o kaynağa göre
 * YENİLENDİ; kaynakla çelişen eski kararlar (`docs/104`) kaynağa bırakıldı.
 *
 * ÖNCEKİ HÂL ile FARK, bir zevk farkı değil bir SORU SIRASI farkı:
 *
 * - Eskiden ekran bir kod LİSTESİYDİ. Kırk kareli bir ızgara açılıyor, sahip
 *   birini seçiyor ve seçtiği kodun paneli sağda beliriyordu. Yani ilk soru
 *   "hangi kod" idi ve baskı ayarları o kodun içine gömülüydü: kırk masaya
 *   kart basmak isteyen sahip, önce bir masa seçmek zorundaydı.
 * - Kaynağın ekranı ise bir BASKI SİPARİŞİDİR ve ilk sorusu "ne basacaksın"
 *   (masa kartı mı, duvar afişi mi, vitrin mi). Kaç masa olduğu İKİNCİ soru,
 *   nasıl görüneceği ÜÇÜNCÜ. Varsayılan kapsam "tüm masalar"dır — çünkü
 *   sahibin buraya gelme sebebi çoğunlukla budur.
 *
 * ÜÇ ADIM DA AYNI PLANI YAZAR (`qr/qrPrintPlan`) ve alt çubuk onu tek cümleye
 * çevirir. Eski ekranda kart tasarımı üç ayrı yerde seçiliyordu ve üçü ayrı
 * durum tutuyordu: sahip solda "markalı" seçip sağdan klasik bir kart
 * indirebiliyordu.
 *
 * ÇİZİLMEYENLER ve sebepleri:
 *
 * - **"Temaları yönet" düğmesi.** Kaynak onu Ayarlar'ın "Baskı temaları"
 *   sekmesine gönderiyor ve orada KALICI bir varsayılan tema tutuyor. Bu
 *   depoda öyle bir sekme ve öyle bir kayıt yok; hiçbir yere gitmeyen bir
 *   düğme, olmayan bir yeteneği ilan etmek olurdu.
 * - **PNG biçimi.** Sunucunun kart bestecisi yalnız SVG ve PDF üretir ve bu
 *   bir karardır (`ExportQrCardController`). Sebep ekranda yazıyor.
 * - **Kesim payı anahtarı, alt satır, logo/masa/adres açma-kapamaları.**
 *   Bestecide karşılıkları yok. Masa adı artık HER kartta basılıyor (bu
 *   pakette eklendi), o yüzden kapatılabilir bir anahtar da yok.
 *
 * Kaldırılmayan şey: kodu kapatma/açma, başka şubeye taşıma, bölüm adlandırma
 * ve ham kod indirme. Kaynağın ekranında yoklar ama ürünün gerçek yetenekleri
 * — kapalı bir "Kod yönetimi" bölümüne indiler, silinmediler.
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
    const [plan, setPlan] = useState<QrPrintPlan>(INITIAL_QR_PRINT_PLAN);
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

    /*
        YALNIZ ETKİN KODLAR BASILIR. Kapatılmış bir kodu kâğıda dökmek, sahibi
        kendi eliyle ölü bir kart bastırmaya davet etmek olurdu; sunucunun
        arşiv ucu da aynı süzgeci uyguluyor (`ExportQrCardsZipController`).
    */
    const activeCodes = codes.filter((code) => code.state === 'active');
    const selected = codesInScope(activeCodes, plan);
    const chunkCount = Math.max(1, Math.ceil(activeCodes.length / CARDS_PER_REQUEST));

    const reasonKind: QrCreateReasonKind = loading
        ? 'loading'
        : loadError
          ? 'unknown'
          : 'notPublished';

    const ready = workspaceId !== undefined && locationId !== null && menuId !== null;

    function updatePlan(patch: Partial<QrPrintPlan>) {
        setPlan((previous) => ({ ...previous, ...patch }));
    }

    const scopeArea = areasOf(activeCodes).find((area) => area.id === plan.areaId) ?? null;
    const scopeName =
        plan.scope === 'all'
            ? t('workspace.publication.qrScreen.scope.all')
            : plan.scope === 'area'
              ? (scopeArea?.label ?? t('workspace.publication.qrScreen.scope.area'))
              : selected[0]
                ? codeName(selected[0])
                : t('workspace.publication.qrScreen.scope.one');

    return (
        <div id="section-qr-codes">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.shell.nav.qrCodes')}
                /*
                    KAYNAĞIN KENDİ CÜMLESİ: kod bir kez basılır, menü sonra
                    istendiği kadar değişir. Bu sektördeki en pahalı arıza,
                    üçüncü taraf bir kısaltıcıya bağlanmış kodların bir gün
                    ölmesidir — masadaki kırk kart aynı anda çöp olur ve
                    restoran bunu misafirden öğrenir.
                */
                description={t('workspace.publication.qrScreen.description')}
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
                        screen="qr_codes_needs_menu"
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
                        {listLoading ? (
                            <p role="status" className="text-body text-fg-muted">
                                {t('workspace.publication.qrScreen.loading')}
                            </p>
                        ) : null}

                        {/*
                            HATANIN BİR ÇIKIŞI OLMALI. Liste çekilemediğinde
                            ekranda yalnız bir cümle kalırsa kullanıcının
                            yapabileceği tek şey sayfayı yenilemektir ve bunu
                            ona kimse söylemez.
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

                        {/*
                            İKİ SÜTUN, kaynağın `1fr 300px` düzeni. `flex-wrap`
                            ile kurulmasının sebebi 320 piksel: sabit bir ızgara
                            orada önizlemeyi 300 piksele zorlar ve adımların
                            kendisini taşırırdı. Sarma noktası içeriğin kendi
                            asgarisinden gelir, breakpoint'ten değil.
                        */}
                        <div className="flex flex-wrap items-start gap-[var(--space-4)]">
                            <div className="flex min-w-[16rem] flex-[3_1_22rem] flex-col gap-[var(--space-4)]">
                                <QrPrintTargetStep plan={plan} onChange={updatePlan} />

                                <QrPrintScopeStep
                                    codes={activeCodes}
                                    plan={plan}
                                    onChange={updatePlan}
                                    bulkWizard={
                                        <BulkQrWizardFields
                                            workspaceId={workspaceId}
                                            locationId={locationId}
                                            menuId={menuId}
                                            hasCurrentPublication={current !== null}
                                            unavailableReason={reasonKind}
                                            onCreated={() => setReloadToken((token) => token + 1)}
                                            onUpgrade={() => onNavigateToSection?.('billing')}
                                        />
                                    }
                                />

                                <QrPrintLookStep
                                    plan={plan}
                                    onChange={updatePlan}
                                    brandPrimaryColor={brandPrimaryColor}
                                    onEditBrand={() => onNavigateToSection?.('brand')}
                                />
                            </div>

                            <div className="min-w-[16rem] flex-[1_1_18rem]">
                                <QrPrintPreviewPanel code={selected[0] ?? null} plan={plan} />
                            </div>
                        </div>

                        {workspaceId !== undefined && locationId !== null ? (
                            <QrPrintActionBar
                                workspaceId={workspaceId}
                                locationId={locationId}
                                plan={plan}
                                selected={selected}
                                scopeName={scopeName}
                            />
                        ) : null}

                        {/*
                            KESİLECEK TABAKA — çok kartlı, tek kâğıtlı, KENDİ
                            yerleşimi olan bir çıktı: sayfa başına on iki kart
                            ve kesme çizgileri. Alt çubuğun "Yazdır"ı değil,
                            çünkü yukarıda seçilen ölçüyü ve tasarımı taşımaz;
                            o adla sunulsaydı sahibin eline seçtiğinden başka
                            bir kâğıt çıkardı. Tek kodlu bir kafeye "12'li
                            sayfa" önermek de olmayan bir işi önermektir.
                        */}
                        {activeCodes.length > 1 &&
                        workspaceId !== undefined &&
                        locationId !== null ? (
                            <section
                                aria-label={t('workspace.publication.qrScreen.sheet')}
                                className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]"
                            >
                                <h2 className="text-body font-bold text-fg">
                                    {t('workspace.publication.qrScreen.sheet')}
                                </h2>
                                <p className="text-body text-fg-secondary">
                                    {t('workspace.publication.qrScreen.sheet.why')}
                                </p>
                                <span className="flex flex-wrap items-center gap-[var(--space-2)]">
                                    {Array.from({ length: chunkCount }, (_unused, index) => (
                                        <ActionLink
                                            key={index}
                                            variant="secondary"
                                            href={printSheetUrl(workspaceId, locationId, index + 1)}
                                        >
                                            {chunkCount === 1
                                                ? t('workspace.publication.qrScreen.downloadAll')
                                                : t(
                                                      'workspace.publication.qrScreen.downloadAllPart',
                                                      {
                                                          part: String(index + 1),
                                                          total: String(chunkCount),
                                                      },
                                                  )}
                                        </ActionLink>
                                    ))}
                                </span>
                            </section>
                        ) : null}

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
                                            Toplu sihirbaz ekranın 2. adımında
                                            duruyor; burada ikinci bir kopyası
                                            çizilirse aynı formun iki hâli
                                            ayrışır ve sahip hangisine yazdığını
                                            bilemez.
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
