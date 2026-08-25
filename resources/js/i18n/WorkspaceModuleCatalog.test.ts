import { describe, it, expect } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { t } from './workspace';

/**
 * RED - S1 workspace i18n modular catalog contract.
 *
 * resources/js/i18n/workspace.ts (legacy flat literal catalog) is being
 * replaced by an aggregator of the same path that automatically discovers
 * module catalogs under resources/js/i18n/workspace/ via import.meta.glob,
 * merges them, and preserves all 413 workspace.* key/value pairs (media.ts
 * intentionally replaces one legacy status key with eight status-specific
 * keys) and t() runtime/type behavior exactly.
 */

const WORKSPACE_DIR = path.dirname(fileURLToPath(import.meta.url));
const CATALOG_DIR = path.join(WORKSPACE_DIR, 'workspace');
const AGGREGATOR_FILE = path.join(WORKSPACE_DIR, 'workspace.ts');

const FROZEN_MODULE_FILENAMES = [
    'analytics.ts',
    'billing.ts',
    'brand-locations.ts',
    'dashboard.ts',
    'launch-readiness.ts',
    'media.ts',
    'menu.ts',
    'publication.ts',
    'shell.ts',
    'team.ts',
];

const FROZEN_LEGACY_KEY_COUNT = 413;

// Frozen from the current resources/js/i18n/workspace/ catalogs: sha256 of
// sorted "key=value" lines joined by "\n" over all 413 entries (media.ts
// intentionally replaces one legacy status key with eight status-specific
// keys, moving the catalog from 406 to 413 entries).
const FROZEN_LEGACY_NORMALIZED_SHA256 =
    '49d09ffd8e79e1b793227151e925e9a9b1a4cea758c4c5974227a6aa64255795';

function normalizedHash(entries: Record<string, string>): string {
    const sortedKeys = Object.keys(entries).sort();
    const normalized = sortedKeys.map((key) => `${key}=${entries[key]}`).join('\n');
    return createHash('sha256').update(normalized, 'utf8').digest('hex');
}

describe('workspace i18n modular catalog contract', () => {
    it('discovers exactly the ten frozen module catalog filenames under resources/js/i18n/workspace/', () => {
        const actualFilenames = readdirSync(CATALOG_DIR)
            .filter((name) => name.endsWith('.ts'))
            .sort();

        expect(actualFilenames).toEqual(FROZEN_MODULE_FILENAMES);
    });

    it('aggregates via automatic eager glob discovery with no static per-module import list and no inline translation literals', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/import\.meta\.glob/);

        for (const filename of FROZEN_MODULE_FILENAMES) {
            const moduleName = filename.replace(/\.ts$/, '');
            const staticImportPattern = new RegExp(
                `from\\s+["'\`]\\./workspace/${moduleName}["'\`]`,
            );
            expect(aggregatorSource).not.toMatch(staticImportPattern);
        }

        expect(aggregatorSource).not.toMatch(/['"]workspace\.[a-zA-Z]+['"]\s*:/);
    });

    it('rejects a duplicate key across catalogs with a deterministic error naming the key and source module', () => {
        const aggregatorSource = readFileSync(AGGREGATOR_FILE, 'utf8');

        expect(aggregatorSource).toMatch(/throw/);
        expect(aggregatorSource).toMatch(/duplicate/i);
    });

    it('composes exactly 413 workspace translation entries matching the frozen normalized legacy SHA-256', async () => {
        const workspaceModule: typeof import('./workspace') = await import('./workspace');
        const composed = workspaceModule.workspaceTranslations as Record<string, string>;

        expect(composed).toBeTruthy();
        expect(Object.keys(composed)).toHaveLength(FROZEN_LEGACY_KEY_COUNT);
        expect(normalizedHash(composed)).toBe(FROZEN_LEGACY_NORMALIZED_SHA256);
    });

    it('t() preserves representative values, supports {var} interpolation, rejects an unknown literal at compile time, and falls back to it at runtime', () => {
        expect(t('workspace.loading')).toBe('Loading your workspace…');
        expect(t('workspace.billing.currentPlan.version')).toBe('Version {version}');
        expect(t('workspace.billing.currentPlan.version', { version: '7' })).toBe('Version 7');
        expect(
            t('workspace.billing.iyzicoSandbox.amount', { amount: '120', currency: 'TRY' }),
        ).toBe('Amount 120 TRY');

        function assertRejectsUnknownLiteral(): void {
            // @ts-expect-error only literal WorkspaceTranslationKey values are accepted by t()
            t('workspace.__unknown_literal_not_in_catalog__');
        }
        void assertRejectsUnknownLiteral;

        const untypedT = t as (key: string, vars?: Record<string, string>) => string;
        const unknownKey = 'workspace.__unknown_literal_not_in_catalog__';
        expect(untypedT(unknownKey)).toBe(unknownKey);
    });
});
