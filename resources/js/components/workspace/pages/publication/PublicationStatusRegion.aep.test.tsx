import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { PublicationStatusRegion, type CurrentPublication } from './PublicationStatusRegion';

/**
 * ONAY VE YAYIN ŞERİDİ — kanonik teslim paketi (`DESIGN_SPEC.md` §9 "Onay ve
 * yayın": onay kutusu + yayın düğmesi, ALTTA ayrı bir `surface-subtle` şerit).
 *
 * Restoran sahibinin yolculuğu: ekranın tek gerçek eylemi "Yayınla"dır ve o
 * eylem geri alınabilir olsa bile misafirin gördüğü menüyü değiştirir. Onay
 * kutusu ile düğme, sayfanın geri kalanıyla AYNI zeminde durduğunda ikisi de
 * "bir bilgi satırı" gibi okunuyordu; sahip yayınlamadan önce duraksaması
 * gereken yerde duraksamıyordu.
 *
 * Paket bu ikisini ayrı bir tonlu şeride alır: zemin değişir, göz durur.
 */
const CURRENT: CurrentPublication = {
    id: 12,
    workspaceId: 7,
    menuId: 42,
    locationId: 3,
    version: 4,
    state: 'published',
    publishedAt: '2026-08-28T18:00:00Z',
    snapshot: { categories: [] },
};

function renderStatus(overrides: Partial<Parameters<typeof PublicationStatusRegion>[0]> = {}) {
    render(
        <PublicationStatusRegion
            current={CURRENT}
            loading={false}
            loadError={false}
            onRetry={() => {}}
            checklistReady
            confirmed={false}
            onConfirmedChange={() => {}}
            onPublish={() => {}}
            publishing={false}
            errorMessage={null}
            {...overrides}
        />,
    );
}

describe('PublicationStatusRegion — onay ve yayın kendi şeridinde durur', () => {
    it('onay kutusu ve yayın düğmesi tonlu bir şeritte toplanır', () => {
        renderStatus();

        const strip = screen.getByRole('checkbox').closest('[data-publish-commit]');

        expect(strip).not.toBeNull();
        expect(strip).toHaveClass('bg-surface-subtle');
        expect(strip).toHaveClass('rounded-[var(--radius-lg)]');
        // Düğme de aynı şeridin içinde: karar ve eylem ayrılmaz.
        expect(strip?.contains(screen.getByRole('button', { name: /publish/i }))).toBe(true);
    });

    it('yeniden dene düğmesi katalogdan çevrilir, koda gömülmez', () => {
        /*
            Önceden düğmenin metni kodun içine İngilizce gömülüydü ("Retry").
            Türkçe kullanan bir restoran sahibi, hata anında — yani panik
            anında — ekranındaki tek düğmeyi okuyamıyordu.
        */
        renderStatus({ current: null, loadError: true });

        expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Retry' })).toBeNull();
    });

    it('yayın özeti sürüm sayısını hizalı yazar', () => {
        /*
            "Version 4 · Published" satırındaki sayı, geçmiş listesindeki
            sürüm numaralarıyla aynı ailedendir; ikisi de `tabular-nums`
            olmazsa aynı sayı iki yerde farklı genişlikte çizilir.
        */
        renderStatus();

        expect(screen.getByText(/Version 4/)).toHaveClass('tabular-nums');
    });

    it('ağırlık merdiveni 400/500/700 ve büyük harfe çevirme yoktur', () => {
        renderStatus();

        const region = screen.getByRole('region', { name: /publication status/i });
        const classLists: string[] = [region.className];
        region.querySelectorAll<HTMLElement>('*').forEach((el) => {
            if (typeof el.className === 'string') classLists.push(el.className);
        });

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
    });
});
