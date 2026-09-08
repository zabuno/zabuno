import type { Meta, StoryObj } from '@storybook/react-vite';

import { ProviderCredentialsPage } from './ProviderCredentialsPage';
import { ThemeRoot } from '../../theme/ThemeRoot';

/**
 * Sağlayıcı kasası paneli, GERÇEK bir düzen motorunda (`docs/117`, FF-220).
 *
 * Bu ekranın diğer bütün testleri jsdom'da koşuyor ve jsdom düzen HESAPLAMAZ:
 * orada hiçbir kutunun boyu yoktur, hiçbir şey taşmaz, hiçbir dokunma hedefi
 * ölçülemez. FF-220 bu panele YENİ bir alan tipi ekliyor — kapalı uçlu bir
 * açılır liste — ve "320 pikselde ne oluyor" sorusu onun için de sorulmalı.
 * Ölçüm yapılmadıysa sonuç "geçti" değil "bilinmiyor"dur.
 *
 * ═══ NEDEN `fetch` BURADA TAKLİT EDİLİYOR ═══
 *
 * Bileşen veriyi kendisi çeker; hikâyenin ona geçirebileceği bir prop yok.
 * Ayrı bir "sunumsal" ikiz yazmak, ölçtüğünü sandığı şeyden sessizce
 * ayrışacak bir düzenek kurmak olurdu (`docs/117` §4.2) — ölçülen ekran,
 * sahibin gördüğü ekranın TA KENDİSİ olmalı. Bu yüzden yalnız ağ katmanı
 * susturuluyor, bileşen değil.
 *
 * Buradaki kimlikler uydurma ve tanınabilir biçimde sahtedir: sahibin
 * gerçek konteyner kimliği depoya girmez (`docs/126` §1).
 */
const PAYLOAD = {
    providers: [
        {
            provider: 'google_tag_manager',
            fields: [
                {
                    name: 'container_id',
                    secret: false,
                    required: true,
                    default: null,
                    choices: null,
                },
                {
                    name: 'ga4',
                    secret: false,
                    required: false,
                    default: 'off',
                    choices: ['off', 'on'],
                },
                {
                    name: 'yandex_metrica',
                    secret: false,
                    required: false,
                    default: 'off',
                    choices: ['off', 'on'],
                },
                {
                    name: 'hotjar',
                    secret: false,
                    required: false,
                    default: 'off',
                    choices: ['off', 'on'],
                },
            ],
        },
        {
            provider: 'mailgun',
            fields: [
                { name: 'domain', secret: false, required: true, default: null, choices: null },
                { name: 'secret', secret: true, required: true, default: null, choices: null },
                {
                    name: 'endpoint',
                    secret: false,
                    required: false,
                    default: 'api.mailgun.net',
                    choices: null,
                },
            ],
        },
    ],
    connections: [
        {
            id: 1,
            provider: 'google_tag_manager',
            label: 'Measurement',
            scope: 'platform_owned',
            workspaceId: null,
            configured: true,
            state: 'active',
            health: 'unknown',
            lastRotatedAt: null,
            lastHealthCheckAt: null,
            fields: [
                { name: 'container_id', secret: false, isSet: true, preview: 'GTM-STORYFAKE' },
                { name: 'ga4', secret: false, isSet: true, preview: 'on' },
                { name: 'yandex_metrica', secret: false, isSet: true, preview: 'off' },
                { name: 'hotjar', secret: false, isSet: false, preview: null },
            ],
        },
        {
            id: 2,
            provider: 'mailgun',
            label: 'Varsayılan',
            scope: 'platform_owned',
            workspaceId: null,
            configured: true,
            state: 'active',
            health: 'healthy',
            lastRotatedAt: null,
            lastHealthCheckAt: null,
            fields: [
                { name: 'domain', secret: false, isSet: true, preview: 'mail.example.test' },
                { name: 'secret', secret: true, isSet: true, preview: '••••b1c0' },
                { name: 'endpoint', secret: false, isSet: true, preview: 'api.mailgun.net' },
            ],
        },
    ],
};

const meta: Meta<typeof ProviderCredentialsPage> = {
    title: 'Surface/Platform/ProviderCredentials',
    component: ProviderCredentialsPage,
    parameters: { layout: 'fullscreen' },
    decorators: [
        (Story) => {
            /*
                Çizimden ÖNCE kurulur: bileşen veriyi ilk etkisinde ister ve
                o an gerçek bir ağ çağrısı çıkarsa hikâye ölçülemez hâle
                gelir (dosya adresinde `fetch` başarısız olur).
            */
            globalThis.fetch = (async () =>
                ({
                    ok: true,
                    status: 200,
                    json: async () => PAYLOAD,
                }) as Response) as typeof fetch;

            return (
                <ThemeRoot>
                    <div className="min-h-dvh bg-canvas p-[var(--space-4)]">
                        <Story />
                    </div>
                </ThemeRoot>
            );
        },
    ],
};

export default meta;

type Story = StoryObj<typeof ProviderCredentialsPage>;

/**
 * Kasa dolu: kapalı uçlu ölçüm hedefleri ile sırlı bir sağlayıcı yan yana.
 * En dar ekranda ölçülmesi gereken hâl budur.
 */
export const Measured: Story = {};
