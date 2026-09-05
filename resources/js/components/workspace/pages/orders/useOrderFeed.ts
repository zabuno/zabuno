import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Sipariş beslemesinin YOKLAMA aralığı — `docs/115` §6 (FF-179).
 *
 * ## Neden yoklama, neden SSE/WebSocket değil
 *
 * Karar ölçülerek verildi, tercih edilerek değil:
 *
 * 1. **Depoda yayın kanalı YOK.** `config/` altında hiçbir broadcasting
 *    yapılandırması bulunmuyor; WebSocket için sunucu, sürücü ve istemci
 *    katmanının üçü birden kurulacaktı.
 * 2. **Hedef barındırmada sürekli çalışan worker YOK** (`docs/15` §
 *    HOST-QUEUE-04): kuyruk cron ile, dakikada bir yürüyor. Yani "anında"
 *    bir kanal bugün zaten yok — bir olayı yayına verecek süreç yok.
 * 3. **SSE bu barındırmada PAHALI.** Sunucu-gönderimli olay akışı, açık her
 *    ekran için bir PHP-FPM işçisini süresiz meşgul eder. Bir restoranda
 *    aynı anda bir garson tableti, bir mutfak monitörü ve bir kasa ekranı
 *    açıksa üç işçi kalıcı olarak kilitlenir; paylaşımlı barındırmada bu,
 *    panelin tamamının cevap veremez hâle gelmesidir. Yoklama isteği ise
 *    işçiyi milisaniyeler içinde geri verir.
 *
 * ## On saniye nereden geliyor
 *
 * Servis anında anlamlı olan gecikme, garsonun masaya bakma sıklığıdır; bir
 * saniyelik tazelik ürüne hiçbir şey katmaz, sunucuya on kat yük bindirir.
 * On saniye, açık ekran başına dakikada altı istek demektir — kuyruk sorgusu
 * indeksli ve sayfalanmış (`orders_service_queue_index`), yani bu yük
 * ölçülebilir ve küçüktür.
 *
 * DEĞER BURADA, TEK YERDE. Ekranlara dağıtılmış bir sayı, biri
 * değiştirildiğinde mutfak monitörüyle garson kuyruğunun farklı hızlarda
 * tazelenmesi demekti — ve bu fark ancak "sipariş neden bende yok" diye
 * bağıran iki kişiyle anlaşılırdı.
 */
export const ORDER_FEED_INTERVAL_MS = 10_000;

export type OrderFeedLine = {
    productName: string;
    quantity: number;
    unitPriceMinorAmount: number;
    lineTotalMinorAmount: number;
    currencyCode: string;
    allergens: string[];
};

export type OrderFeedRow = {
    id: number;
    status: string;
    tableName: string;
    areaLabel: string | null;
    totalMinorAmount: number;
    currencyCode: string;
    rejectionReason: string | null;
    placedAt: string;
    statusChangedAt: string;
    timeZone: string | null;
    lines: OrderFeedLine[];
};

export type OrderFeed = {
    status: 'loading' | 'ready' | 'error';
    orders: OrderFeedRow[];
    /** Son BAŞARILI yanıtın alındığı an. Ekran bunu yazar. */
    lastUpdatedAt: Date | null;
    /**
     * Sunucunun anı ile bu ekranın saati arasındaki fark (ms).
     *
     * Mutfak duvarındaki bir ekranın saati genellikle yanlıştır. "Dokuz
     * dakikadır bekliyor" cümlesi o saatten hesaplanırsa, ekran kendi
     * hatasını misafirin bekleme süresi diye gösterir.
     */
    clockOffsetMs: number;
    /**
     * Son deneme BAŞARISIZ oldu; ekranda duran veri eski olabilir.
     *
     * `error` değildir: ekranda hâlâ kullanılabilir bir liste var. Hepsini
     * silip hata göstermek, servisin ortasında aşçının elindeki tek listeyi
     * almak olurdu.
     */
    stale: boolean;
    refresh: () => void;
};

/**
 * Sipariş beslemesini yoklar; SAYFA GÖRÜNMEZ OLDUĞUNDA DURUR.
 *
 * Durma bir optimizasyon değil, dürüstlüğün parçası: arka planda duran bir
 * monitör sunucuyu boşuna meşgul etmemeli. Zamanlayıcı görünmezken hiç
 * KURULMAZ — "kurulur ama isteği atlar" biçimi, bir gün atlamayı kaldıran
 * tek satırlık bir düzenlemeyle sessizce geri gelirdi.
 *
 * Sekme yeniden görünür olduğunda ilk istek ANINDA gider: aşçı ekrana
 * döndüğünde on saniye eski bir liste görmemeli.
 */
export function useOrderFeed(url: string, enabled = true): OrderFeed {
    const [orders, setOrders] = useState<OrderFeedRow[]>([]);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
    const [lastUpdatedAt, setLastUpdatedAt] = useState<Date | null>(null);
    const [clockOffsetMs, setClockOffsetMs] = useState(0);
    const [stale, setStale] = useState(false);
    const [visible, setVisible] = useState(
        () => typeof document === 'undefined' || document.visibilityState !== 'hidden',
    );
    const [manualTick, setManualTick] = useState(0);

    const mountedRef = useRef(true);
    // İlk yanıt gelmeden ikinci bir denemenin "eskimiş" demesini engeller:
    // hiç veri yokken eskimiş bir şey de yoktur, ortada bir HATA vardır.
    const hasDataRef = useRef(false);

    useEffect(() => {
        mountedRef.current = true;

        return () => {
            mountedRef.current = false;
        };
    }, []);

    const load = useCallback(async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                if (mountedRef.current) {
                    setStale(hasDataRef.current);
                    setStatus(hasDataRef.current ? 'ready' : 'error');
                }

                return;
            }

            const body = (await response.json()) as { data?: OrderFeedRow[]; serverTime?: string };

            if (!mountedRef.current) {
                return;
            }

            const received = new Date();
            const serverMoment = body.serverTime === undefined ? null : new Date(body.serverTime);

            hasDataRef.current = true;
            setOrders(body.data ?? []);
            setStatus('ready');
            setStale(false);
            setLastUpdatedAt(received);

            if (serverMoment !== null && !Number.isNaN(serverMoment.getTime())) {
                setClockOffsetMs(serverMoment.getTime() - received.getTime());
            }
        } catch {
            if (mountedRef.current) {
                setStale(hasDataRef.current);
                setStatus(hasDataRef.current ? 'ready' : 'error');
            }
        }
    }, [url]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        function handleVisibility(): void {
            setVisible(document.visibilityState !== 'hidden');
        }

        document.addEventListener('visibilitychange', handleVisibility);

        return () => document.removeEventListener('visibilitychange', handleVisibility);
    }, []);

    useEffect(() => {
        if (!enabled || !visible) {
            return;
        }

        void load();

        const timer = window.setInterval(() => void load(), ORDER_FEED_INTERVAL_MS);

        return () => window.clearInterval(timer);
    }, [enabled, visible, load, manualTick]);

    const refresh = useCallback(() => setManualTick((tick) => tick + 1), []);

    return { status, orders, lastUpdatedAt, clockOffsetMs, stale, refresh };
}
