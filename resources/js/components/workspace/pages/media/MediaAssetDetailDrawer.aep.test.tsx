import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';
import { MediaRemoteSection, MediaUsageList } from './MediaRemoteSection';
import type { MediaUsage } from '../MediaPage';

/**
 * DOSYA ÇEKMECESİNİN PARÇALARI — kanonik teslim paketi (`DESIGN_SPEC.md` §7
 * "Dosya çekmecesi": durum + insanca sebep, "Nerede kullanılıyor" YAYIN DURUMU
 * HAPIYLA).
 *
 * Restoran sahibinin yolculuğu: bir fotoğrafı silmek üzere ve tek sorusu var —
 * "bu şu an misafirin gördüğü menüde mi?". Cevap bir HAP rozetle verilir:
 * "Yayında" kelimesi metin olarak orada durur ve zemin dolgusu ikinci kanaldır
 * (WCAG 1.4.1 — yalnız renkle konuşan bir işaret, renk körü bir kullanıcıya
 * hiçbir şey söylemez). Düz gri bir kelime bu ayrımı taşıyamıyordu.
 *
 * Hap bir YARIÇAP değil bir BİÇİM kararıdır: külliyat onu kendi jetonuyla
 * yayınlar (`--radius-pill`), `rounded-full` değil.
 *
 * Durum sebebi ("HEIC dönüştürülüyor; 1–2 dakika") ise bir CÜMLEDİR ve gövde
 * ölçeğindedir — sahibin bekleyip beklemeyeceğine karar verdiği tek bilgi
 * dipnot olamaz. Bu ortamda virüs taraması kapalı olabilir; kapalı tarama
 * "hazır" demek değildir, rozet olduğu gibi kalır.
 */
const USAGES: MediaUsage[] = [
    {
        entityType: 'menuItem',
        entityId: 11,
        slot: 'itemImage',
        label: 'Adana kebap',
        published: true,
    },
    { entityType: 'menuItem', entityId: 12, slot: 'itemImage', label: 'Ayran', published: false },
];

describe('MediaAssetStatusBadge — sebep bir cümledir', () => {
    it('durum sebebi gövde ölçeğindedir', () => {
        render(<MediaAssetStatusBadge status="scanning" reason="HEIC converting; 1–2 minutes" />);

        const reason = screen.getByText('HEIC converting; 1–2 minutes');

        expect(reason).toHaveClass('text-body');
        expect(reason.className).not.toMatch(/text-meta/);
    });

    it('taranan dosya "taranıyor" der; ekran onu hazır saymaz', () => {
        render(<MediaAssetStatusBadge status="scanning" reason={null} />);

        expect(screen.getByRole('status').textContent).toBe('Scanning in progress');
    });
});

describe('MediaUsageList — yayın durumu haptır', () => {
    it('yayındaki kullanım hap rozetle ve ikinci bir kanalla işaretlenir', () => {
        render(<MediaUsageList usages={USAGES} />);

        const live = screen.getByText('live menu');

        expect(live).toHaveClass('rounded-pill');
        expect(live.className).not.toMatch(/rounded-full/);
        expect(live).toHaveClass('bg-surface-success');
        expect(live).toHaveClass('text-body');
    });

    it('taslak kullanım da hap rozettir, yalnız tonu ayrışır', () => {
        render(<MediaUsageList usages={USAGES} />);

        const draft = screen.getByText('draft');

        expect(draft).toHaveClass('rounded-pill');
        expect(draft).toHaveClass('text-body');
    });
});

describe('MediaRemoteSection — bölüm başlığı', () => {
    it('başlık meta rolüne düşürülmez', () => {
        /*
            "Nerede kullanılıyor" bir bölüm BAŞLIĞIDIR; çekmecede sahibin gözü
            ilk ona takılır. Meta rolü zaman damgası ve sayaç içindir.
        */
        render(
            <MediaRemoteSection
                id="usages"
                heading="Where it is used"
                remote={{ state: 'ready', rows: USAGES }}
                loading="…"
                failed="…"
                empty="…"
            >
                {(rows) => <MediaUsageList usages={rows} />}
            </MediaRemoteSection>,
        );

        const heading = screen.getByRole('heading', { name: 'Where it is used' });

        expect(heading).toHaveClass('text-body');
        expect(heading).toHaveClass('font-bold');
        expect(heading.className).not.toMatch(/text-meta/);
        expect(heading.className).not.toMatch(/font-semibold/);
    });
});
