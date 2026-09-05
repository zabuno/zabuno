import { useCallback, useEffect, useId, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { PageState } from '../shared/PageState';

/**
 * SİPARİŞ ALMAYI AÇ/KAPAT — `docs/115` S6, Y1 (FF-179).
 *
 * Göç bu sütunu VARSAYILAN KAPALI yazdı; ekran o kararı görünür kılar.
 * Sipariş alma, panelde birinin BAKMASINI gerektiren tek yetenektir —
 * kendiliğinden açılsaydı, güncelledikten sonra hiçbir şey yapmayan bir
 * restoranın mutfağına sessizce iş düşerdi. Yardım cümlesi bunu söyler,
 * çünkü şalteri açan kişi ne söz verdiğini bilmelidir.
 *
 * YÖNETİCİYE DÜĞME ÇİZİLMEZ (`docs/59`: yapılamayan iş çizilmez). Sunucu
 * zaten 403 döner; ekranın işi o 403'ü kullanıcıya yaşatmamaktır.
 *
 * ═══ Y3: PLAN, ŞALTERDEN ÖNCE GELİR ═══
 *
 * Ölçülen kusur: şalter açılabiliyordu ama plan sipariş almayı içermiyorsa
 * misafirin siparişi reddediliyordu. Sahip hizmeti açtığını sanıyor, mutfağa
 * hiçbir şey düşmüyordu. Aynı kural burada da geçerli: yapılamayan iş
 * çizilmez — ama SEBEBİ yazılır, çünkü sebepsiz kapalı bir kapı sahibi
 * ekranda arayışa iter.
 *
 * HAK SONRADAN DÜŞERSE ŞALTER SESSİZCE KAPATILMAZ. Ekran "açık ama
 * çalışmıyor" gerçeğini söyler ve ayarı sahibin elinde bırakır; sunucunun
 * kendi başına kapatması, sahibin planı geri geldiğinde neyi kaybettiğini
 * bilmemesi demek olurdu.
 */
export type OrderingSwitchRegionProps = {
    workspaceId: number;
    locationId: number;
    /** Şalter değiştiğinde kuyruk ekranı boş listeyi doğru anlatabilsin diye. */
    onChange?: (acceptsOrders: boolean) => void;
    /**
     * Plan kapısının GERÇEK çıkış yolu.
     *
     * Yoksa düğme çizilmez: basıldığında hiçbir yere gitmeyen bir düğme,
     * sahibi ikinci kez çıkışsız bırakırdı.
     */
    onNavigateToPlan?: () => void;
};

type Status = 'loading' | 'ready' | 'error';

export function OrderingSwitchRegion({
    workspaceId,
    locationId,
    onChange,
    onNavigateToPlan,
}: OrderingSwitchRegionProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [acceptsOrders, setAcceptsOrders] = useState(false);
    const [canManage, setCanManage] = useState(false);
    /*
        VARSAYILAN `true` VE BU BİLİNÇLİ.

        Alan gelmezse ekran OLMAYAN bir kısıt uydurmaz. Uydursaydı, sunucunun
        bir gün alanı düşürmesi bütün restoranlara "planınızda yok" yazdırır
        ve kimse şalteri açamazdı — sessiz bir arıza yerine gürültülü ve
        yanlış bir arıza. Gerçek kapı zaten sunucuda.
    */
    const [planIncludesOrdering, setPlanIncludesOrdering] = useState(true);
    const [saving, setSaving] = useState(false);
    const [saveFailed, setSaveFailed] = useState(false);
    const fieldId = useId();

    const url = `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/ordering`;

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as {
                    acceptsOrders?: boolean;
                    canManage?: boolean;
                    planIncludesOrdering?: boolean;
                };

                if (cancelled) {
                    return;
                }

                setAcceptsOrders(body.acceptsOrders === true);
                setCanManage(body.canManage === true);
                setPlanIncludesOrdering(body.planIncludesOrdering !== false);
                setStatus('ready');
                onChange?.(body.acceptsOrders === true);
            } catch {
                if (!cancelled) {
                    setStatus('error');
                }
            }
        })();

        return () => {
            cancelled = true;
        };
        // `onChange` bilerek bağımlılıkta değil: çağıranın her karede yeniden
        // kurduğu bir işlev, şalteri sonsuz bir okuma döngüsüne sokardı.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    const toggle = useCallback(
        async (next: boolean): Promise<void> => {
            setSaving(true);
            setSaveFailed(false);

            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    url,
                    buildAuthRequestInit({
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ acceptsOrders: next }),
                    }),
                );

                if (!response.ok) {
                    /*
                        DEĞER GERİ ALINMAZ, çünkü hiç İLERİ ALINMADI: şalter
                        ancak sunucu evet dedikten sonra hareket eder. İyimser
                        bir çevirme, tutmayan bir istekten sonra sahibe
                        "sipariş alıyorum" diyen kapalı bir şube bırakırdı.
                    */
                    setSaveFailed(true);

                    return;
                }

                setAcceptsOrders(next);
                onChange?.(next);
            } catch {
                setSaveFailed(true);
            } finally {
                setSaving(false);
            }
        },
        [onChange, url],
    );

    if (status === 'loading') {
        return (
            <PageState
                kind="loading"
                screen="orders_settings"
                title={t('workspace.orders.settings.loading')}
            />
        );
    }

    if (status === 'error') {
        return (
            <PageState
                kind="error"
                screen="orders_settings"
                title={t('workspace.orders.settings.error.title')}
                description={t('workspace.orders.settings.error.description')}
                whyNoAction={t('workspace.orders.settings.error.description')}
            />
        );
    }

    return (
        <section
            aria-label={t('workspace.orders.settings.region')}
            className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-center justify-between gap-[var(--space-3)]">
                <label htmlFor={fieldId} className="text-body font-medium text-fg">
                    {t('workspace.orders.settings.switch.label')}
                </label>
                <input
                    id={fieldId}
                    type="checkbox"
                    role="switch"
                    checked={acceptsOrders}
                    /*
                        Yetkisi olmayan için düğme DEVRE DIŞI değil, KAPALI bir
                        kapı olarak açıklanır: devre dışı bir kutu neden
                        basılamadığını söylemez. Sebep her zaman altında yazar.

                        PLAN EKSİKKEN KİLİT TEK YÖNLÜ: açılamaz ama kapanabilir.
                        Şalter hak düştükten sonra açık kalmış olabilir ve
                        sahibi kendi hizmetini kapatamadığı bir ekranda
                        bırakmak, planı düşmüş bir restoranı kilitlemek olurdu.
                    */
                    disabled={!canManage || saving || (!planIncludesOrdering && !acceptsOrders)}
                    onChange={(event) => void toggle(event.target.checked)}
                    className="h-6 w-11"
                />
            </div>

            <p role="status" className="text-body text-fg-secondary">
                {acceptsOrders
                    ? t('workspace.orders.settings.switch.on')
                    : t('workspace.orders.settings.switch.off')}
            </p>

            <p className="text-meta text-fg-secondary">
                {t('workspace.orders.settings.switch.help')}
            </p>

            {!planIncludesOrdering ? (
                /*
                    `status`, `alert` DEĞİL: ortada bozulmuş bir şey yok
                    (`docs/59`). Plan kısıtını hata gibi sunmak, sahibi
                    hiçbir zaman işe yaramayacak bir "tekrar dene"ye iter —
                    bu depoda Analytics'in 402 yanıtında bir kez yaşandı.

                    İKİ AYRI CÜMLE, çünkü iki ayrı gerçek: kapalı şalterde
                    "açamazsın", açık şalterde "açık görünüyor ama hiçbir
                    sipariş gelemiyor". İkisini tek cümleye toplamak,
                    ikincisinde sahibin ekranda okuduğu "Ordering is on"
                    satırını yalana çevirirdi.
                */
                <div role="status" className="flex flex-col items-start gap-[var(--space-2)]">
                    <p className="text-body text-fg-secondary">
                        {t(
                            acceptsOrders
                                ? 'workspace.orders.settings.plan.stuckOn'
                                : 'workspace.orders.settings.plan.missing',
                            { name: t('workspace.orders.plan.name') },
                        )}
                    </p>
                    {onNavigateToPlan ? (
                        <button
                            type="button"
                            onClick={onNavigateToPlan}
                            className="min-h-[44px] text-body underline"
                        >
                            {t('workspace.orders.plan.action')}
                        </button>
                    ) : null}
                </div>
            ) : null}

            {!canManage ? (
                <p className="text-meta text-fg-muted">
                    {t('workspace.orders.settings.switch.readOnly')}
                </p>
            ) : null}

            {saving ? (
                <p role="status" className="text-meta text-fg-muted">
                    {t('workspace.orders.settings.switch.saving')}
                </p>
            ) : null}

            {saveFailed ? (
                <p role="alert" className="text-meta text-fg-danger">
                    {t('workspace.orders.settings.switch.error')}
                </p>
            ) : null}
        </section>
    );
}

export default OrderingSwitchRegion;
