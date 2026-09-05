import { useCallback, useEffect, useRef, useState } from 'react';
import { ArrowsIn, ArrowsOut, CookingPot, ForkKnife } from '@phosphor-icons/react';

import { t } from '../../../i18n/workspace';
import { changeOrderStatus } from '../pages/orders/changeOrderStatus';
import {
    orderAllergens,
    updatedAtLabel,
    waitingLabel,
    waitingMinutes,
} from '../pages/orders/orderPresentation';
import { useOrderFeed, type OrderFeedRow } from '../pages/orders/useOrderFeed';

/**
 * MUTFAK MONİTÖRÜ — `docs/115` S5, hikâyeler K1–K5 (FF-179).
 *
 * Sahibin cümlesi: "Monitör, restoran Admin tarafında vardır. Admin panelde
 * bu monitör içeriğinin tam ekran olabilmesi için gereken UI olmalıdır."
 *
 * ## Bu dosya neden `kitchen/` altında
 *
 * Mutfak monitörü MASAÜSTÜ/TABLET yüzeyidir ve kodu telefon paketine hiç
 * inmez (`docs/54`). Ayrım MODÜL SINIRINDA yapılır: bu klasör yalnız
 * masaüstü giriş noktasından ulaşılır ve `scripts/adaptive-bundle-gate`
 * bunu her koşuda kanıtlar. Bileşenin içinde bir `deviceClass` dalı olsaydı,
 * kod yine indirilir ve ayrıştırılırdı — yalnız görünmezdi.
 *
 * Gerekçe estetik değil: bu ekran duvara asılan bir monitör içindir. 320
 * pikselde okunacak bir tipografiyle üç metre uzaktan okunacak bir tipografi
 * aynı bileşende yaşayamaz; ikisini tek bir dosyada tutmak, ikisini de
 * ortalamak olurdu.
 *
 * ## Tam ekran
 *
 * Fullscreen API DESTEKLENMİYORSA DÜĞME ÇİZİLMEZ; yerine tarayıcının kendi
 * tuşunu kullanmasını söyleyen dürüst bir cümle durur. Basıldığında hiçbir
 * şey yapmayan bir düğme, mutfakta bir kez denenip bir daha güvenilmeyen
 * bir arayüz bırakır.
 */
export type KitchenMonitorProps = {
    workspaceId: number;
    locationId: number;
    /** Bu kullanıcı ocağı ilerletebilir mi (`order.kitchen`)? */
    canAdvance: boolean;
    /** Tabağı masaya teslim edebilir mi (`order.confirm`)? */
    canDeliver: boolean;
};

type RowStage = 'idle' | 'busy' | 'conflict' | 'failed';

