import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { SupportQueue, type SupportQueueRow } from './SupportQueue';

/**
 * DESTEK KUYRUĞU — `docs/125` §6'nın kapanışı.
 *
 * O belge şunu yazıyordu: *"Uçlar var, ekran yok… Ekran `docs/122` Y7 ile
 * birlikte gelir."* Bu dosya o cümleyi kapatır ve ürünün BUGÜNKÜ gerçeğini
 * de dondurur.
 *
 * CEVAP YÜZEYİ ARTIK VAR (SUPPORT-REPLY-01). Süperadmin kuyruk satırından
 * çıkmadan düz metin cevap yollar; "bu üründe cevap yazma yüzeyi yok"
 * cümlesi bugünden itibaren YANLIŞTIR ve ekranda durması, olmayan bir
 * eksikliği ilan etmek olurdu. Manuel "Mark answered" AYRI kalır: o hâlâ
 * yalnız zamanı kaydeder, hiçbir şey göndermez.
 */
const rows: SupportQueueRow[] = [
    {
        id: 1,
        reference: 'ZB-3F7K2',
        workspace_id: 42,
        name: 'Hüseyin',
        email: 'huseyin@example.com',
        subject: 'Menüm görünmüyor',
        message: 'Karekodu okutunca boş sayfa açılıyor.',
        channel: 'panel',
        status: 'received',
        received_at: '2026-09-06 09:14:02',
        first_response_at: null,
        acknowledgement: 'sent',
    },
    {
        id: 2,
        reference: 'ZB-9QW4M',
        workspace_id: null,
        name: 'Ayşe',
        email: 'ayse@example.com',
        subject: 'Fiyatlarınız nedir',
        message: 'Üç şubem var.',
        channel: 'public_contact',
        status: 'received',
        received_at: '2026-09-06 11:02:40',
        first_response_at: null,
        acknowledgement: 'not_attempted',
    },
];

type QueueProps = React.ComponentProps<typeof SupportQueue>;

function renderQueue(overrides: Partial<QueueProps> = {}) {
    const onStatusFilter = vi.fn();
    const onChangeStatus = vi.fn();
    const onOpenWorkspace = vi.fn();
    const onReply = vi.fn();

    const props: QueueProps = {
        rows,
        status: '',
        busy: false,
        onStatusFilter,
        onChangeStatus,
        onOpenWorkspace,
        onReply,
        replyError: null,
        replyToConfigured: true,
        ...overrides,
    };

    const view = render(<SupportQueue {...props} />);

    /** Aynı satır, yeni bir sunucu durumuyla yeniden çizilir. */
    const rerenderQueue = (next: Partial<QueueProps>) => {
        view.rerender(<SupportQueue {...props} {...next} />);
    };

    return { onStatusFilter, onChangeStatus, onOpenWorkspace, onReply, rerenderQueue };
}

