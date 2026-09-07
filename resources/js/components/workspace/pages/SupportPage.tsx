import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActionLink } from '../../catalog/navigation/micro/ActionLink';
import { t } from '../../../i18n/workspace';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { SupportRequestForm, type SupportSubmitOutcome } from './support/SupportRequestForm';
import { SupportRequestList, type SupportRequestListStatus } from './support/SupportRequestList';
import {
    createSupportApi,
    type SupportApi,
    type SupportCommitment,
    type SupportRequestRow,
} from './support/supportApi';

export type SupportPageProps = {
    workspaceId: number;
    /** Cevabın gideceği adres — hesaptan (`WorkspaceSectionRuntimeContext.email`). */
    email: string;
    /**
     * Sunucuyla konuşan arayüz. Ürün geçirmez (varsayılan `fetch`
     * tabanlıdır); hikâye ve test sahtesini geçirir.
     */
    api?: SupportApi;
};

/**
 * Destek — FF-201 (`docs/125`, `docs/107` Faz 1.6).
 *
 * "Tıkanırsam kime sorarım?" sorusunun PANEL cevabı. Sahip adını ve
 * e-postasını yeniden yazmaz; talebi bu çalışma alanına bağlanır; referansı
 * ekranda ve e-postada durur; durumu burada görünür.
 *
 * SIRA DAR EKRANA GÖRE: önce yardım bağlantısı ve (varsa) taahhüt — tek
 * satır; sonra FORM, çünkü bu ekrana gelen çoğu kişi bir şey soracak;
 * en altta liste. Liste önce gelseydi, telefonda formu bulmak için beş
 * eski talebin altına kaydırmak gerekirdi.
 *
 * YANIT TAAHHÜDÜ SUNUCUDAN: `commitment` `null` ise hiçbir cümle çizilmez.
 * Bu ekranın kendi kataloğunda öyle bir cümle YOKTUR (`docs/125` §3).
 */
/**
 * Sunucudan gelen son cevap, HANGİ istemciyle geldiğiyle birlikte.
 *
 * "Yükleniyor" ayrı bir durum değil, bir TÜRETİMDİR: elimizdeki cevap bu
 * istemciye ait değilse (ilk açılış ya da çalışma alanı değişti) ekran
 * yükleniyordur. Böylece etkinin içinde eşzamanlı `setState` yok — çalışma
 * alanı değişince eski restoranın talepleri bir kare bile görünmez, ve
 * gönderim sonrası yeniden okuma listeyi "yükleniyor"a düşürüp titretmez.
 */
type Loaded = {
    client: SupportApi;
    status: Exclude<SupportRequestListStatus, 'loading'>;
    requests: SupportRequestRow[];
    commitment: SupportCommitment;
};

export function SupportPage({ workspaceId, email, api }: SupportPageProps) {
    const client = useMemo(() => api ?? createSupportApi(workspaceId), [api, workspaceId]);
    const [loaded, setLoaded] = useState<Loaded | null>(null);
    const requestRef = useRef(0);

    const load = useCallback(async () => {
        const requestId = ++requestRef.current;

        try {
            const body = await client.list();

            if (requestRef.current !== requestId) {
                return;
            }

            setLoaded({
                client,
                status: 'success',
                requests: body.requests,
                commitment: body.commitment,
            });
        } catch {
            if (requestRef.current === requestId) {
                setLoaded({ client, status: 'error', requests: [], commitment: null });
            }
        }
    }, [client]);

    useEffect(() => {
        // Ekip sayfasıyla aynı biçim: durum yalnız cevap GELDİKTEN sonra
        // yazılır; etkinin kendi gövdesinde eşzamanlı `setState` yok.
        void (async () => {
            await load();
        })();
    }, [load]);

    const current = loaded !== null && loaded.client === client ? loaded : null;
    const listStatus: SupportRequestListStatus = current === null ? 'loading' : current.status;
    const requests = current?.requests ?? [];
    const commitment = current?.commitment ?? null;

    const submit = useCallback(
        async (subject: string, message: string): Promise<SupportSubmitOutcome> => {
            try {
                const created = await client.create(subject, message);

                // Liste sunucudan YENİDEN okunur: ekranla sunucunun
                // ayrışması, sahibi olmayan bir kayda inandırırdı.
                await load();

                return {
                    kind: 'sent',
                    reference: created.reference,
                    acknowledgement: created.acknowledgement,
                };
            } catch {
                return { kind: 'error' };
            }
        },
        [client, load],
    );

    return (
        <div id="section-support">
            <WorkspacePageFrame
                measure="settings"
                title={t('workspace.support.title')}
                description={t('workspace.support.description')}
                cardChildren
            >
                <div className="flex flex-col gap-3">
                    {commitment !== null ? (
                        <p className="text-body font-medium text-fg">{commitment.sentence}</p>
                    ) : null}
                    <p className="text-body text-fg-secondary">
                        {t('workspace.support.help.lead')}
                    </p>
                    {/*
                        BAĞLANTI, DÜĞME DEĞİL: `/help` bir sayfadır ve
                        oturum istemez. `ActionLink` 44 piksellik hedefi
                        kendi taşır (`--density-hit-area-min`); metin
                        bağlantısı 320 pikselde hedef ölçüsünü tutmuyordu
                        (`scripts/mobile-ux-audit.baseline.json`).
                    */}
                    <ActionLink href="/help" variant="secondary" className="self-start">
                        {t('workspace.support.help.link')}
                    </ActionLink>
                </div>

                <SupportRequestForm email={email} onSubmit={submit} />

                <SupportRequestList status={listStatus} requests={requests} />
            </WorkspacePageFrame>
        </div>
    );
}

export default SupportPage;
