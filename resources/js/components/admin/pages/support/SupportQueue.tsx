import { t } from '../../../../i18n/platform';
import { Button } from '../../../catalog/forms/micro/Button';
import { Select } from '../../../catalog/forms/micro/Select';
import { OpsCard } from '../../../ops/OpsCard';

export type SupportQueueRow = {
    id: number;
    reference: string;
    workspace_id: number | null;
    name: string;
    email: string;
    subject: string;
    message: string;
    channel: string;
    status: string;
    received_at: string;
    first_response_at: string | null;
    acknowledgement: string;
};

export type SupportQueueProps = {
    rows: SupportQueueRow[];
    status: string;
    busy: boolean;
    onStatusFilter: (status: string) => void;
    onChangeStatus: (id: number, status: string) => void;
};

/**
 * DESTEK KUYRUĞU — `docs/125` §6'nın kapanışı.
 *
 * O belge şunu yazıyordu: *"Uçlar var, ekran yok… Ekran `docs/122` Y7 ile
 * birlikte gelir; o güne kadar süperadmin talepleri sahibe giden bildirim
 * e-postasından okur."* Bu bileşen o cümleyi kapatıyor.
 *
 * KUYRUK EN ESKİ ÜSTTE ve bu sunucunun kararı: bir destek kuyruğunda en
 * yeni değil, EN UZUN BEKLEYEN önce cevaplanır. Ekran sıralamayı yeniden
 * kurmaz, sunucudan geleni çizer.
 *
 * CEVAP BURADA YAZILMAZ. Ürün hâlâ bir cevap yazma yüzeyi taşımıyor
 * (`docs/125` §9) ve bu ekran öyle bir yüzey varmış gibi davranmıyor: sahip
 * adresi ve referans burada, cevap e-postayla yazılıyor. Var olmayan bir
 * kutuyu çizmek, cevabın gittiğini sandırırdı.
 *
 * DURUM DEĞİŞTİRME BİR KAYIT FİİLİDİR, bir cevap değil. İlk `answered`
 * geçişi ilk yanıt damgasını bir kez atar — "kaç saatte cevap verdik"
 * ölçümünün kaynağı.
 */
export function SupportQueue({
    rows,
    status,
    busy,
    onStatusFilter,
    onChangeStatus,
}: SupportQueueProps) {
    return (
        <OpsCard
            title={t('platform.supportQueue.title')}
            toolbar={
                <Select
                    aria-label={t('platform.supportQueue.filter')}
                    value={status}
                    onChange={(event) => {
                        onStatusFilter(event.target.value);
                    }}
                >
                    <option value="">{t('platform.supportQueue.filter.all')}</option>
                    <option value="received">{t('platform.supportQueue.status.received')}</option>
                    <option value="answered">{t('platform.supportQueue.status.answered')}</option>
                    <option value="closed">{t('platform.supportQueue.status.closed')}</option>
                </Select>
            }
        >
            {rows.length === 0 ? (
                <p className="text-body text-fg-muted">{t('platform.supportQueue.empty')}</p>
            ) : (
                <ul className="flex flex-col gap-[var(--space-4)]">
                    {rows.map((row) => (
                        <li key={row.id} className="flex flex-col gap-[var(--space-1)]">
                            <span className="text-meta tabular-nums text-fg-muted">
                                {row.received_at} · {row.channel} · {row.status}
                            </span>
                            <span className="text-body font-medium text-fg">
                                {row.reference} · {row.subject}
                            </span>
                            {/*
                                ADRES KIRILARAK sığar: 320 pikselde uzun bir
                                e-posta adresi, kırılmazsa sayfayı yana
                                kaydırır.
                            */}
                            <span className="break-all text-meta text-fg-muted">
                                {row.name} · {row.email}
                            </span>
                            <p className="text-body text-fg-secondary">{row.message}</p>
                            {row.acknowledgement !== 'sent' && (
                                <p className="text-meta text-fg-danger">
                                    {t('platform.supportQueue.noAcknowledgement')}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-[var(--space-2)]">
                                {row.status !== 'answered' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => {
                                            onChangeStatus(row.id, 'answered');
                                        }}
                                    >
                                        {t('platform.supportQueue.markAnswered')}
                                    </Button>
                                )}
                                {row.status !== 'closed' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        color="light"
                                        disabled={busy}
                                        onClick={() => {
                                            onChangeStatus(row.id, 'closed');
                                        }}
                                    >
                                        {t('platform.supportQueue.markClosed')}
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <p className="mt-[var(--space-3)] text-meta text-fg-muted">
                {t('platform.supportQueue.noReplySurface')}
            </p>
        </OpsCard>
    );
}

export default SupportQueue;
