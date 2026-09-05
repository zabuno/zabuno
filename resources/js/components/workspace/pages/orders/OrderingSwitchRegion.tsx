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
 */
export type OrderingSwitchRegionProps = {
    workspaceId: number;
    locationId: number;
    /** Şalter değiştiğinde kuyruk ekranı boş listeyi doğru anlatabilsin diye. */
    onChange?: (acceptsOrders: boolean) => void;
};

type Status = 'loading' | 'ready' | 'error';

export function OrderingSwitchRegion({
    workspaceId,
    locationId,
    onChange,
}: OrderingSwitchRegionProps) {
    const [status, setStatus] = useState<Status>('loading');
    const [acceptsOrders, setAcceptsOrders] = useState(false);
    const [canManage, setCanManage] = useState(false);
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
                };

                if (cancelled) {
                    return;
                }

                setAcceptsOrders(body.acceptsOrders === true);
                setCanManage(body.canManage === true);
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
                    // Yetkisi olmayan için düğme DEVRE DIŞI değil, KAPALI bir
                    // kapı olarak açıklanır: devre dışı bir kutu neden
                    // basılamadığını söylemez.
                    disabled={!canManage || saving}
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
