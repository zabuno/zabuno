import type { Meta, StoryObj } from '@storybook/react-vite';

import { MediaSettingsRegion } from './MediaSettingsRegion';
import { PanelCard } from '../shared/PanelCard';

/**
 * MEDYA > AYARLAR — ölçülebilmesi için hikâye.
 *
 * Bu bölüm bugüne kadar yalnız oturum açmış bir panelde çiziliyordu, yani
 * `scripts/mobile-ux-audit` onu HİÇ görmüyordu: 320 pikseldeki hâli
 * "geçti" değil BİLİNMİYORDU. Sahibin bulduğu kusur (anahtar biçiminde
 * çizilmiş ama çevrilemeyen dört önlem) tam da ölçülmeyen bir yerde
 * yaşadı.
 *
 * Kap gerçeğin aynısıdır: `MediaPage` bu bölümü bir `PanelCard` içinde
 * çizer. Daha dar ya da daha gevşek bir sarmalayıcı seçmek, ölçümü
 * kolaylaştırıp ekranı olduğundan iyi göstermek olurdu.
 */
const BODY = {
    patterns: [
        { key: 'directory', value: 'workspaceFolder', changeable: false },
        { key: 'fileName', value: 'opaqueKey', changeable: false },
        { key: 'date', value: 'deviceLocale', changeable: false },
    ],
    security: [
        { key: 'virusScan', state: 'on', switchable: false },
        { key: 'contentSignature', state: 'on', switchable: false },
        { key: 'metadataStrip', state: 'partial', switchable: false },
        { key: 'signedLink', state: 'on', switchable: false },
        { key: 'watermark', state: 'missing', switchable: false },
    ],
};

/*
    Bölüm veriyi UÇTAN okur ve cevap gelmezse hiç çizilmez. Hikâye sabit bir
    cevap verir: uydurma değil, `docs/108` §6.5-§6.6'nın kendi listesi —
    testin kullandığı gövdenin aynısı.
*/
function stubFetch() {
    window.fetch = (async () =>
        ({
            ok: true,
            status: 200,
            json: async () => BODY,
        }) as unknown as Response) as typeof window.fetch;
}

const meta: Meta<typeof MediaSettingsRegion> = {
    title: 'Surface/Workspace/MediaSettingsRegion',
    component: MediaSettingsRegion,
    decorators: [
        (Story) => {
            stubFetch();

            return (
                <div className="bg-canvas">
                    <PanelCard>
                        <Story />
                    </PanelCard>
                </div>
            );
        },
    ],
};

export default meta;

export const Default: StoryObj<typeof MediaSettingsRegion> = {
    args: { workspaceId: 7 },
};

/**
 * Tarayıcı bu ortamda bağlı değilken: hâl "kapalı" değil "çalışmıyor" der ve
 * altındaki "kapatılamaz" satırı susar — zaten kapalı olan bir şeyin
 * kapatılamadığını söylemek çelişkidir.
 */
export const ScannerNotRunning: StoryObj<typeof MediaSettingsRegion> = {
    args: { workspaceId: 7 },
    decorators: [
        (Story) => {
            window.fetch = (async () =>
                ({
                    ok: true,
                    status: 200,
                    json: async () => ({
                        ...BODY,
                        security: [
                            { key: 'virusScan', state: 'unavailable', switchable: false },
                            ...BODY.security.slice(1),
                        ],
                    }),
                }) as unknown as Response) as typeof window.fetch;

            return <Story />;
        },
    ],
};