export function KitchenMonitor({
    workspaceId,
    locationId,
    canAdvance,
    canDeliver,
}: KitchenMonitorProps) {
    const feed = useOrderFeed(
        `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/orders/kitchen`,
    );
    const boardRef = useRef<HTMLElement>(null);
    const [fullscreen, setFullscreen] = useState(false);
    const [stages, setStages] = useState<Record<number, RowStage>>({});

    /*
        YETENEK ÇALIŞMA ANINDA SORULUR, tarayıcı adından tahmin edilmez.
        `requestFullscreen` yoksa yetenek yoktur; kullanıcı aracısı dizesine
        bakan bir kontrol, yarın adı değişen bir tarayıcıda yanlış cevap
        verirdi.
    */
    const fullscreenAvailable =
        typeof document !== 'undefined' &&
        typeof document.documentElement.requestFullscreen === 'function';

    useEffect(() => {
        function handleChange(): void {
            setFullscreen(Boolean(document.fullscreenElement));
        }

        document.addEventListener('fullscreenchange', handleChange);

        return () => document.removeEventListener('fullscreenchange', handleChange);
    }, []);

    const toggleFullscreen = useCallback(async (): Promise<void> => {
        try {
            /*
                TRUTHY KONTROL, `!== null` DEĞİL.

                Fullscreen API'yi hiç uygulamayan bir ortamda
                `document.fullscreenElement` `null` değil TANIMSIZDIR; katı
                bir `!== null` orada "zaten tam ekrandayız" der ve var
                olmayan bir `exitFullscreen`'i çağırır. Sonuç, düğmeye
                basıldığında hiçbir şey olmamasıdır — ve bunu mutfakta
                keşfetmek en pahalı yoldur.
            */
            if (document.fullscreenElement) {
                await document.exitFullscreen();

                return;
            }

            await boardRef.current?.requestFullscreen();
        } catch {
            /*
                Tarayıcı reddedebilir (kullanıcı hareketi yok, izin kapalı).
                Sessizce yutuluyor ve bu bilinçli: `fullscreenchange`
                tetiklenmediği için düğme kendiliğinden eski hâlinde kalır,
                yani ekran YALAN SÖYLEMEZ. Ayrıca bir hata kutusu basmak,
                mutfak duvarındaki ekranı kimsenin kapatamayacağı bir
                uyarıyla doldururdu.
            */
        }
    }, []);

    const advance = useCallback(
        async (orderId: number, status: 'preparing' | 'ready' | 'delivered'): Promise<void> => {
            setStages((current) => ({ ...current, [orderId]: 'busy' }));

            const result = await changeOrderStatus(workspaceId, locationId, orderId, status);

            setStages((current) => ({
                ...current,
                [orderId]:
                    result.outcome === 'ok'
                        ? 'idle'
                        : result.outcome === 'conflict'
                          ? 'conflict'
                          : 'failed',
            }));

            if (result.outcome !== 'failed') {
                feed.refresh();
            }
        },
        [feed, locationId, workspaceId],
    );

    const timeZone = feed.orders[0]?.timeZone ?? null;

    return (
        <section
            ref={boardRef}
            aria-label={t('workspace.orders.kitchen.region')}
            /*
                TAM EKRANDA ARKA PLAN AÇIKÇA BOYANIR. Tam ekrana alınan bir
                eleman belgenin arka planını yanına ALMAZ; boyanmasaydı,
                mutfak duvarındaki ekran siyah bir çerçevenin ortasında
                yüzen bir liste gösterirdi.
            */
            className="flex flex-col gap-[var(--space-4)] bg-surface p-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-center justify-between gap-[var(--space-3)]">
                <p className="text-meta text-fg-muted" data-testid="kitchen-updated-at">
                    {updatedAtLabel(feed.lastUpdatedAt, timeZone)}
                </p>

                {fullscreenAvailable ? (
                    <button
                        type="button"
                        onClick={() => void toggleFullscreen()}
                        className="flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-border px-[var(--space-4)] text-body font-medium text-fg"
                    >
                        {fullscreen ? (
                            <ArrowsIn size={24} weight="bold" aria-hidden="true" />
                        ) : (
                            <ArrowsOut size={24} weight="bold" aria-hidden="true" />
                        )}
                        {fullscreen
                            ? t('workspace.orders.kitchen.fullscreen.exit')
                            : t('workspace.orders.kitchen.fullscreen')}
                    </button>
                ) : (
                    <p className="text-meta text-fg-secondary">
                        {t('workspace.orders.kitchen.fullscreen.unavailable')}
                    </p>
                )}
            </div>

            {feed.stale ? (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.orders.stale')}
                </p>
            ) : null}

            {feed.status === 'loading' ? (
                <p role="status" className="text-section text-fg">
                    {t('workspace.orders.kitchen.loading')}
                </p>
            ) : null}

            {feed.status === 'error' ? (
                <p role="alert" className="text-section text-fg-danger">
                    {t('workspace.orders.kitchen.error.title')}
                </p>
            ) : null}

            {feed.status === 'ready' && feed.orders.length === 0 ? (
                <div className="flex flex-col items-center gap-[var(--space-3)] py-[var(--space-7)] text-center">
                    <CookingPot size={48} weight="regular" aria-hidden="true" />
                    <p className="text-monitor font-bold text-fg">
                        {t('workspace.orders.kitchen.empty.title')}
                    </p>
                    {/* K1: bekleyen sipariş mutfağa HİÇ görünmez. Boş ekran
                        bunu söylemezse aşçı garsona "sipariş gelmiyor mu"
                        diye sorar. */}
                    <p className="max-w-[60ch] text-section text-fg-secondary">
                        {t('workspace.orders.kitchen.empty.description')}
                    </p>
                </div>
            ) : null}

            <ul className="grid grid-cols-[repeat(auto-fill,minmax(22rem,1fr))] gap-[var(--space-4)]">
                {feed.orders.map((order) => (
                    <KitchenTicket
                        key={order.id}
                        order={order}
                        clockOffsetMs={feed.clockOffsetMs}
                        stage={stages[order.id] ?? 'idle'}
                        canAdvance={canAdvance}
                        canDeliver={canDeliver}
                        onAdvance={(status) => void advance(order.id, status)}
                    />
                ))}
            </ul>
        </section>
    );
}

/**
 * Tek bir fiş — UZAKTAN OKUNUR (`docs/115` K5).
 *
 * Tipografi burada bilerek panelin geri kalanından büyüktür: bu ekran
 * masanın başında değil, mutfağın duvarında durur. Masa adı `text-monitor`,
 * satırlar `text-section`; ölçüler token katmanından gelir, elle piksel
 * yazılmaz.
 *
 * Hedefler 44 pikselden büyük (K3): eller meşguldür ve ekrana bakmadan
 * basılır.
 */
