import { t } from '../../../../i18n/workspace';
import { Button } from '../../../catalog/forms/micro/Button';

/** Düğmenin neden kapalı olduğunu belirleyen durum (FF-108). */
export type QrCreateReasonKind = 'notPublished' | 'loading' | 'unknown';

type QrDestinationFieldsRegionProps = {
    disabled: boolean;
    onCreate: () => void;
    reasonKind?: QrCreateReasonKind;
};

const REASON_KEYS: Record<QrCreateReasonKind, Parameters<typeof t>[0]> = {
    notPublished: 'workspace.publication.qrDestination.fields.unavailable',
    loading: 'workspace.publication.qrDestination.fields.checking',
    unknown: 'workspace.publication.qrDestination.statusUnknown',
};

/**
 * Kod oluşturma — gerçek bir yayın onaylanana kadar kapalı; asla sahte bir
 * token üretilmez.
 *
 * KAPALI OLMANIN SEBEBİ SÖYLENİR ve sebep duruma göre değişir (FF-108).
 * Önceden tek bir cümle vardı — "önce menünüzü yayınlayın" — ve yayın bilgisi
 * yoldayken ya da sunucuya ulaşılamadığında da o yazıyordu: yayında bir
 * menüsü olan sahibe yanlış bir iş yaptırıyordu.
 *
 * Düğme artık katalog `Button`'ı: elle kurulan sürüm `rounded-sm` ve `py-1`
 * ile sistemin dokunma yüksekliğinin dışına düşüyordu.
 */
export function QrDestinationFieldsRegion({
    disabled,
    onCreate,
    reasonKind = 'notPublished',
}: QrDestinationFieldsRegionProps) {
    return (
        <div className="flex flex-col gap-2">
            <Button type="button" color="light" disabled={disabled} onClick={onCreate}>
                {t('workspace.publication.qrDestination.createButton')}
            </Button>

            {disabled ? (
                <p role="status" className="text-body text-fg-muted">
                    {t(REASON_KEYS[reasonKind])}
                </p>
            ) : null}
        </div>
    );
}

export default QrDestinationFieldsRegion;
