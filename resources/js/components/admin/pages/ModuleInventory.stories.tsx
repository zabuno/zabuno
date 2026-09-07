import type { Meta, StoryObj } from '@storybook/react-vite';

import { ModuleInventory, type SpecModuleRow } from './ModuleInventory';
import { ThemeRoot } from '../../theme/ThemeRoot';

/**
 * Modül envanteri, GERÇEK bir düzen motorunda (`docs/117`, FF-210).
 *
 * Bu ekranın bütün diğer testleri jsdom'da koşuyor ve jsdom düzen HESAPLAMAZ:
 * orada hiçbir kutunun boyu yoktur, hiçbir şey taşmaz, hiçbir dokunma hedefi
 * ölçülemez. Yani "320 pikselde ne oluyor" sorusu bu ekran için hiç
 * sorulamıyordu. Hikâye, `scripts/mobile-ux-audit` bu soruyu sorabilsin diye
 * var — ölçüm yapılmadıysa sonuç "geçti" değil "bilinmiyor"dur.
 *
 * Bileşen getirmeyi bilmez; hikâye ona uçtan gelenin aynı şeklini verir.
 */
const specs: SpecModuleRow[] = [
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
            directories: [
                'app/Domain/MenuCatalog',
                'app/Application/MenuCatalog',
                'app/Infrastructure/MenuCatalog',
            ],
            routeFiles: ['routes/api/menu-catalog.php'],
            tables: ['menus', 'menu_categories', 'menu_items', 'products'],
            testFiles: 24,
        },
    },
    {
        slug: 'core-taxonomy',
        name: 'CORE-09 — Taxonomy',
        specPath: 'spec/core-taxonomy.md',
        moduleClass: 'core',
        mapping: 'mapped',
        mappingNote: '',
        contexts: ['Taxonomy'],
        presence: 'partial',
        observation: {
            directories: ['app/Domain/Taxonomy'],
            routeFiles: [],
            tables: [],
            testFiles: 0,
        },
    },
    {
        slug: 'opt-11-scheduled-publishing',
        name: 'OPT-11 — Scheduled Publishing',
        specPath: 'spec/opt-11-scheduled-publishing.md',
        moduleClass: 'optional',
        mapping: 'unknown',
        mappingNote: 'Publication bağlamının içinde bir dilim; ayrı bir dizini yok',
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
];

const meta: Meta<typeof ModuleInventory> = {
    title: 'Surface/Engineering/ModuleInventory',
    component: ModuleInventory,
    parameters: { layout: 'fullscreen' },
    args: {
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
                code: 'CORE-13',
                name: 'File / Media',
                moduleClass: 'core',
                version: '1.0.0',
                dependencies: ['CORE-02', 'CORE-04'],
                deterministicBaseline: 'required',
                aiPosture: 'automated_guarded',
            },
        ],
        graph: {
            nodes: ['Analytics', 'MenuCatalog', 'Publication'],
            edges: [
                {
                    from: 'Publication',
                    to: 'MenuCatalog',
                    evidencePath:
                        'app/Application/Publication/UseCase/BuildPublicationSnapshot.php',
                },
            ],
        },
        specs,
        unmappedContexts: ['Rating', 'Reference', 'Security', 'Team', 'Url'],
    },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-4)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
};

export default meta;

type Story = StoryObj<typeof ModuleInventory>;

export const Measured: Story = {};

/**
 * Hiçbir şey ölçülemediğinde ekran BOŞ değil, DÜRÜST olmalı: dört rozetin
 * hepsi "bilinmiyor" ve her biri sebebini yazıyor.
 */
export const NothingCouldBeMeasured: Story = {
    args: {
        specs: specs.map((spec) => ({
            ...spec,
            presence: 'unknown' as const,
            mapping: 'unknown',
            mappingNote: 'Eşleme kurulamadı; bu satır ölçülmedi',
            contexts: [],
            observation: { directories: [], routeFiles: [], tables: [], testFiles: 0 },
        })),
        unmappedContexts: [],
    },
};
