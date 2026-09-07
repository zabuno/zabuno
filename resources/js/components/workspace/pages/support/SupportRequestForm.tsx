import { useId, useState } from 'react';
import { Button } from '../../../catalog/forms/micro/Button';
import { Label } from '../../../catalog/forms/micro/Label';
import { Textarea } from '../../../catalog/forms/micro/Textarea';
import { TextField } from '../../../catalog/forms/compound/TextField';
import { t } from '../../../../i18n/workspace';
import type { SupportAcknowledgement } from './supportApi';

export type SupportSubmitOutcome =
    | { kind: 'sent'; reference: string; acknowledgement: SupportAcknowledgement }
    | { kind: 'error' };

type SupportRequestFormProps = {
    /** Cevabın gideceği adres — hesaptan; form sormaz, söyler. */
    email: string;
    onSubmit: (subject: string, message: string) => Promise<SupportSubmitOutcome>;
};

const SUBJECT_MAX = 160;
const MESSAGE_MAX = 4000;

/**
 * Yeni talep — sunum bileşeni. Fetch bilmez; `onSubmit` sonucu ne derse
 * onu yazar.
 *
 * SONUÇ CÜMLESİ SUNUCUNUN SÖYLEDİĞİDİR: `acknowledgement` `sent` ise
 * "kopyasını gönderdik", değilse "kopya gidemedi ama talep kayıtlı". İkisini
 * tek "gönderildi" altında toplamak, sahibi gelmeyen bir e-postayı
 * beklemeye çağırmak olurdu.
 */
export function SupportRequestForm({ email, onSubmit }: SupportRequestFormProps) {
    const headingId = useId();
    const messageId = useId();
    const messageHelpId = `${messageId}-help`;
    const [subject, setSubject] = useState('');
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

    const canSubmit =
        !submitting &&
        subject.trim() !== '' &&
        subject.length <= SUBJECT_MAX &&
        message.trim() !== '' &&
        message.length <= MESSAGE_MAX;

    async function handleSubmit() {
        if (!canSubmit) {
            return;
        }

        setSubmitting(true);
        setNotice(null);

        const outcome = await onSubmit(subject.trim(), message.trim());

        setSubmitting(false);

        if (outcome.kind === 'error') {
            setNotice({ tone: 'error', text: t('workspace.support.form.error') });

            return;
        }

        setSubject('');
        setMessage('');
        setNotice({
            tone: 'success',
            text:
                outcome.acknowledgement === 'sent'
                    ? t('workspace.support.form.sent', { reference: outcome.reference, email })
                    : t('workspace.support.form.sentNoCopy', { reference: outcome.reference }),
        });
    }

    return (
        <form
            aria-labelledby={headingId}
            className="flex flex-col gap-4"
            /*
                `noValidate` — `docs/47` Kural 5(b), `forms.guard.test.ts`.
                Konu ve mesaj alanları `required` taşır; onlarsız tarayıcı
                kendi baloncuğunu gösterir, `submit` olayı hiç oluşmaz ve
                aşağıdaki işleyici çalışmaz. O baloncuk bizim kataloğumuzdan
                değil tarayıcının dilinden gelir ve odağı biz taşıyamayız.
                Doğrulama `canSubmit` ile bizde: eksik alanda gönder düğmesi
                pasif, sunucu reddederse cümleyi biz yazarız.
            */
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                void handleSubmit();
            }}
        >
            <h2 id={headingId} className="text-body font-bold text-fg">
                {t('workspace.support.form.heading')}
            </h2>

            <TextField
                label={t('workspace.support.form.subject')}
                helpText={t('workspace.support.form.subject.help')}
                name="support-subject"
                value={subject}
                maxLength={SUBJECT_MAX}
                required
                onChange={(event) => {
                    setSubject(event.target.value);
                    setNotice(null);
                }}
            />

            <div className="flex flex-col gap-1">
                <Label htmlFor={messageId} required>
                    {t('workspace.support.form.message')}
                </Label>
                <Textarea
                    id={messageId}
                    name="support-message"
                    rows={6}
                    value={message}
                    maxLength={MESSAGE_MAX}
                    required
                    aria-describedby={messageHelpId}
                    onChange={(event) => {
                        setMessage(event.target.value);
                        setNotice(null);
                    }}
                />
                <p id={messageHelpId} className="text-body text-fg-secondary">
                    {t('workspace.support.form.message.help', { email })}
                </p>
            </div>

            {/*
                TAM GENİŞLİK: 320 pikselde birincil eylem parmağın altında
                durur; kenara yaslı küçük bir düğme, dar ekranda ilk
                kaçırılan hedeftir (`docs/117`).
            */}
            <Button
                type="submit"
                className="w-full"
                disabled={!canSubmit}
                loading={submitting}
                loadingText={t('workspace.support.form.submitting')}
            >
                {t('workspace.support.form.submit')}
            </Button>

            {notice !== null && (
                <p
                    role={notice.tone === 'error' ? 'alert' : 'status'}
                    className={
                        notice.tone === 'error'
                            ? 'text-body font-medium text-fg-danger'
                            : 'text-body font-medium text-fg-success'
                    }
                >
                    {notice.text}
                </p>
            )}
        </form>
    );
}

export default SupportRequestForm;
