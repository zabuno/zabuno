import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

/**
 * MODÜL ENVANTERİ EKRANI — `docs/111` adım 2, 3 ve 4; kabul ölçütleri §8.
 *
 * Bu testlerin çoğu ekranın NE ÇİZMEDİĞİNİ dondurur. Sebebi ölçülmüş: modül
 * tanımlarının 62'si de bir zamanlar kendini "PLANNING ONLY —
 * çalıştırılamaz" ilan ediyordu ve en az 18'inde bu yanlıştı. O cümleyi
 * superadmin'in "hangi modüller var" diye baktığı yere taşımak,
 * `docs/109` §8.7'deki kusur ailesine en görünür üyeyi eklemek olurdu.
 *
 * Aynı sebeple açma/kapama anahtarı — devre dışı olanı bile — çizilmez:
 * bugün kodda hiçbir rota, iş ya da menü bir modül anahtarına bakmıyor,
 * ve devre dışı bir düğme tutulmayacak bir söz verir (`docs/109` §8.4).
 *
 * FF-210 iki söz daha ekledi ve ikisi de "iddia ile ölçümün mesafesi"
 * hakkında: rozet gözlemsiz çizilemez, ve ölçülemeyen bir modül sessizce
 * "yok" değil AÇIKÇA "bilinmiyor" olur.
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
    specModules: [
        {
            slug: 'menu-catalog',
            name: 'Menu Catalog',
            specPath: 'spec/menu-catalog.md',
            moduleClass: 'product',
            mapping: 'mapped',
            mappingNote: '',
            contexts: ['MenuCatalog'],
            presence: 'implemented',
            observation: {
                directories: ['app/Domain/MenuCatalog', 'app/Application/MenuCatalog'],
                routeFiles: ['routes/api/menu-catalog.php'],
                tables: ['menus', 'menu_items'],
                testFiles: 21,
            },
        },
        {
            slug: 'opt-11-scheduled-publishing',
            name: 'OPT-11 — Scheduled Publishing',
            specPath: 'spec/opt-11-scheduled-publishing.md',
            moduleClass: 'optional',
            mapping: 'unknown',
            mappingNote: 'Publication baglaminin icinde bir dilim; ayri bir dizini yok',
            contexts: [],
            presence: 'unknown',
            observation: { directories: [], routeFiles: [], tables: [], testFiles: 0 },
        },
        {
            slug: 'opt-17-loyalty',
            name: 'OPT-17 — Loyalty',
            specPath: 'spec/opt-17-loyalty.md',
            moduleClass: 'optional',
            mapping: 'none',
            mappingNote: '',
            contexts: [],
            presence: 'definition-only',
            observation: { directories: [], routeFiles: [], tables: [], testFiles: 0 },
        },
    ],
    unmappedContexts: ['Rating', 'Url'],
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

    it('never draws a status badge without the observation that produced it', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const specs = await screen.findByRole('region', {
            name: 'Module specs measured against the code',
        });

        // `docs/111` §8.4: rozet tek başına bir iddiadır; gözlem onu
        // denetlenebilir yapar. İkisi aynı satırda üretilir, bu yüzden
        // rozetin bulunduğu her yerde sayım da okunur.
        const implemented = within(specs)
            .getAllByRole('listitem')
            .find((item) => item.textContent?.includes('Menu Catalog'));

        expect(implemented?.textContent).toContain('Implemented');
        expect(implemented?.textContent).toContain('2 directories');
        expect(implemented?.textContent).toContain('1 route files');
        expect(implemented?.textContent).toContain('2 tables');
        expect(implemented?.textContent).toContain('21 tests');
    });

    it('says "unknown" with its reason instead of quietly reporting nothing', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const specs = await screen.findByRole('region', {
            name: 'Module specs measured against the code',
        });

        const slice = within(specs)
            .getAllByRole('listitem')
            .find((item) => item.textContent?.includes('Scheduled Publishing'));

        expect(slice?.textContent).toContain('Unknown');
        expect(slice?.textContent).toContain('ayri bir dizini yok');
        // Ölçüm yapılmadığında "0 dizin" yazmak, yapılmamış bir ölçümün
        // sonucunu uydurmak olurdu (`docs/109` §8.3).
        expect(slice?.textContent).not.toContain('0 directories');
        expect(slice?.textContent).not.toContain('0 tests');
    });

    it('says out loud what the badge does not claim', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const specs = await screen.findByRole('region', {
            name: 'Module specs measured against the code',
        });

        // Kanıtsız bir "çalışıyor" rozeti olmayan bir sayfadan kötüdür:
        // superadmin ona bakıp aramayı bırakır (`docs/111` §1).
        expect(
            within(specs).getByText(/is not .the code|not .it runs in production|does not say/i),
        ).toBeInTheDocument();
        expect(
            within(specs).getByText(/unconfigured, undeployed or unreachable/i),
        ).toBeInTheDocument();
    });

    it('lists code contexts that no module spec claims, on their own', async () => {
        const { ModulesPage } = await importPage();

        render(<ModulesPage />);

        const unmapped = await screen.findByRole('region', {
            name: 'Code contexts with no module spec',
        });

        expect(within(unmapped).getByText('Rating')).toBeInTheDocument();
        expect(within(unmapped).getByText('Url')).toBeInTheDocument();
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
