import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MediaQuotaRegion } from './MediaQuotaRegion';

/**
 * `docs/49` Faz 7 madde 1-2 (`docs/98` FF-71): kota sayaçları okunur, dolunca
 * sebep görünür, uç yoksa kutu sessizce çekilir.
 */
describe('MediaQuotaRegion (FAZ7-QUOTA-UI-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('plan adı, üç sayaç ve çöp süresi okunur; dolu değilse uyarı yok', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                status: 200,
                json: async () => ({
                    quota: {
                        planCode: 'starter',
                        planLabel: 'Starter',
                        originalBytesUsed: 15 * 1048576,
                        originalBytesLimit: 200 * 1048576,
                        assetsUsed: 3,
                        assetsLimit: 100,
                        monthlyUploadsUsed: 4,
                        monthlyUploadsLimit: 100,
                        trashRetentionDays: 7,
                        blockedReason: null,
                    },
                }),
            })),
        );

        render(<MediaQuotaRegion workspaceId={4} />);

        expect(await screen.findByText('Plan: Starter')).toBeInTheDocument();
        expect(screen.getByText('15 MB of 200 MB')).toBeInTheDocument();
        expect(screen.getByText('3 of 100')).toBeInTheDocument();
        expect(screen.getByText('4 of 100')).toBeInTheDocument();
        expect(screen.getByText(/stay in trash for 7 days/)).toBeInTheDocument();
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('dolunca sunucunun sebebi olduğu gibi okunur; sınırsız aylık "no limit" yazar', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                status: 200,
                json: async () => ({
                    quota: {
                        planCode: 'team',
                        planLabel: 'Team',
                        originalBytesUsed: 1,
                        originalBytesLimit: 2,
                        assetsUsed: 10000,
                        assetsLimit: 10000,
                        monthlyUploadsUsed: 5,
                        monthlyUploadsLimit: null,
                        trashRetentionDays: 90,
                        blockedReason: 'Görsel sayısı sınırına ulaşıldı (10000).',
                    },
                }),
            })),
        );

        render(<MediaQuotaRegion workspaceId={4} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Görsel sayısı sınırına ulaşıldı',
        );
        expect(screen.getByText('5 of no limit')).toBeInTheDocument();
    });

    it('uç okunamazsa hiçbir şey çizmez — yükleme akışını kapatmaz', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({ ok: false, status: 500 })),
        );

        const { container } = render(<MediaQuotaRegion workspaceId={4} />);

        await Promise.resolve();
        expect(container).toBeEmptyDOMElement();
    });
});
