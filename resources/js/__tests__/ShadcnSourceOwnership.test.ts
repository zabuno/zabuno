import { describe, expect, it } from 'vitest';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';

/**
 * Encodes docs/03 ADR-L06: shadcn/ui is source-owned (copied into the
 * repo), never an installed npm package, and Radix is only ever used
 * behind an adapter for a genuinely missing accessible primitive — never
 * installed just because it "might be needed".
 */
const repoRoot = resolve(__dirname, '../../..');
const uiDir = resolve(repoRoot, 'resources/js/components/ui');
const jsRoot = resolve(repoRoot, 'resources/js');

function collectSourceFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = resolve(dir, entry.name);
    if (entry.isDirectory()) {
      return collectSourceFiles(entryPath);
    }
    return /\.tsx?$/.test(entry.name) ? [entryPath] : [];
  });
}

describe('shadcn/ui — source-owned secondary configuration', () => {
  it('keeps shadcn component source copied into the repo, not installed via npm', () => {
    expect(existsSync(uiDir)).toBe(true);
    const files = readdirSync(uiDir).filter((f) => f.endsWith('.tsx'));
    expect(files.length).toBeGreaterThan(0);
  });

  it('never declares a shadcn package as an npm dependency', () => {
    const packageJsonPath = resolve(repoRoot, 'package.json');
    expect(existsSync(packageJsonPath)).toBe(true);

    const pkg = JSON.parse(readFileSync(packageJsonPath, 'utf-8'));
    const deps = { ...pkg.dependencies, ...pkg.devDependencies };
    const shadcnDep = Object.keys(deps).find((name) => /shadcn/i.test(name));

    expect(shadcnDep).toBeUndefined();
  });

  it('only pulls in a Radix primitive behind an adapter, never bare', () => {
    const packageJsonPath = resolve(repoRoot, 'package.json');
    const pkg = JSON.parse(readFileSync(packageJsonPath, 'utf-8'));
    const deps = { ...pkg.dependencies, ...pkg.devDependencies };
    const radixDeps = Object.keys(deps).filter((name) => name.startsWith('@radix-ui/'));

    const adapterDir = resolve(repoRoot, 'resources/js/components/adapters');
    expect(existsSync(adapterDir)).toBe(true);

    const sourceFiles = collectSourceFiles(jsRoot);

    for (const radixDep of radixDeps) {
      const importingFiles = sourceFiles.filter((file) =>
        readFileSync(file, 'utf-8').includes(radixDep),
      );

      // Every declared Radix dependency must actually be consumed by the
      // source-owned shadcn wrapper — otherwise it is dead weight installed
      // "just because it might be needed".
      expect(importingFiles.length).toBeGreaterThan(0);

      // A raw import of a Radix primitive is only allowed inside the
      // source-owned shadcn component itself (resources/js/components/ui);
      // every other consumer must go through the adapter boundary instead
      // of reaching past it for the bare primitive.
      const rawImportsOutsideUi = importingFiles.filter(
        (file) => !file.startsWith(uiDir + sep),
      );

      expect(rawImportsOutsideUi).toEqual([]);
    }
  });
});
