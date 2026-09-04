import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BulkQrWizardFields } from './BulkQrWizardFields';

/**
 * TOPLU MASA KODU — AEP okuma sözleşmesi (kanonik teslim paketi,
 * `DESIGN_SPEC.md` §4 "Toplu masa kodu — tek soru").
 *
 * Restoran sahibinin yolculuğu: kırk masa yaratır ve ekran ona kırk bağlantı
 * verir. Bu kırk satır bir LİSTEDİR — sahip "Masa 13 nerede" diye tarar.
 * Aralarında boşluk bırakılmış, ayraçsız kırk bağlantı bir liste değil bir
 * yığındır: göz her satırda yeniden hizalanmak zorunda kalır ve sahip
 * aradığı masayı bulmak için yukarıdan aşağı okur.
 *
 * NEDEN AYRI DOSYA: `BulkQrWizardFields.test.tsx` sihirbazın SÖZLEŞMESİNİ
 * (hangi uca, hangi gövdeyle, hangi durumda istek gider) korur ve o
 * sözleşme sunucu değişince değişir. Buradaki iddialar teslim paketi
 * değişince değişir.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 11;

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function stubCreateTwoTables() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});

            return jsonResponse(201, {
                areas: [{ id: 1 }],
                tables: [
                    { id: 91, name: 'Masa 13' },
                    { id: 92, name: 'Masa 14' },
                ],
                qrCodes: [
                    { tableId: 91, resolverUrl: 'https://zabuno.com/q/aaa' },
                    { tableId: 92, resolverUrl: 'https://zabuno.com/q/bbb' },
                ],
            });
        }),
    );
}

async function createTwoTables() {
    const user = userEvent.setup();

    render(
        <BulkQrWizardFields
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            menuId={MENU_ID}
            hasCurrentPublication
        />,
    );

    await user.type(screen.getByLabelText(/table count/i), '2');
    await user.click(screen.getByRole('button', { name: /create table qr codes/i }));

    return user;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('BulkQrWizardFields — üretilen kodlar bir listedir', () => {
    it('her masa satırı ince ayraçla ayrılır, kendi kutusuyla değil', async () => {
        stubCreateTwoTables();
        await createTwoTables();

        const row = (await screen.findByRole('link', { name: 'Masa 13' })).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
    });

    it('satır ritmi yoğunluk jetonlarına bağlıdır', async () => {
        /*
            Sahip Ayarlar'dan "Ferah" seçtiğinde kırk masalık liste de
            ferahlamalıdır. Elle yazılmış bir `gap-1` yoğunluk anahtarını
            sağır bırakır: ekranın yarısı değişir, yarısı olduğu yerde kalır
            ve sahip ayarın bozuk olduğunu düşünür.
        */
        stubCreateTwoTables();
        await createTwoTables();

        const row = (await screen.findByRole('link', { name: 'Masa 13' })).closest('li');

        expect(row).toHaveClass('min-h-[var(--density-row-height)]');
        expect(row).toHaveClass('px-[var(--density-padding-inline)]');
    });
});

describe('BulkQrWizardFields — AEP tipografi', () => {
    it('form etiketi ve hata metni gövde boyundadır', () => {
        /*
            `--text-meta` ölçüm etiketi ve zaman damgası içindir; soru ve
            hata mesajı değil (`app.css` §text-meta: "Etiket, gövde, buton
            metni veya hata mesajı için KULLANILMAZ").

            Bugün ikisi de 1rem'e bağlı, yani ekranda fark yok — ama meta
            ölçeği ikincil bilgi için yarın küçüldüğünde "Enter a whole
            number between 1 and 500." uyarısı da onunla küçülür ve hatayı
            okunamaz yapar.
        */
        render(<BulkQrWizardFields hasCurrentPublication={false} />);

        const label = screen.getByText('Table count', { selector: 'label' });

        expect(label).toHaveClass('text-body');
        expect(label).not.toHaveClass('text-meta');
    });
});
