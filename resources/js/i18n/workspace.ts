const modules = import.meta.glob<{ default?: never; [key: string]: unknown }>('./workspace/*.ts', {
    eager: true,
});

const en: Record<string, string> = {};
const seenSourceByKey = new Map<string, string>();

for (const modulePath of Object.keys(modules).sort()) {
    const moduleExports = modules[modulePath] as Record<string, Record<string, string>>;

    for (const catalog of Object.values(moduleExports)) {
        for (const [key, value] of Object.entries(catalog)) {
            const existingSource = seenSourceByKey.get(key);

            if (existingSource !== undefined) {
                throw new Error(
                    `Duplicate workspace translation key "${key}" found in "${modulePath}"; already defined in "${existingSource}".`,
                );
            }

            seenSourceByKey.set(key, modulePath);
            en[key] = value;
        }
    }
}

export const workspaceTranslations = en;

// Each catalog module under ./workspace/*.ts augments this interface with its
// own keys via `declare module '../workspace' { interface
// WorkspaceTranslationCatalog extends Record<...> {} }`. TypeScript's
// declaration merging composes the literal key union here without this file
// ever naming a module — adding a new catalog needs no edit to this file.
// eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging target
export interface WorkspaceTranslationCatalog {}

export type WorkspaceTranslationKey = keyof WorkspaceTranslationCatalog;

export function t(key: WorkspaceTranslationKey, vars?: Record<string, string>): string {
    const template: string = en[key] ?? key;

    if (!vars) {
        return template;
    }

    return Object.entries(vars).reduce<string>(
        (result, [name, value]) => result.replaceAll(`{${name}}`, value),
        template,
    );
}
