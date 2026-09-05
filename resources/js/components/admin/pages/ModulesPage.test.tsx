import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

/**
 * MODÜL ENVANTERİ EKRANI — `docs/111` adım 2, kabul ölçütleri §8.
 *
 * Bu testlerin çoğu ekranın NE ÇİZMEDİĞİNİ dondurur. Sebebi ölçülmüş:
 * `modules/` klasöründeki 62 dosyanın hepsi kendini "PLANNING ONLY —
 * çalıştırılamaz" ilan ediyor ve en az 18'inde bu yanlış. O cümleyi
 * superadmin'in "hangi modüller var" diye baktığı yere taşımak,
 * `docs/109` §8.7'deki kusur ailesine en görünür üyeyi eklemek olurdu.
 *
 * Aynı sebeple açma/kapama anahtarı — devre dışı olanı bile — çizilmez:
 * bugün kodda hiçbir rota, iş ya da menü bir modül anahtarına bakmıyor,
 * ve devre dışı bir düğme tutulmayacak bir söz verir (`docs/109` §8.4).
 */

const PAYLOAD = {
    modules: [
        {
            code: 'CORE-01',
            name: 'Identity & Sessions',
            moduleClass: 'core',
            version: '1.0.0',
            dependencies: [],
            deterministicBaseline: 'required',
            aiPosture: 'advisory',
        },
        {
            code: 'CORE-03',
            name: 'Authorization',
            moduleClass: 'core',
            version: '1.2.0',
            dependencies: ['CORE-01', 'CORE-02'],
            deterministicBaseline: 'required',
            aiPosture: 'advisory',
        },
    ],
    contextGraph: {
        nodes: ['Analytics', 'MenuCatalog', 'Publication'],
        edges: [
            {
                from: 'Publication',
                to: 'MenuCatalog',
                evidencePath: 'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
            },
        ],
    },
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

async function importPage() {
    return import('./ModulesPage') as unknown as Promise<{
        ModulesPage: React.ComponentType;
    }>;
}

function stubFetch(status: number, body: unknown): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => jsonResponse(status, body)),
    );
}

/**
 * Satırı İLK HÜCRESİNDEN bulur.
 *
 * Düz metin araması burada yanıltıcıdır: `CORE-01` hem kendi satırının
 * kodudur hem de `CORE-03`'ün bağımlılık hücresinde geçer. Aynı belirsizlik
 * bağlam tablosunda da var — `Publication` hem bir düğüm hem de bir kanıt
 * yolunun parçası.
 */
function rowStartingWith(region: HTMLElement, first: string): HTMLElement {
    const row = within(region)
        .getAllByRole('row')
        .find((candidate) => within(candidate).queryAllByRole('cell')[0]?.textContent === first);

    if (row === undefined) throw new Error(`"${first}" ile başlayan satır yok.`);

    return row;
}

describe('ModulesPage', () => {
    beforeEach(() => {
        stubFetch(200, PAYLOAD);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('reads the inventory from the admin endpoint and names each module with its registry fields', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const registry = await screen.findByRole('region', { name: 'Core kernel registry' });

        expect(rowStartingWith(registry, 'CORE-01')).toBeInTheDocument();
        expect(within(registry).getByText('Identity & Sessions')).toBeInTheDocument();
        expect(within(registry).getByText('1.2.0')).toBeInTheDocument();
        // Kaynak dosya ekranda yazılı: liste değil, listenin nereden geldiği
        // bu sayfanın asıl cevabıdır.
        expect(within(registry).getByText(/config\/core-modules\.php/)).toBeInTheDocument();

        expect(fetch).toHaveBeenCalledWith('/api/admin/modules', expect.anything());
    });

    it('leaves an empty dependency list empty instead of writing a zero, a dash or "unknown"', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const registry = await screen.findByRole('region', { name: 'Core kernel registry' });
        const identityRow = rowStartingWith(registry, 'CORE-01');

        const cells = within(identityRow).getAllByRole('cell');
        const dependsOn = cells[cells.length - 1];
        expect(dependsOn.textContent?.trim()).toBe('');

        // `docs/109` §8.3: veri yoksa alan boş kalır. "0", "-" ve "bilinmiyor"
        // hepsi bir cevap gibi görünür; hiçbiri cevap değildir.
        for (const filler of ['—', '-', '0', 'unknown', 'bilinmiyor', 'n/a']) {
            expect(dependsOn.textContent).not.toContain(filler);
        }
    });

    it('shows the dependency graph with the file that proves each edge, and no edge without one', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const graph = await screen.findByRole('region', {
            name: 'Observed dependencies between contexts',
        });

        const publicationRow = rowStartingWith(graph, 'Publication');
        expect(within(publicationRow).getByText('MenuCatalog')).toBeInTheDocument();
        expect(
            within(publicationRow).getByText(
                'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
            ),
        ).toBeInTheDocument();

        // Kenarı olmayan bağlam listeden düşmez, ama "bağımsız" da denmez:
        // ölçülmemiş olmak, yokluk değildir (`docs/111` §4).
        const analyticsCells = within(rowStartingWith(graph, 'Analytics')).getAllByRole('cell');
        expect(analyticsCells[1].textContent?.trim()).toBe('');
        expect(analyticsCells[2].textContent?.trim()).toBe('');
    });

    it('draws no enable/disable control, not even a disabled one', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        await screen.findByRole('region', { name: 'Core kernel registry' });

        expect(screen.queryAllByRole('switch')).toHaveLength(0);
        expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
        expect(screen.queryAllByRole('button')).toHaveLength(0);
    });

    it('never renders a status claim taken from the module spec files', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        await screen.findByRole('region', { name: 'Core kernel registry' });

        // 62 spec dosyasının hepsinde bulunan cümle. Ekrana çıkarsa, en az
        // 18 modül için yalan söylemiş oluruz.
        expect(screen.queryByText(/PLANNING ONLY/i)).toBeNull();
        expect(screen.queryByText(/not runnable/i)).toBeNull();
    });

    it('says the inventory could not be read instead of drawing an empty one', async () => {
        stubFetch(500, {});
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            /module inventory could not be loaded/i,
        );
    });
});
