import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * WORKSPACE_SECTION_REGISTRY_RED
 *
 * Structural/architecture contract for the workspace section registry
 * (zabuno-workspace-section-registry-v1 — page-local *.section.tsx descriptors + a single
 * WorkspaceSectionRegistry.tsx consumed by WorkspaceApp). These tests
 * inspect source files on disk via node:fs rather than importing the
 * registry module, so they compile and run before any registry or
 * descriptor file exists. Today they must fail RED solely because:
 *   - the ten page-local *.section.tsx descriptors do not exist,
 *   - resources/js/components/workspace/shell/WorkspaceSectionRegistry.tsx
 *     does not exist, and
 *   - WorkspaceApp.tsx still directly imports every page component and
 *     branches rendering per activeSection instead of consuming the
 *     registry.
 *
 * No production edits accompany this file; it is test-only and frozen.
 */

const THIS_DIR = dirname(fileURLToPath(import.meta.url));
const WORKSPACE_DIR = join(THIS_DIR, '..');
const PAGES_DIR = join(WORKSPACE_DIR, 'pages');
const REGISTRY_PATH = join(THIS_DIR, 'WorkspaceSectionRegistry.tsx');
const WORKSPACE_APP_PATH = join(WORKSPACE_DIR, 'WorkspaceApp.tsx');

const EXPECTED_SECTION_DESCRIPTOR_FILES = [
    'DashboardPage.section.tsx',
    'BrandPage.section.tsx',
    'LocationsPage.section.tsx',
    'MenuPage.section.tsx',
    'MediaPage.section.tsx',
    'PublicationPage.section.tsx',
    'AnalyticsPage.section.tsx',
    'TeamPage.section.tsx',
    'BillingPage.section.tsx',
    'LaunchReadinessPage.section.tsx',
];

const EXPECTED_AI_QUICK_ACTION_KEYS = ['dashboard', 'locations', 'menu', 'publication'];

function readIfExists(path: string): string | null {
    return existsSync(path) ? readFileSync(path, 'utf8') : null;
}

