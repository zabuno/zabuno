import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { WorkspaceIdentityRegion } from './WorkspaceIdentityRegion';

/**
 * ÇALIŞMA ALANI SEKMESİ — kanonik kaynak (`panel.dc.html` > "Ayarlar" >
 * `tabAccount`) burada çalışma alanının ADINI ve PANEL ADRESİNİ gösteriyor,
 * ikisini de salt-okunur, adresin altında da sebebini yazıyor:
 * "Değiştirilemez — ekip bağlantıları buna bağlı."
 *
 * NEDEN BU TEST: depoda bu sekmenin yerinde kişisel ad/şifre formu vardı ve
 * çalışma alanının kendisi ürünün hiçbir ekranında yazılı değildi. İki
 * restoranı olan bir sahip "hangi paneldeyim?" sorusunu ancak tarayıcının
 * adres çubuğundan okuyarak cevaplayabiliyordu.
 *
 * VERİ UYDURULMAZ (docs/109 §4 madde 3): ad ve adres sunucunun zaten verdiği
 * `GET /api/workspace-context` gövdesinden okunur. Kaynağın aynı sekmedeki
 * diğer üç bloğu (misafir menüsü dilleri, özel alan adı, tehlikeli bölge)
 * hiç çizilmez — arkalarında ne uç nokta ne veri var.
 */
const CONTEXT = { id: 41, name: 'Paşa Döner', slug: 'pasa-doner', state: 'active' };

function stubFetch(body: unknown = CONTEXT, status = 200) {
    const calls: string[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            calls.push(String(url));

            return {
                ok: status >= 200 && status < 300,
                status,
                headers: new Headers(),
                json: async () => body,
            } as Response;
        }),
    );

    return calls;
}

describe('WorkspaceIdentityRegion (docs/109 — Ayarlar > Çalışma alanı)', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('çalışma alanının adını ve panel adresini salt-okunur gösterir', async () => {
        const calls = stubFetch();

        render(<WorkspaceIdentityRegion workspaceId={41} />);

        const name = (await screen.findByLabelText('Name')) as HTMLInputElement;
        expect(name.value).toBe('Paşa Döner');
        expect(name.readOnly).toBe(true);

        const address = screen.getByLabelText('Panel address') as HTMLInputElement;
        // Adres GERÇEK adrestir: kabuk bu çalışma alanını `/app/<slug>` altında açar.
        expect(address.value).toContain('/app/pasa-doner');
        expect(address.readOnly).toBe(true);

        expect(calls).toContain('/api/workspace-context');
    });

    it('adresin neden değiştirilemediğini yazar', async () => {
        stubFetch();

        render(<WorkspaceIdentityRegion workspaceId={41} />);

        // Sebebi yazılmayan bir kilit, kullanıcıya arıza gibi görünür.
        expect(
            await screen.findByText('This cannot be changed — your team’s links depend on it.'),
        ).toBeInTheDocument();
    });

    /*
        Sunucu cevap vermezse bölüm SESSİZCE BOŞ KALMAZ. Boş bir kart,
        kullanıcıya "çalışma alanım silinmiş" dedirtir.
    */
    it('okunamazsa sebebini söyler', async () => {
        stubFetch(null, 500);

        render(<WorkspaceIdentityRegion workspaceId={41} />);

        expect(
            await screen.findByText('Your workspace details could not be loaded.'),
        ).toBeInTheDocument();
    });
});