function KitchenTicket({
    order,
    clockOffsetMs,
    stage,
    canAdvance,
    canDeliver,
    onAdvance,
}: {
    order: OrderFeedRow;
    clockOffsetMs: number;
    stage: RowStage;
    canAdvance: boolean;
    canDeliver: boolean;
    onAdvance: (status: 'preparing' | 'ready' | 'delivered') => void;
}) {
    const allergens = orderAllergens(order);

    return (
        <li className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border-2 border-border bg-surface p-[var(--space-5)]">
            <div className="flex items-baseline justify-between gap-[var(--space-3)]">
                <h3 className="text-monitor font-bold tracking-tight text-fg">
                    {t('workspace.orders.table', { name: order.tableName })}
                </h3>
                <p className="text-section font-medium text-fg-secondary">
                    {waitingLabel(waitingMinutes(order.placedAt, clockOffsetMs))}
                </p>
            </div>

            <p className="text-section text-fg-secondary">
                {order.status === 'preparing'
                    ? t('workspace.orders.kitchen.status.preparing')
                    : order.status === 'ready'
                      ? t('workspace.orders.kitchen.status.ready')
                      : t('workspace.orders.kitchen.status.confirmed')}
            </p>

            <ul className="flex flex-col gap-[var(--space-2)]">
                {order.lines.map((line, index) => (
                    <li key={index} className="text-section font-medium text-fg">
                        {t('workspace.orders.quantity', { count: String(line.quantity) })}{' '}
                        {line.productName}
                    </li>
                ))}
            </ul>

            {allergens.length > 0 ? (
                /*
                    ALERJEN FİŞTE, ve gözden kaçmayacak yerde (K4). Yanlış
                    ya da görülmemiş bir alerjen bilgisi bir sağlık olayıdır;
                    "ürün sayfasında yazıyordu" bir savunma değildir.
                */
                <p className="rounded-[var(--radius-md)] border border-border-danger bg-surface-danger p-[var(--space-2)] text-section font-bold text-fg-danger">
                    {t('workspace.orders.allergens', { list: allergens.join(', ') })}
                </p>
            ) : null}

            {stage === 'conflict' ? (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.orders.conflict', { status: order.status })}
                </p>
            ) : null}

            {stage === 'failed' ? (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.orders.actionFailed')}
                </p>
            ) : null}

            <div className="mt-auto flex flex-wrap gap-[var(--space-2)]">
                {order.status === 'confirmed' && canAdvance ? (
                    <button
                        type="button"
                        disabled={stage === 'busy'}
                        onClick={() => onAdvance('preparing')}
                        className="flex min-h-[56px] flex-1 items-center justify-center gap-2 rounded-[var(--radius-md)] border-2 border-border bg-surface-accent px-[var(--space-4)] text-section font-bold text-fg"
                    >
                        <CookingPot size={28} weight="bold" aria-hidden="true" />
                        {t('workspace.orders.kitchen.start')}
                    </button>
                ) : null}

                {order.status === 'preparing' && canAdvance ? (
                    <button
                        type="button"
                        disabled={stage === 'busy'}
                        onClick={() => onAdvance('ready')}
                        className="flex min-h-[56px] flex-1 items-center justify-center gap-2 rounded-[var(--radius-md)] border-2 border-border bg-surface-accent px-[var(--space-4)] text-section font-bold text-fg"
                    >
                        <ForkKnife size={28} weight="bold" aria-hidden="true" />
                        {t('workspace.orders.kitchen.ready')}
                    </button>
                ) : null}

                {/*
                    "Teslim edildi" MUTFAĞIN değil servisin cümlesidir: tabağı
                    masaya götüren kişi bilir. Aşçıda `order.confirm` yoktur
                    ve bu düğme ona hiç çizilmez — sunucu zaten 403 döner,
                    ekranın işi o 403'ü yaşatmamaktır.
                */}
                {order.status === 'ready' && canDeliver ? (
                    <button
                        type="button"
                        disabled={stage === 'busy'}
                        onClick={() => onAdvance('delivered')}
                        className="flex min-h-[56px] flex-1 items-center justify-center rounded-[var(--radius-md)] border-2 border-border px-[var(--space-4)] text-section font-bold text-fg"
                    >
                        {t('workspace.orders.kitchen.deliver')}
                    </button>
                ) : null}
            </div>
        </li>
    );
}

export default KitchenMonitor;
