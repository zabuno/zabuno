import { useId, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { Button } from '../../../catalog/forms/micro/Button';
import { Label } from '../../../catalog/forms/micro/Label';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
import { formatMoment } from './ratingPresentation';
import { MAX_REPLY_LENGTH, publishRatingReply, withdrawRatingReply } from './ratingReply';

export type RatingReplyEditorProps = {
    workspaceId: number;
    productId: number;
    /** Yayındaki yanıt — yoksa `null`. */
    body: string | null;
    publishedAt: string | null;
    /** Yayınlanan/geri alınan cümlenin listeye de yansıması için. */
    onSaved: (body: string | null) => void;
};

/**
 * SAHİBİN YANITI — `docs/116` §4 (P6).
 *
 * ═══ BURADA KALDIRILAN ŞEY MİSAFİRİN ÖLÇÜMÜ DEĞİL, RESTORANIN CÜMLESİDİR ═══
 *
 * "Withdraw" düğmesi "sil" düğmesi değildir ve ikisi karıştırılırsa paketin
 * tamamı anlamını yitirir. Yanıt sahibin KENDİ metnidir; düzeltilemez
 * olsaydı, yanlış yazdığı bir cümleye sonsuza kadar mahkûm olurdu ve o cümle
 * misafirin gördüğü menüde dururdu. Puan ise dokunulmazdır.
 *
 * ═══ BOŞ GÖNDERMEK SİLMEK DEĞİLDİR ═══
 *
 * Sunucu boş gövdeyi 422 ile reddediyor ve öyle kalmalı: "boş gönder = sil"
 * bir gün kazayla silinen bir cümledir. Ekran o 422'yi sahibe YAŞATMAZ —
 * kutuyu boş bırakıp yayınlamaya basınca isteği hiç kurmaz ve sebebini
 * yazar.
 */
export function RatingReplyEditor({
    workspaceId,
    productId,
    body,
    publishedAt,
    onSaved,
}: RatingReplyEditorProps) {
    const fieldId = useId();
    const [draft, setDraft] = useState(body ?? '');
    const [saving, setSaving] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);

    /*
        KUTUYU YAYINDAKİ CÜMLEYE GERİ GETİREN BİR ETKİ YOK.

        İlk yazılışında bir `useEffect` bunu yapıyordu ve iki maliyeti vardı:
        her çizimden sonra ikinci bir çizim, ve sahibin YAZMAKTA olduğu bir
        taslağın üstüne yazma riski. Onun yerine bileşen, yayındaki cümleye
        göre anahtarlanıyor (bkz. `RatingsPage`): cümle değiştiğinde React
        kutuyu baştan kurar, sahip yazarken ise anahtar hiç değişmez.
    */
    const tooLong = draft.length > MAX_REPLY_LENGTH;
    const publishedMoment = formatMoment(publishedAt);

    async function publish(): Promise<void> {
        const trimmed = draft.trim();

        if (trimmed === '') {
            setProblem(t('workspace.ratings.reply.empty'));

            return;
        }

        if (trimmed.length > MAX_REPLY_LENGTH) {
            /*
                SESSİZ KIRPMA YASAK. Sahibin cümlesini kırpıp yayınlamak, ona
                yazmadığı bir cümleyi söyletmektir — ve o cümle misafirin
                gördüğü menüde durur.
            */
            setProblem(t('workspace.ratings.reply.tooLong', { max: String(MAX_REPLY_LENGTH) }));

            return;
        }

        setProblem(null);
        setSaving(true);

        const result = await publishRatingReply(workspaceId, productId, trimmed);

        setSaving(false);

        if (result.outcome === 'ok') {
            onSaved(trimmed);

            return;
        }

        setProblem(
            result.outcome === 'rejected' && result.reason === 'reply_too_long'
                ? t('workspace.ratings.reply.tooLong', { max: String(MAX_REPLY_LENGTH) })
                : result.outcome === 'rejected'
                  ? t('workspace.ratings.reply.empty')
                  : t('workspace.ratings.reply.failed'),
        );
    }

    async function withdraw(): Promise<void> {
        setProblem(null);
        setSaving(true);

        const result = await withdrawRatingReply(workspaceId, productId);

        setSaving(false);

        if (result.outcome === 'ok') {
            onSaved(null);

            return;
        }

        setProblem(t('workspace.ratings.reply.failed'));
    }

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <Label htmlFor={fieldId}>{t('workspace.ratings.reply.label')}</Label>
            <p className="text-meta text-fg-secondary">{t('workspace.ratings.reply.help')}</p>
            <Textarea
                id={fieldId}
                rows={3}
                value={draft}
                invalid={tooLong}
                onChange={(event) => {
                    setDraft(event.target.value);
                    setProblem(null);
                }}
            />
            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                <Button
                    size="sm"
                    loading={saving}
                    loadingText={t('workspace.ratings.reply.saving')}
                    onClick={() => {
                        void publish();
                    }}
                >
                    {body === null
                        ? t('workspace.ratings.reply.publish')
                        : t('workspace.ratings.reply.update')}
                </Button>
                {/*
                    GERİ ALMA DÜĞMESİ YALNIZ YAYINDA BİR CÜMLE VARSA ÇİZİLİR.
                    Yoksa basıldığında hiçbir şey yapmayan bir düğme olurdu ve
                    sahip onu "puanı kaldır" sanabilirdi.
                */}
                {body !== null ? (
                    <Button
                        size="sm"
                        color="alternative"
                        disabled={saving}
                        onClick={() => {
                            void withdraw();
                        }}
                    >
                        {t('workspace.ratings.reply.withdraw')}
                    </Button>
                ) : null}
            </div>
            {body === null ? (
                <p className="text-meta text-fg-muted">{t('workspace.ratings.reply.none')}</p>
            ) : (
                <p className="text-meta text-fg-muted">
                    {publishedMoment === null
                        ? t('workspace.ratings.reply.who')
                        : `${t('workspace.ratings.reply.who')} · ${t('workspace.ratings.reply.published', { time: publishedMoment })}`}
                </p>
            )}
            {problem !== null ? (
                <p role="alert" className="text-meta text-fg-danger">
                    {problem}
                </p>
            ) : null}
        </div>
    );
}

export default RatingReplyEditor;
