import { useState } from 'react';

import { t } from '../../../../i18n/platform';
import { Button } from '../../../catalog/forms/micro/Button';
import { Select } from '../../../catalog/forms/micro/Select';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
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
    /** Satırın kiracısını tek tıkla açar — yalnız `workspace_id` varsa çağrılır. */
    onOpenWorkspace: (workspaceId: number) => void;
    /**
     * Cevabı yollar. `true` dönerse taslak silinir; başka her şeyde (hata,
     * `void`) taslak DURUR — üç paragraf yazıp taşıyıcı arızasında onu
     * kaybetmek, görevliyi baştan yazmaya ya da hiç yazmamaya iter.
     */
    onReply: (id: number, body: string) => Promise<boolean> | void;
    /** Hangi satırda ne patladı — sanitize edilmiş cümle, sunucudan. */
    replyError: { id: number; message: string } | null;
    /**
     * `SUPPORT_EMAIL` var mı? Yalnız KESİN yokken uyarılır: ilk yükte
     * cevap bilinmiyorken uyarmak, olmayan bir eksikliği ilan etmek olurdu.
     */
    replyToConfigured: boolean;
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
 * CEVAP ARTIK BURADA YAZILIR (SUPPORT-REPLY-01). Her satırın kendi kutusu
 * ve kendi düğmesi var; "bu üründe cevap yazma yüzeyi yok" cümlesi bugünden
 * itibaren YANLIŞTIR ve ekranda durması olmayan bir eksikliği ilan etmek
 * olurdu. Etiket REFERANSI TAŞIR: iki satırın kutusu aynı adı taşısaydı,
 * ekran okuyucuyla çalışan görevli hangi talebe yazdığını yalnız sırayı
 * sayarak bilebilirdi.
 *
 * ÇİFT TIKLAMA İKİ E-POSTA DEMEKTİR. Sunucuda yinelenen gönderimi eleyen
 * bir anahtar YOK; tek savunma burada: gönderim sürerken düğme basılamaz.
 *
 * DURUM DEĞİŞTİRME BİR KAYIT FİİLİDİR, bir cevap değil. İlk `answered`
 * geçişi ilk yanıt damgasını bir kez atar — "kaç saatte cevap verdik"
 * ölçümünün kaynağı; hiçbir şey göndermez.
 *
 * MASA DÜĞMESİ YALNIZ HESABI OLAN SATIRDADIR. Herkese açık iletişim
 * formundan gelen talebin `workspace_id`'si yoktur; ona da aynı düğmeyi
 * çizmek, açılamayacak bir masayı vaat etmek olurdu. Düğme yerine o
 * satırda NEDENİ yazar — eksik bir eylem, açıklanmamış bir boşluktan
 * iyidir.
 */
export function SupportQueue({
    rows,
    status,
    busy,
    onStatusFilter,
    onChangeStatus,
    onOpenWorkspace,
    onReply,
    replyError,
    replyToConfigured,
}: SupportQueueProps) {
    /*
        TASLAK SATIR BAZINDA ve YEREL. Sunucuda saklanan bir taslak, hiç
        gönderilmemiş bir cevabı kalıcı veri yapardı; bu paket yeni tablo
        eklemiyor. Kuyruk yenilendiğinde satır kimliği aynı kaldığı için
        yazılan cümle de yerinde kalır.
    */
    const [drafts, setDrafts] = useState<Record<number, string>>({});

    async function send(id: number) {
        const body = (drafts[id] ?? '').trim();

        if (busy || body === '') return;

        const sent = await onReply(id, body);

        // Yalnız GERÇEKTEN çıkan bir cevap kutuyu temizler.
        if (sent === true) {
            setDrafts((previous) => ({ ...previous, [id]: '' }));
        }
    }

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
                            {row.workspace_id === null ? (
                                <p className="text-meta text-fg-muted">
                                    {t('platform.supportQueue.noWorkspace')}
                                </p>
                            ) : null}
                            <div className="flex flex-wrap gap-[var(--space-2)]">
                                {row.workspace_id !== null && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => {
                                            // Kontrol tıklama ANINDA yeniden
                                            // yapılır: derleyici, sonradan
                                            // çalışacak bir geri çağırmanın
                                            // içinde dışarıdaki daraltmayı
                                            // geçerli saymaz.
                                            if (row.workspace_id !== null) {
                                                onOpenWorkspace(row.workspace_id);
                                            }
                                        }}
                                    >
                                        {t('platform.supportQueue.openDesk', {
                                            reference: row.reference,
                                        })}
                                    </Button>
                                )}
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
                            {/*
                                CEVAP KUTUSU EYLEMLERİN ALTINDA: satırın
                                hikâyesi okunur (kim, ne yazdı), sonra
                                cevaplanır. 320 pikselde kutu tam genişlik,
                                düğme kendi satırında.
                            */}
                            <div className="flex flex-col gap-[var(--space-2)]">
                                <Textarea
                                    aria-label={t('platform.supportQueue.reply.label', {
                                        reference: row.reference,
                                    })}
                                    rows={3}
                                    value={drafts[row.id] ?? ''}
                                    onChange={(event) => {
                                        setDrafts((previous) => ({
                                            ...previous,
                                            [row.id]: event.target.value,
                                        }));
                                    }}
                                />
                                {replyError?.id === row.id && (
                                    <p role="alert" className="text-meta text-fg-danger">
                                        {replyError.message}
                                    </p>
                                )}
                                <Button
                                    type="button"
                                    size="sm"
                                    className="self-start"
                                    disabled={busy || (drafts[row.id] ?? '').trim() === ''}
                                    onClick={() => void send(row.id)}
                                >
                                    {t('platform.supportQueue.reply.send', {
                                        reference: row.reference,
                                    })}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {/*
                CEVAP ADRESİ YOKSA SÖYLENİR — bir kez, kartın altında: bu bir
                yapılandırma olgusudur, satırın bir özelliği değil. UYARI
                DEĞİL, bir not: gönderim yine çıkar, müşteri yalnız
                "cevapla"ya basamaz.
            */}
            {!replyToConfigured && (
                <p className="mt-[var(--space-3)] text-meta text-fg-danger">
                    {t('platform.supportQueue.noReplyTo')}
                </p>
            )}
            <p className="mt-[var(--space-3)] text-meta text-fg-muted">
                {t('platform.supportQueue.replyNote')}
            </p>
        </OpsCard>
    );
}

export default SupportQueue;
