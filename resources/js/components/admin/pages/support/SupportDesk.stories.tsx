import type { Meta, StoryObj } from '@storybook/react-vite';

import { SupportDesk } from './SupportDesk';
import { ThemeRoot } from '../../../theme/ThemeRoot';
import type { TenantSupportView } from './types';

/**
 * Destek masası, GERÇEK bir düzen motorunda (`docs/117`, `docs/133`).
 *
 * Bu ekranın diğer testleri jsdom'da koşuyor ve jsdom düzen HESAPLAMAZ:
 * orada hiçbir kutunun boyu yoktur, hiçbir şey taşmaz, hiçbir dokunma hedefi
 * ölçülemez. Hikâye, `scripts/mobile-ux-audit` "320 pikselde ne oluyor"
 * sorusunu sorabilsin diye var — ölçüm yapılmadıysa sonuç "geçti" değil
 * "bilinmiyor"dur.
 *
 * MİSAFİR ADRESİ EN UZUN DİZEDİR ve bu bilerek: 43 karakterlik bir jeton
 * taşıyan bağlantı, kırılmazsa 320 pikselde sayfayı yana kaydırır. Hikâye o
 * adresi gerçek uzunluğunda taşır, kısaltılmış bir örnekle değil.
 */
const view: TenantSupportView = {
    workspace: { id: 42, name: 'Kadıköy Kebap', slug: 'kadikoy-kebap', state: 'active' },
    subscription: { state: 'active', plan_name: 'Restaurant', plan_code: 'restaurant' },
    locations: [
        {
            id: 7,
            displayName: 'Kadıköy — Moda Caddesi',
            acceptsOrders: true,
            menuCount: 3,
            qrCodes: [
                {
                    id: 11,
                    guestUrl: 'https://zabuno.com/q/8Kq2mVx4Nb7RtY9pLc3JdW6sZaH1fG5uEo0iPnQrXvT',
                    state: 'active',
                    destinationType: 'menu',
                    menuId: 21,
                    menuName: 'Ana Menü',
                    publishedVersion: 12,
                    publishedAt: '2026-09-05 18:20:11',
                },
                {
                    id: 12,
                    guestUrl: 'https://zabuno.com/q/3Wd7nRb2Fk9YtP5xQz1LcJm8sVhA4gU6oE0iTvXrNqB',
                    state: 'active',
                    destinationType: 'menu',
                    menuId: 22,
                    menuName: 'Kahvaltı',
                    publishedVersion: null,
                    publishedAt: null,
                },
            ],
        },
    ],
    supportRequests: [
        {
            reference: 'ZB-3F7K2',
            subject: 'Menüm görünmüyor',
            status: 'received',
            receivedAt: '2026-09-06 09:14:02',
            firstResponseAt: null,
        },
    ],
    accessHistory: [
        {
            id: 3,
            workspaceId: 42,
            actor: 'destek@zabuno.com',
            reason: 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).',
            startedAt: '2026-09-06 09:31:00',
            expiresAt: '2026-09-06 09:46:00',
            endedAt: '2026-09-06 09:38:00',
            active: false,
        },
    ],
    findings: [{ code: 'qr_menu_never_published', locationId: 7 }],
};

const meta: Meta<typeof SupportDesk> = {
    title: 'Surface/Platform/SupportDesk',
    component: SupportDesk,
    parameters: { layout: 'fullscreen' },
    args: {
        view,
        session: null,
        windowMinutes: 15,
        reasonMinLength: 12,
        busy: false,
        notice: null,
        onOpen: () => undefined,
        onEnd: () => undefined,
    },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-3)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
};

export default meta;

type Story = StoryObj<typeof SupportDesk>;

/** Çoğu destek çağrısının bittiği yer: bulgu okundu, oturum hiç açılmadı. */
export const NoSessionNeeded: Story = {};

/** Hiçbir ölçülebilir arıza yok — ekran boş değil, dürüst. */
export const NothingIsBroken: Story = {
    args: { view: { ...view, findings: [] } },
};

/** Oturum AÇIK: en üstte, uyarı tonunda, bitirme düğmesiyle. */
export const SessionOpenHere: Story = {
    args: {
        session: {
            id: 9,
            workspaceId: 42,
            actor: 'destek@zabuno.com',
            reason: 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).',
            startedAt: '2026-09-07 10:00:00',
            expiresAt: '2026-09-07 10:15:00',
            endedAt: null,
            active: true,
        },
    },
};

/** Sunucu bir yazmayı reddetti; ekran kendi tahminini değil onun cümlesini yazar. */
export const WriteRefused: Story = {
    args: {
        notice: 'This support access session is read-only.',
        session: {
            id: 9,
            workspaceId: 42,
            actor: 'destek@zabuno.com',
            reason: 'Sahip aradı: karekod boş sayfa açıyor (ZB-3F7K2).',
            startedAt: '2026-09-07 10:00:00',
            expiresAt: '2026-09-07 10:15:00',
            endedAt: null,
            active: true,
        },
    },
};

/** Kurulumu yarım kalmış kiracı: şube yok, karekod yok, cevap yine var. */
export const NothingSetUpYet: Story = {
    args: {
        view: {
            ...view,
            locations: [],
            supportRequests: [],
            accessHistory: [],
            findings: [{ code: 'no_location', locationId: null }],
        },
    },
};
