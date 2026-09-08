import type { Meta, StoryObj } from '@storybook/react-vite';

import { DataRightsRegion } from './DataRightsRegion';
import {
    stubFetch as stubFetchLifecycle,
    withFetchLifecycle,
} from '../../../../storybook/fetchLifecycle';

/**
 * Veri hakları bölümü (FF-226, `docs/138`).
 *
 * Hikâyeler 320 pikselde ÖLÇÜLMEK için var (`scripts/mobile-ux-audit`):
 * silme kutusundaki metin alanı kenardan kırpılıyor mu, "vazgeç" düğmesi
 * parmağın altında mı, saklananlar listesi taşıyor mu.
 *
 * Ölçülecek en yoğun hâl `ScheduledErasure` DEĞİL, `EverythingAtOnce`:
 * hazır bir arşiv, planlanmış bir silme ve uzun sebepli saklama listesi
 * aynı ekranda.
 */
const WORKSPACE_ID = 5;

const BASE = {
    workspaceName: 'Zeytin Restoranları',
    graceDays: 30,
    exportedSections: ['workspaces', 'brands', 'locations', 'menus', 'products', 'invoices'],
    retained: {
        invoices:
            'Kesilmiş fatura yasal bir belgedir; ayrıca numara serisi boşluksuzdur ve bir satırın silinmesi seride kanıtlanabilir bir boşluk açardı.',
        ledger_entries: 'Defter kaydı; faturanın muhasebe karşılığıdır ve tek başına silinemez.',
        consent_records: 'Onayın kanıtı; en çok hesabın artık olmadığı gün gerekir.',
    },
    outOfScope: { platform_audits: 'Platformun kendi denetim kaydı.' },
    assetsUnderLegalHold: 0,
    hosting: { provider: 'netcup GmbH', country: 'Germany' },
    requests: [] as unknown[],
};

const READY_EXPORT = {
    id: 3,
    kind: 'export',
    state: 'ready',
    requestedBy: 'sahip@zeytin.example',
    requestedAt: '2026-09-08 09:00:00',
    sectionCount: 6,
    bytes: 40960,
    availableUntil: '2026-09-15 09:00:00',
    scheduledFor: null,
    completedAt: '2026-09-08 09:01:00',
    cancelledAt: null,
    failed: false,
    deletedRowTotal: 0,
    notifiedAt: '2026-09-08 09:01:05',
    notificationFailed: false,
    downloadUrl: '#archive',
};

const SCHEDULED_ERASURE = {
    id: 4,
    kind: 'erasure',
    state: 'scheduled',
    requestedBy: 'sahip@zeytin.example',
    requestedAt: '2026-09-08 09:10:00',
    sectionCount: 44,
    bytes: null,
    availableUntil: null,
    scheduledFor: '2026-10-08 09:10:00',
    completedAt: null,
    cancelledAt: null,
    failed: false,
    deletedRowTotal: 0,
    notifiedAt: null,
    notificationFailed: false,
    downloadUrl: null,
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function stub(body: unknown) {
    stubFetchLifecycle(async (url) => {
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/data-rights`) {
            return jsonResponse(200, body);
        }

        return jsonResponse(404, { message: 'Not Found.' });
    });
}

const meta: Meta<typeof DataRightsRegion> = {
    title: 'Macro/Workspace/DataRightsRegion',
    component: DataRightsRegion,
    decorators: [withFetchLifecycle],
    parameters: {
        docs: {
            description: {
                component:
                    'Settings → Workspace danger zone: take a copy of the data, or ask for it to be erased.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof DataRightsRegion>;

export const Default: Story = {
    loaders: [
        async () => {
            stub(BASE);

            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID },
};

export const ArchiveReady: Story = {
    loaders: [
        async () => {
            stub({ ...BASE, requests: [READY_EXPORT] });

            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID },
};

export const ScheduledErasure: Story = {
    loaders: [
        async () => {
            stub({ ...BASE, requests: [SCHEDULED_ERASURE] });

            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID },
};

export const BlockedByLegalHold: Story = {
    loaders: [
        async () => {
            stub({ ...BASE, assetsUnderLegalHold: 2 });

            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID },
};

export const EverythingAtOnce: Story = {
    loaders: [
        async () => {
            stub({ ...BASE, requests: [READY_EXPORT, SCHEDULED_ERASURE] });

            return {};
        },
    ],
    args: { workspaceId: WORKSPACE_ID },
};
