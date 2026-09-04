import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { DiningAreasRegion } from './DiningAreasRegion';

/**
 * SALON BÖLÜMLERİ LİSTESİNİN AEP RİTMİ — kanonik teslim paketi
 * (`DESIGN_SPEC.md` "Kart grameri": tek kart, içinde İNCE AYRAÇLI satırlar).
 *
 * Restoran sahibinin yolculuğu: "salon üst kat / salon içerisi / salon bahçe"
 * üç satırdır ve sahibin gözü onları TEK bir liste olarak okumalıdır — çünkü
 * hepsi aynı salonun parçasıdır. Satırlar farklı ritimlerde çizilirse (biri
 * alt ayraçlı, diğeri üst ayraçlı, yükseklikleri serbest) liste bir tablo
 * gibi değil, üst üste yığılmış üç ayrı kutu gibi okunur.
 *
 * Ritmin kaynağı YOĞUNLUK jetonlarıdır: `--density-row-height` ve
 * `--density-padding-inline`. Sahip Ayarlar'dan "Sıkı / Standart / Ferah"
 * seçtiğinde bu liste de onunla değişmelidir; elle yazılmış bir `py-1`
 * yoğunluk anahtarını sağır bırakır.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function stubAreas() {
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                ({
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => [
                        { id: 1, label: 'Salon üst kat', tableCount: 12 },
                        { id: 2, label: 'Salon bahçe', tableCount: 4 },
                    ],
                }) as Response,
        ),
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('DiningAreasRegion — satır kart değildir', () => {
    it('satırlar ÜSTTEN ayraçlıdır ve ilk satırda ayraç yoktur', async () => {
        /*
            Ayraç ÜSTE konur, alta değil. Alt ayraçlı bir listede son satırın
            ayracını ayrıca susturmak gerekir ve o susturma unutulduğunda
            listenin altında kartın kendi kenarlığıyla çakışan ikinci bir
            çizgi belirir. Üstten ayraç, listeye eklenen her yeni satırı
            kendiliğinden doğru çizer — düzeltilecek bir istisna kalmaz.
            Uygulanmış örnek: `team/TeamMemberList.tsx`.
        */
        stubAreas();
        render(<DiningAreasRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const row = (await screen.findByText('12 tables')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row).not.toHaveClass('border-b');
    });

    it('satır yüksekliği ve yatay dolgusu yoğunluk jetonundan gelir', async () => {
        stubAreas();
        render(<DiningAreasRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const row = (await screen.findByText('12 tables')).closest('li');

        expect(row).toHaveClass('min-h-[var(--density-row-height)]');
        expect(row).toHaveClass('px-[var(--density-padding-inline)]');
    });
});
