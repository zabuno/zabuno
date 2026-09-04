import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrDestinationRegion } from './QrDestinationRegion';

/**
 * QR-HONEST-STATE — FF-108, `docs/104` Döngü 4: "sayfa yalan söylemez".
 *
 * Somut arıza: `useCurrentPublication`, cevap YOLDAYKEN de sunucu 500
 * DÖNDÜĞÜNDE de `current: null` verir. Ekran yalnız ona bakıyordu ve üç ayrı
 * dünyayı tek cümleye indiriyordu — "önce menünüzü yayınlayın". Yayında bir
 * menüsü, masalarında basılı ve çalışan kartları olan restoran sahibine,
 * kodlarının hiç var olmadığı söyleniyordu; sahibin oradan çıkardığı sonuç
 * "yeniden yayınlayayım" ya da "yeniden basayım" olurdu.
 *
 * İkinci arıza aynı kökten: kod listesi de `hasCurrentPublication` false iken
 * hiç ÇEKİLMİYORDU. `loaded` false kaldığı için ne "yükleniyor" ne de "boş"
 * yazıyordu — ekranda hiçbir şey yoktu ve hiçbir açıklama da yoktu.
 *
 * Üçüncüsü: sunucu toplu üretim için bilerek 402 + `entitlement` döndürüyor,
 * istemci ise 201 olmayan her cevabı "Tekrar deneyin." diye gösteriyordu.
 * Tekrar denemek hiçbir zaman işe yaramaz; çıkış yolu plan yükseltmesidir.
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

const ITEM = {
    id: 4021,
    workspaceId: 71,
    locationId: 923,
    menuId: 42,
    token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    resolverUrl: 'https://zabuno.test/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    destinationType: 'published_menu',
    state: 'active',
};

describe('QrDestinationRegion — üç hâl ayrı (QR-HONEST-STATE)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('yayın bilgisi yoldayken "önce yayınlayın" DEMEZ, beklemeyi söyler', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoading
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByText(/checking whether your menu/i)).toBeInTheDocument();
        });

        expect(region.textContent ?? '').not.toMatch(/publish your menu first/i);
    });

    it('yayın sorgusu başarısızsa basılı kodların çalışmaya devam ettiğini söyler', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoadFailed
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByRole('alert')).toHaveTextContent(
                /could not reach the server/i,
            );
        });

        expect(within(region).getByRole('alert')).toHaveTextContent(/printed codes keep working/i);
        expect(region.textContent ?? '').not.toMatch(/publish your menu first/i);
    });

    it('yayın bilinmese bile VAR OLAN kodları çeker ve gösterir', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, [ITEM]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoadFailed
            />,
        );

        await waitFor(() => {
            expect(
                within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
            ).toHaveAttribute('href', ITEM.resolverUrl);
        });

        expect(
            fetchSpy.mock.calls.some(([url]) =>
                /brand\/locations\/923\/qr-codes$/.test(String(url)),
            ),
        ).toBe(true);
    });

    it('yayın gerçekten yokken sebep "önce yayınlayın" olur', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByText(/publish your menu first/i)).toBeInTheDocument();
        });

        expect(within(region).getByRole('button', { name: /create/i })).toBeDisabled();
    });
});

describe('BulkQrWizardFields — plan kısıtı hata değildir (QR-HONEST-STATE)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('402 gelince "tekrar deneyin" DEMEZ; plan yükseltme yolunu gösterir', async () => {
        const user = userEvent.setup();
        const onUpgrade = vi.fn();

        fetchSpy.mockImplementation((url: unknown, init?: RequestInit) => {
            const href = String(url);

            if (/tables\/bulk$/.test(href) && init?.method === 'POST') {
                return Promise.resolve(
                    jsonResponse(402, {
                        message: 'Plan does not include bulk QR generation.',
                        entitlement: 'qr.bulk_generation',
                    }),
                );
            }

            return Promise.resolve(jsonResponse(200, []));
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
                onUpgrade={onUpgrade}
            />,
        );

        await user.type(screen.getByLabelText(/table count/i), '12');
        await user.click(screen.getByRole('button', { name: /create table qr codes/i }));

        await waitFor(() => {
            expect(screen.getByText(/not included in your current plan/i)).toBeInTheDocument();
        });

        expect(screen.queryByText(/could not create/i)).toBeNull();

        await user.click(screen.getByRole('button', { name: /see plans/i }));
        expect(onUpgrade).toHaveBeenCalledTimes(1);
    });

    it('toplu sihirbazın düğmesi kapalıyken sebebi yazılıdır', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
            />,
        );

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });

        await waitFor(() => {
            expect(within(wizard).getByText(/publish your menu first/i)).toBeInTheDocument();
        });
    });
});

describe('QR kodun insan adı (FF-109)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('satırda masa adını ve alanını gösterir, seçicide token yazmaz', async () => {
        /*
            Masanın adı veritabanında vardı (`qr_codes.dining_table_id`) ama
            liste DTO'su onu düşürüyordu: sahip 40 kod arasından "T12"yi
            bulamıyor, ekranda yalnız 43 karakterlik token'lar görüyordu.
            Yeniden bastırmak — ürünün asıl işi — imkânsızdı.
        */
        fetchSpy.mockResolvedValue(
            jsonResponse(200, [
                { ...ITEM, id: 1, tableName: 'T12', areaLabel: 'Bahçe' },
                {
                    ...ITEM,
                    id: 2,
                    token: 'k2LmNoPqRsTuVwXyZ01234567890abc',
                    resolverUrl: 'https://zabuno.test/q/k2LmNoPqRsTuVwXyZ01234567890abc',
                    tableName: 'T13',
                    areaLabel: 'Bahçe',
                },
            ]),
        );

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const region = await screen.findByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByText('T12')).toBeInTheDocument();
        });
        expect(within(region).getAllByText('Bahçe')).toHaveLength(2);

        const selector = screen.getByRole('combobox', { name: /qr code/i });
        expect(selector.textContent ?? '').not.toMatch(new RegExp(ITEM.token));
        expect(within(selector).getByRole('option', { name: 'T12 · Bahçe' })).toBeInTheDocument();
    });

    it('adsız kodlar birden fazlaysa seçicide birbirinden ayrılır', async () => {
        fetchSpy.mockResolvedValue(
            jsonResponse(200, [
                { ...ITEM, id: 1, tableName: null },
                {
                    ...ITEM,
                    id: 2,
                    token: 'k2LmNoPqRsTuVwXyZ01234567890abc',
                    resolverUrl: 'https://zabuno.test/q/k2LmNoPqRsTuVwXyZ01234567890abc',
                    tableName: null,
                },
            ]),
        );

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const selector = await screen.findByRole('combobox', { name: /qr code/i });

        expect(
            within(selector).getByRole('option', { name: /entrance code 1/i }),
        ).toBeInTheDocument();
        expect(
            within(selector).getByRole('option', { name: /entrance code 2/i }),
        ).toBeInTheDocument();
    });
});

