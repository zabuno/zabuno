import type { Meta, StoryObj } from '@storybook/react-vite';
import { ThemeRoot } from '../../theme/ThemeRoot';
import { SupportPage } from './SupportPage';
import type { SupportApi, SupportRequestRow } from './support/supportApi';

/**
 * Destek ekranı (FF-201). Hikâyeler 320 pikselde ÖLÇÜLMEK için var
 * (`scripts/mobile-ux-audit`): formun düğmesi parmağın altında mı, liste
 * satırı kırılıyor mu, yardım bağlantısı 44 piksel mi.
 *
 * Sunucu yerine sahte bir `api` geçirilir: hikâye ağ konuşmaz, ama ekranın
 * ürünle AYNI yolu (yükleniyor → liste, gönder → yeniden oku) yürüdüğünü
 * gösterir.
 */
const rows: SupportRequestRow[] = [
    {
        id: 3,
        reference: 'ZB-7KM4P',
        subject: 'Menüm görünmüyor',
        status: 'received',
        received_at: '2026-09-06T09:12:00+03:00',
        first_response_at: null,
    },
    {
        id: 2,
        reference: 'ZB-3F7K2',
        subject: 'Karekod kartlarını nasıl bastırırım?',
        status: 'answered',
        received_at: '2026-09-04T14:30:00+03:00',
        first_response_at: '2026-09-05T10:05:00+03:00',
    },
    {
        id: 1,
        reference: 'ZB-Q2W9X',
        subject: 'Fatura adresimi değiştirmek istiyorum',
        status: 'closed',
        received_at: '2026-08-28T08:00:00+03:00',
        first_response_at: '2026-08-28T15:40:00+03:00',
    },
];

function fakeApi(
    requests: SupportRequestRow[],
    commitment: { hours: number; sentence: string } | null,
    mode: 'ok' | 'fail' = 'ok',
): SupportApi {
    let state = [...requests];

    return {
        async list() {
            if (mode === 'fail') {
                throw new Error('down');
            }

            return { requests: state, commitment };
        },
        async create(subject) {
            const created: SupportRequestRow = {
                id: state.length + 100,
                reference: 'ZB-NEW01',
                subject,
                status: 'received',
                received_at: new Date().toISOString(),
                first_response_at: null,
            };
            state = [created, ...state];

            return { ...created, acknowledgement: 'sent' };
        },
    };
}

const meta: Meta<typeof SupportPage> = {
    title: 'Surface/Workspace/SupportPage',
    component: SupportPage,
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-fluid-lg)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: {
        workspaceId: 7,
        email: 'mehmet@zeytinkebap.com',
    },
};

export default meta;
type Story = StoryObj<typeof SupportPage>;

/** Talepleri olan sahip; taahhüt YAPILANDIRILMAMIŞ — hiçbir cümle yok. */
export const WithRequests: Story = {
    args: { api: fakeApi(rows, null) },
};

/**
 * Taahhüt yapılandırılmış: cümle sunucudan gelir ve olduğu gibi yazılır.
 * Sayı bir örnek değeridir; ürün onu yalnız sahibin `.env`'inden okur.
 */
export const WithCommitment: Story = {
    args: { api: fakeApi(rows, { hours: 24, sentence: 'We reply within 24 hours.' }) },
};

/** Hiç talep yok: boş durum çıkış yolunu (form yukarıda) zaten gösterir. */
export const Empty: Story = {
    args: { api: fakeApi([], null) },
};

/** Liste yüklenemedi: form yine çalışır — arıza listeyi kapatır, kanalı değil. */
export const ListUnavailable: Story = {
    args: { api: fakeApi(rows, null, 'fail') },
};