describe('WorkspaceSectionRegistry structural contract', () => {
    it('declares exactly the ten page-local *.section.tsx descriptor files', () => {
        const missing = EXPECTED_SECTION_DESCRIPTOR_FILES.filter(
            (fileName) => !existsSync(join(PAGES_DIR, fileName)),
        );

        expect(missing, `missing descriptor files: ${missing.join(', ')}`).toEqual([]);
    });

    it('loads descriptors via an eager import.meta.glob over *.section.tsx and imports no page component directly', () => {
        const registrySource = readIfExists(REGISTRY_PATH);

        expect(registrySource, `expected registry source at ${REGISTRY_PATH}`).not.toBeNull();

        const source = registrySource as string;

        expect(source).toMatch(/import\.meta\.glob\(\s*['"][^'"]*\.section\.tsx['"]/);
        expect(source).toMatch(/eager\s*:\s*true/);

        const directPageImportPattern =
            /from\s+['"]\.\.\/pages\/(DashboardPage|BrandPage|LocationsPage|MenuPage|MediaPage|PublicationPage|AnalyticsPage|TeamPage|BillingPage|LaunchReadinessPage)['"]/;

        expect(source).not.toMatch(directPageImportPattern);
    });

    it('descriptors declare unique keys/hashes/order/labelKey and exactly the four AI quick-action sections', () => {
        const descriptorSources = EXPECTED_SECTION_DESCRIPTOR_FILES.map((fileName) => ({
            fileName,
            source: readIfExists(join(PAGES_DIR, fileName)),
        }));

        for (const { fileName, source } of descriptorSources) {
            expect(source, `expected descriptor source for ${fileName}`).not.toBeNull();
        }

        const keys: string[] = [];
        const hashes: string[] = [];
        const orders: number[] = [];
        const aiQuickActionSections: string[] = [];

        for (const { fileName, source } of descriptorSources) {
            const text = source as string;

            const keyMatch = text.match(/key\s*:\s*['"]([^'"]+)['"]/);
            const hashMatch = text.match(/hash\s*:\s*['"](#[^'"]+)['"]/);
            const orderMatch = text.match(/order\s*:\s*(-?\d+)/);
            const labelKeyMatch = text.match(/labelKey\s*:\s*['"]([^'"]+)['"]/);

            expect(keyMatch, `${fileName} must declare a string key`).not.toBeNull();
            expect(hashMatch, `${fileName} must declare a #hash`).not.toBeNull();
            expect(orderMatch, `${fileName} must declare a numeric order`).not.toBeNull();
            expect(labelKeyMatch, `${fileName} must declare a labelKey`).not.toBeNull();

            if (keyMatch) keys.push(keyMatch[1]);
            if (hashMatch) hashes.push(hashMatch[1]);
            if (orderMatch) orders.push(Number(orderMatch[1]));

            const aiQuickActionMatch = text.match(/aiQuickAction\s*:\s*true/);
            if (aiQuickActionMatch && keyMatch) {
                aiQuickActionSections.push(keyMatch[1]);
            }
        }

        expect(new Set(keys).size).toBe(keys.length);
        expect(new Set(hashes).size).toBe(hashes.length);
        expect(new Set(orders).size, 'orders must be deterministic/unique').toBe(orders.length);

        expect(aiQuickActionSections.sort()).toEqual([...EXPECTED_AI_QUICK_ACTION_KEYS].sort());
    });

    it('WorkspaceApp imports no page directly, has no per-page activeSection render branches, and consumes registry helpers plus one active render adapter', () => {
        const appSource = readIfExists(WORKSPACE_APP_PATH);

        expect(appSource, `expected WorkspaceApp source at ${WORKSPACE_APP_PATH}`).not.toBeNull();

        const source = appSource as string;

        const directPageImportPattern =
            /from\s+['"]\.\/pages\/(DashboardPage|BrandPage|LocationsPage|MenuPage|MediaPage|PublicationPage|AnalyticsPage|TeamPage|BillingPage|LaunchReadinessPage)['"]/;

        expect(source).not.toMatch(directPageImportPattern);

        const perPageBranchPattern =
            /activeSection\s*===\s*['"](dashboard|brand|locations|menu|media|publication|analytics|team|billing|security)['"]/;

        expect(source).not.toMatch(perPageBranchPattern);

        expect(source).toMatch(/from\s+['"]\.\/shell\/WorkspaceSectionRegistry['"]/);

        // Exactly one registry-driven active-render invocation, formatting-tolerant
        // (allows whitespace/newlines between the call target and its args, and any
        // call-site identifier ending in Section/Active/Render/renderActiveSection-style
        // naming) rather than N per-page JSX branches.
        const activeRenderInvocationPattern =
            /(renderActiveSection|activeSection(?:Render|Component|Element)|renderSection)\s*\(/;
        const activeRenderMatches = source.match(
            new RegExp(activeRenderInvocationPattern.source, 'g'),
        );

        expect(
            activeRenderMatches,
            'expected exactly one registry-based active render invocation in WorkspaceApp',
        ).not.toBeNull();
        expect(activeRenderMatches?.length).toBe(1);
    });

    it('registry source sorts registrations deterministically, rejects duplicate/missing dashboard, and resolves unknown hashes to dashboard without a hardcoded page import list', () => {
        const registrySource = readIfExists(REGISTRY_PATH);

        expect(registrySource, `expected registry source at ${REGISTRY_PATH}`).not.toBeNull();

        const source = registrySource as string;

        // Deterministic order sorting: a .sort( call whose comparator references
        // the descriptor "order" field, formatting-tolerant across whitespace/newlines.
        const orderSortPattern = /\.sort\(\s*\([^)]*\)\s*=>[^;]*\border\b/s;

        expect(
            source,
            'expected registrations sorted deterministically by an "order" comparator',
        ).toMatch(orderSortPattern);

        expect(source).toMatch(/duplicate/i);
        expect(source).toMatch(/missing/i);

        // Explicit unknown-hash -> dashboard fallback: a resolver that falls back to
        // the literal 'dashboard' key/hash when no descriptor matches the given hash,
        // not merely incidental use of the word "dashboard" elsewhere in the file.
        const fallbackToDashboardPattern =
            /(unknown|resolve|fallback)[\s\S]{0,200}?['"]dashboard['"]/i;

        expect(
            source,
            'expected an explicit unknown-hash fallback resolving to "dashboard"',
        ).toMatch(fallbackToDashboardPattern);

        const hardcodedPageImportListPattern =
            /from\s+['"]\.\.\/pages\/(DashboardPage|BrandPage|LocationsPage|MenuPage|MediaPage|PublicationPage|AnalyticsPage|TeamPage|BillingPage|LaunchReadinessPage)['"]/;

        expect(source).not.toMatch(hardcodedPageImportListPattern);
    });
});