describe('Yıkıcı eylem onay ister (FF-110)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('menüden "Disable" seçmek tek başına kodu kapatmaz; vazgeçilebilir', async () => {
        /*
            Kapatma, satırın altında "Taşı"nın yanında duran ve ondan yalnız
            RENKLE ayrılan küçük bir yazıydı. Renk tek başına bir ayrım
            değildir ve iki hedef bitişikti; yanlış tıklamanın bedeli, o
            masadaki basılı kartın misafir için ölmesi.
        */
        const user = userEvent.setup();
        fetchSpy.mockImplementation((url: unknown) =>
            Promise.resolve(
                /qr-codes$/.test(String(url))
                    ? jsonResponse(200, [{ ...ITEM, tableName: 'T12' }])
                    : jsonResponse(200, []),
            ),
        );

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        await screen.findByText('T12');

        await user.click(screen.getByRole('button', { name: /more actions for t12/i }));
        await user.click(screen.getByRole('menuitem', { name: /disable/i }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent(/no longer see your menu/i);
        expect(dialog).toHaveTextContent(/without reprinting/i);

        await user.click(within(dialog).getByRole('button', { name: /cancel/i }));

        expect(fetchSpy.mock.calls.some(([url]) => /\/disable$/.test(String(url)))).toBe(false);
    });
});