describe('SupportQueue', () => {
    it('shows who wrote, what they wrote, and how to reach them', () => {
        renderQueue();

        const region = screen.getByRole('region', { name: 'Support queue' });

        expect(within(region).getByText(/ZB-3F7K2 · Menüm görünmüyor/)).toBeInTheDocument();
        expect(within(region).getByText(/huseyin@example.com/)).toBeInTheDocument();
        expect(
            within(region).getByText('Karekodu okutunca boş sayfa açılıyor.'),
        ).toBeInTheDocument();
    });

    it('warns when the sender never got an acknowledgement', () => {
        renderQueue();

        // İki satır var, uyarı YALNIZ alındı e-postası çıkmayanda: sahibin
        // "yazdım ama cevap gelmedi" demesinin sebebi çoğu zaman budur.
        const warnings = screen.getAllByText(/No acknowledgement email reached this sender/);
        expect(warnings).toHaveLength(1);
    });

    it('changes a status through the caller, and hides the button for the status already held', async () => {
        const user = userEvent.setup();
        const { onChangeStatus } = renderQueue({
            rows: [{ ...rows[0], status: 'answered' }],
        });

        expect(screen.queryByRole('button', { name: 'Mark answered' })).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Close' }));
        expect(onChangeStatus).toHaveBeenCalledWith(1, 'closed');
    });

    it('filters through the server, not in the browser', async () => {
        const user = userEvent.setup();
        const { onStatusFilter } = renderQueue();

        await user.selectOptions(screen.getByLabelText('Status'), 'answered');
        expect(onStatusFilter).toHaveBeenCalledWith('answered');
    });

    /**
     * TEK TIK, YALNIZ AÇILACAK BİR HESAP VARSA.
     *
     * Kuyruktaki iki satır aynı görünür ama biri bir restoran hesabına
     * bağlıdır, diğeri herkese açık iletişim formundan gelmiştir ve hiçbir
     * hesaba bağlı DEĞİLDİR. İkisine de aynı düğmeyi çizmek, destek
     * görevlisine var olmayan bir masayı vaat ederdi: tıklar, bekler ve
     * elinde bir hata cümlesi kalırdı.
     */
    it('offers one accessible desk action for a tenant row and none for a public contact row', async () => {
        const user = userEvent.setup();
        const { onOpenWorkspace } = renderQueue();

        const actions = screen.getAllByRole('button', { name: /Open the support desk/ });
        expect(actions).toHaveLength(1);

        await user.click(actions[0]);
        expect(onOpenWorkspace).toHaveBeenCalledWith(42);
    });

    it('says nothing is waiting instead of drawing an empty box', () => {
        renderQueue({ rows: [] });

        expect(screen.getByText('Nothing is waiting.')).toBeInTheDocument();
    });

    /**
     * SUPPORT-REPLY-UI-ONE-CLICK-01.
     *
     * SAHİBİN YOLCULUĞU: destek görevlisi bugün kuyruğu okuyor, adresi elle
     * kopyalıyor, posta programını açıyor, cevabı orada yazıyor ve geri
     * dönüp "Mark answered"a basıyor. Dört pencere, iki uygulama, tek bir
     * cevap. Bu senaryo o yolculuğu SATIRIN İÇİNE indiriyor: yaz, bir kez
     * bas, bitti.
     *
     * ÇİFT TIKLAMA İKİ E-POSTA DEMEKTİR. Sunucuda yinelenen gönderimi eleyen
     * bir anahtar YOK (bu paket exactly-once vaat etmiyor), bu yüzden tek
     * savunma ekrandadır: gönderim sürerken düğme basılamaz. Ekran bunu
     * yapmazsa Hüseyin aynı cevabı iki kez alır.
     */
    it('sends a reply from inside the row with one click, and refuses the second click while busy', async () => {
        const user = userEvent.setup();
        const { onReply, rerenderQueue } = renderQueue();

        // ARTIK YÜZEY VAR: ekran kendi eksikliğini ilan etmeyi bıraktı.
        expect(screen.queryByText(/no reply surface yet/i)).toBeNull();

        /*
            HER SATIRIN KENDİ KUTUSU VE KENDİ ETİKETİ. İki satır aynı
            etiketi taşısaydı, ekran okuyucuyla çalışan görevli hangi
            talebe yazdığını yalnız sırayı sayarak bilebilirdi.
        */
        const box = screen.getByRole('textbox', {
            name: /repl(y|ies).*ZB-3F7K2|ZB-3F7K2.*repl(y|ies)/i,
        });
        const others = screen.getAllByRole('textbox', { name: /repl/i });
        expect(others).toHaveLength(rows.length);

        await user.type(box, 'Menüyü yayınlayın.');

        const send = screen.getByRole('button', {
            name: /send reply.*ZB-3F7K2|ZB-3F7K2.*send reply/i,
        });
        await user.click(send);

        expect(onReply).toHaveBeenCalledTimes(1);
        expect(onReply).toHaveBeenCalledWith(1, 'Menüyü yayınlayın.');

        // Gönderim sürerken ikinci tıklama hiçbir şey yollamaz.
        rerenderQueue({ busy: true });
        await user.click(
            screen.getByRole('button', { name: /send reply.*ZB-3F7K2|ZB-3F7K2.*send reply/i }),
        );
        expect(onReply).toHaveBeenCalledTimes(1);

        /*
            MANUEL DAMGA AYRI DURUR ve dürüstlüğünü koruyor: durum
            değiştirmek bir kayıt fiilidir, bir cevap değil.
        */
        expect(
            screen.getByText(/marking a request answered records the timing, it does not send/i),
        ).toBeInTheDocument();
    });

    /**
     * SUPPORT-REPLY-UI-FAILURE-01.
     *
     * TAŞIYICI DÜŞTÜĞÜNDE YAZILAN CÜMLE KAYBOLMAZ. Görevli üç paragraf
     * yazdı, gönderdi, sunucu "çıkaramadım" dedi. Kutu temizlenirse o üç
     * paragraf gitmiştir ve görevli baştan yazar — ya da yazmaz. Taslak
     * durur, hata satırın kendisinde duyurulur ve düğme yeniden basılabilir.
     *
     * CEVAP ADRESİ YOKSA BU DA SÖYLENİR: `SUPPORT_EMAIL` boşken müşteri
     * "cevapla"ya bastığında yazdığı yer kimsenin okumadığı bir kutudur.
     * Bunu görevliye söylememek, sessiz bir kayıp üretirdi.
     */
    it('keeps the draft, announces the failure on the row, and admits when there is no reply-to address', async () => {
        const user = userEvent.setup();
        const { rerenderQueue } = renderQueue({ replyToConfigured: false });

        const box = screen.getByRole('textbox', {
            name: /repl(y|ies).*ZB-3F7K2|ZB-3F7K2.*repl(y|ies)/i,
        });
        await user.type(box, 'Menüyü yayınlayın.');

        rerenderQueue({
            replyToConfigured: false,
            replyError: { id: 1, message: 'The reply could not be sent.' },
        });

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('The reply could not be sent.');

        // TASLAK DURUR — yeniden yazdırmıyoruz.
        expect(
            screen.getByRole('textbox', { name: /repl(y|ies).*ZB-3F7K2|ZB-3F7K2.*repl(y|ies)/i }),
        ).toHaveValue('Menüyü yayınlayın.');

        // Ve yeniden denenebilir.
        expect(
            screen.getByRole('button', { name: /send reply.*ZB-3F7K2|ZB-3F7K2.*send reply/i }),
        ).toBeEnabled();

        // Hata YALNIZ o satırda; ikinci satır sağlam görünür.
        expect(screen.getAllByRole('alert')).toHaveLength(1);

        expect(
            screen.getByText(/no reply-to address is configured|cannot reply to this email/i),
        ).toBeInTheDocument();
    });
});