describe('Basılabilir deste (FF-111)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function codes(count: number) {
        return Array.from({ length: count }, (_unused, index) => ({
            ...ITEM,
            id: index + 1,
            token: `token${String(index).padStart(27, '0')}`,
            resolverUrl: `https://zabuno.test/q/token${String(index)}`,
            tableName: `T${String(index + 1)}`,
        }));
    }

    it('birden fazla kod varsa deste birincil eylem olur ve milimetreyi YAZAR', async () => {
        /*
            Tek çıktı A4'ün ortasında tek bir kareydi: 40 masa = 40 sayfa,
            her biri %97 beyaz ve baskıdan sonra ayırt edilemez. Sahip
            kartları dağıtırken hangisinin hangi masa olduğunu bilemiyordu.
        */
        fetchSpy.mockResolvedValue(jsonResponse(200, codes(3)));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const link = await screen.findByRole('link', { name: /download print sheet/i });
        expect(link).toHaveAttribute(
            'href',
            '/api/workspaces/71/brand/locations/923/qr-codes/print.pdf',
        );

        // Milimetre ekranda yazar — kâğıt boyu açılır listesinin yapamadığı iş.
        expect(screen.getByText(/each code prints at 4 cm/i)).toBeInTheDocument();
        expect(screen.getByText(/3 codes on 1 A4 page/i)).toBeInTheDocument();
    });

    it('tek kod varsa deste ÖNERİLMEZ', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, codes(1)));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        await screen.findByText('T1');
        expect(screen.queryByRole('link', { name: /print sheet/i })).toBeNull();
    });

    it('deste tek istekte sığmıyorsa parçalar AYRI AYRI sunulur, sessizce kırpılmaz', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, codes(60)));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        expect(
            await screen.findByRole('link', { name: /print sheet 1 of 2/i }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /print sheet 2 of 2/i })).toHaveAttribute(
            'href',
            '/api/workspaces/71/brand/locations/923/qr-codes/print.pdf?chunk=2',
        );
    });
});

describe('Tema ve kalıcılık sözleşmesi (FF-112)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('ürünün en güçlü argümanını EKRANDA yazar: basılı kart ölmez', async () => {
        /*
            Bu sektördeki en pahalı arıza, üçüncü taraf bir kısaltıcıya bağlı
            kodların bir gün ölmesidir: masadaki kırk kart aynı anda çöp olur.
            Zabuno'nun kodları kalıcı ve yeniden yönlendirilebilirdi — ama
            sahip bunu bilmeden bastırıyordu.
        */
        fetchSpy.mockResolvedValue(jsonResponse(200, [{ ...ITEM, tableName: 'T1' }]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const region = await screen.findByRole('region', { name: /qr destination/i });
        expect(within(region).getByText(/printed codes keep working/i)).toBeInTheDocument();
        expect(within(region).getByText(/never reprint because something changed/i)).toBeTruthy();
    });

    it('her temanın taranabilir olduğunu söyler', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, [{ ...ITEM, tableName: 'T1' }]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        expect(await screen.findByText(/every theme here prints dark on light/i)).toBeTruthy();
    });

    it('marka rengi taranamayacak kadar açıksa bunu İNDİRMEDEN ÖNCE söyler', async () => {
        const user = userEvent.setup();
        const onEditBrand = vi.fn();
        fetchSpy.mockResolvedValue(jsonResponse(200, [{ ...ITEM, tableName: 'T1' }]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
                brandPrimaryColor="#FFE066"
                onEditBrand={onEditBrand}
            />,
        );

        await screen.findByText('T1');
        await user.click(screen.getByRole('radio', { name: /branded/i }));

        expect(screen.getByText(/too light to scan reliably/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /set your brand colour/i }));
        expect(onEditBrand).toHaveBeenCalledTimes(1);
    });

    it('marka rengi kullanılabilirse uyarı ÇIKMAZ', async () => {
        const user = userEvent.setup();
        fetchSpy.mockResolvedValue(jsonResponse(200, [{ ...ITEM, tableName: 'T1' }]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
                brandPrimaryColor="#1B4332"
            />,
        );

        await screen.findByText('T1');
        await user.click(screen.getByRole('radio', { name: /branded/i }));

        expect(screen.queryByText(/too light to scan/i)).toBeNull();
        expect(screen.queryByText(/prints in black/i)).toBeNull();
    });
});

describe('Ekran bir KÜTÜK, üreteç değil (FF-114)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('çok kod varken deste ÖNCE gelir, tek kod bölümü kapalı başlar', async () => {
        /*
            Ekran bir ÜRETEÇ gibi kuruluydu: en üstte biçim, kâğıt, yön ve tek
            bir kodun önizlemesi; sahibin asıl işi — masalara dağıtılacak
            kartları basmak — en alttaydı. Restoran sahibi buraya "QR ayarı
            yapmaya" gelmez.
        */
        fetchSpy.mockResolvedValue(
            jsonResponse(200, [
                { ...ITEM, id: 1, tableName: 'T1' },
                { ...ITEM, id: 2, token: 'second-token-aaaaaaaaaaaaaaaaaaa', tableName: 'T2' },
            ]),
        );

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const sheetLink = await screen.findByRole('link', { name: /download print sheet/i });
        // FF-120: bölümün adı değişti — orada kartın teması değil, kodun HAM
        // dosyası var. Kart artık kendi sihirbazında ve birincil iş o.
        const single = screen.getByText(/download the bare code file/i);

        // Deste, tek kod bölümünün ÜSTÜNDE durur.
        expect(
            sheetLink.compareDocumentPosition(single) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        // `<details>` kapalı başlar ama içeriği DOM'da kalır: klavye ve ekran
        // okuyucu etkilenmez.
        expect(single.closest('details')).not.toHaveAttribute('open');
        expect(screen.getByLabelText(/output format/i)).toBeInTheDocument();
    });

    it('tek kod varken deste önerilmez ve tek kod bölümü açıkta durur', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, [{ ...ITEM, tableName: 'T1' }]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        await screen.findByText('T1');
        expect(screen.queryByText(/download the bare code file/i)).toBeNull();
        expect(screen.getByLabelText(/output format/i)).toBeInTheDocument();
    });
});

describe('Masa kartı sihirbazı (FF-120, FF-122)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function codes() {
        return [
            { ...ITEM, id: 1, tableName: 'T1', areaLabel: 'Bahçe', areaId: 10 },
            {
                ...ITEM,
                id: 2,
                token: 'k2LmNoPqRsTuVwXyZ01234567890abc',
                tableName: 'T2',
                areaLabel: 'Üst kat',
                areaId: 11,
            },
        ];
    }

    it('ilk soru "hangi dosya" değil "kim basılacak"', async () => {
        /*
            Restoran sahibi buraya bir dosya biçimi seçmeye gelmez; masalarına
            kart koymaya gelir. Eski ekranda en üstte biçim/kâğıt/yön ve
            karekodun piksel renkleri vardı — hiçbiri onun sorduğu soru değildi.
        */
        fetchSpy.mockResolvedValue(jsonResponse(200, codes()));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const wizard = await screen.findByRole('region', { name: /table card/i });

        expect(within(wizard).getByRole('radio', { name: /this code/i })).toBeInTheDocument();
        expect(within(wizard).getByRole('radio', { name: /one area/i })).toBeInTheDocument();
        expect(within(wizard).getByRole('radio', { name: /all 2 codes/i })).toBeInTheDocument();
    });

    it('alan seçenekleri gerçek alanlardan gelir ve toplu çıktı bir ARŞİVDİR', async () => {
        const user = userEvent.setup();
        fetchSpy.mockResolvedValue(jsonResponse(200, codes()));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const wizard = await screen.findByRole('region', { name: /table card/i });

        await user.click(within(wizard).getByRole('radio', { name: /one area/i }));
        expect(within(wizard).getByRole('radio', { name: 'Bahçe' })).toBeInTheDocument();
        await user.click(within(wizard).getByRole('radio', { name: 'Bahçe' }));

        // Son adıma geç.
        await user.click(within(wizard).getByRole('button', { name: /4\. download/i }));

        const zip = within(wizard).getByRole('link', { name: /zip of pdfs/i });
        expect(zip).toHaveAttribute(
            'href',
            expect.stringContaining('/brand/locations/923/qr-cards.zip'),
        );
        expect(zip.getAttribute('href')).toContain('areaId=10');
    });

    it('tek kod seçiliyken arşiv değil tek dosya sunulur', async () => {
        const user = userEvent.setup();
        fetchSpy.mockResolvedValue(jsonResponse(200, codes()));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
            />,
        );

        const wizard = await screen.findByRole('region', { name: /table card/i });
        await user.click(within(wizard).getByRole('button', { name: /4\. download/i }));

        expect(
            within(wizard).getByRole('link', { name: /download card \(pdf\)/i }),
        ).toHaveAttribute('href', expect.stringContaining('/qr-codes/1/card.pdf'));
        expect(within(wizard).queryByRole('link', { name: /zip/i })).toBeNull();
    });
});
