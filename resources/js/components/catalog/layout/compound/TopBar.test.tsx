import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TopBar } from './TopBar';

/*
    docs/06 §11 AEP mirası — kaynak artık İKİ katman (FF-131).

    Marka sarısı ve üstündeki mürekkep teslim paketinin kendi dosyasında
    (`resources/css/aep/tokens/colors.css`) tanımlı; `app.css` onun üstüne
    takma ad koyuyor. Yalnız `app.css` okuyan bir ölçüm, marka renginin
    tanımını hiç göremez ve "tek bir tanım var" iddiasını boş bir dosyada
    doğrulamış olurdu.
*/
const repoCss = readFileSync(resolve(process.cwd(), 'resources/css/app.css'), 'utf8');
const aepCss = readFileSync(resolve(process.cwd(), 'resources/css/aep/tokens/colors.css'), 'utf8');
const appCss = [aepCss, repoCss].join('\n');

/** Test-local WCAG 2.1 contrast ratio helper (relative luminance) — docs/06 §11 / §8 AA. */
function hexToRgb(hex: string): [number, number, number] {
    const normalized = hex.replace('#', '');
    const value =
        normalized.length === 3
            ? normalized
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : normalized;
    const int = parseInt(value, 16);
    return [(int >> 16) & 255, (int >> 8) & 255, int & 255];
}

function relativeLuminance([r, g, b]: [number, number, number]): number {
    const [rs, gs, bs] = [r, g, b].map((c) => {
        const s = c / 255;
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

function contrastRatio(hexA: string, hexB: string): number {
    const lA = relativeLuminance(hexToRgb(hexA));
    const lB = relativeLuminance(hexToRgb(hexB));
    const [lighter, darker] = lA > lB ? [lA, lB] : [lB, lA];
    return (lighter + 0.05) / (darker + 0.05);
}

describe('TopBar', () => {
    it('renders the brand', () => {
        render(<TopBar brand={{ name: 'Zabuno' }} />);
        expect(screen.getByText('Zabuno')).toBeInTheDocument();
    });

    it('omits the mobile menu toggle when onToggleMenu is not given', () => {
        render(<TopBar brand={{ name: 'Zabuno' }} />);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('renders an Open menu toggle that calls onToggleMenu', () => {
        const onToggleMenu = () => {};
        render(<TopBar brand={{ name: 'Zabuno' }} onToggleMenu={onToggleMenu} />);
        expect(screen.getByRole('button', { name: 'Open menu' })).toBeInTheDocument();
    });

    it('labels the toggle Close menu when menuOpen is true', () => {
        render(<TopBar brand={{ name: 'Zabuno' }} onToggleMenu={() => {}} menuOpen />);
        expect(screen.getByRole('button', { name: 'Close menu' })).toBeInTheDocument();
    });

    it('renders the end slot content', () => {
        render(<TopBar brand={{ name: 'Zabuno' }} end={<span>owner@example.com</span>} />);
        expect(screen.getByText('owner@example.com')).toBeInTheDocument();
    });

    it('uses the semantic surface-glass class and no literal white/gray-900 shell background', () => {
        const { container } = render(<TopBar brand={{ name: 'Zabuno' }} />);
        const header = container.querySelector('header');
        expect(header?.className ?? '').toMatch(/(?:^|\s)surface-glass(?:\s|$)/);
        expect(header?.className ?? '').not.toMatch(/(?:^|\s)bg-white(?:\s|$)/);
        expect(header?.className ?? '').not.toMatch(/dark:bg-gray-900(?:\s|$)/);
    });

    /**
     * Başlık çubuğunun bir TABAN yüksekliği vardır — teslim paketinin
     * `DESIGN_SPEC.md` §1 "Üst çubuk": `min-height: 64px`.
     *
     * Taban olmadan çubuk içeriğine göre büzülüyordu: komut alanı olmayan bir
     * ekranda (giriş, hata sayfası, henüz çalışma alanı seçilmemiş hâl) yalnız
     * marka satırı kalıyor ve çubuk ince gri bir şeride dönüşüyordu. Aynı
     * uygulamada sayfadan sayfaya farklı kalınlıkta bir başlık, kullanıcıya
     * "başka bir yere geldim" dedirtir.
     *
     * Rakam elle yazılmaz, TÜRETİLİR: çubuk bir kontrol boyu artı iki yanında
     * birer 8pt nefestir. Yoğunluk ayarı kontrol boyunu değiştirdiğinde çubuk
     * da onunla birlikte değişir; sabit bir 64px, ferah moddaki 52px'lik komut
     * alanını kırpardı.
     */
    it('çubuğun taban yüksekliği kontrol boyundan türetilir', () => {
        const { container } = render(<TopBar brand={{ name: 'Zabuno' }} />);
        const header = container.querySelector('header');

        expect(header?.className ?? '').toMatch(/min-h-\[calc\(var\(--control-height\)/);
    });
});

describe('app.css token contract (docs/06 §11 AEP mirası)', () => {
    it('defines exactly one brand primitive as #ffb900', () => {
        // İlkel palet artık AEP dosyasında `--aep-yellow-500` adıyla durur;
        // `--color-brand-500` ona bağlanan takma addır. Donan şey isim değil,
        // marka renginin TEK bir yerde tanımlanmasıdır.
        const matches = appCss.match(/--aep-yellow-500:\s*#FFB900/gi) ?? [];
        expect(matches).toHaveLength(1);
    });

    it('defines semantic action/surface/text aliases', () => {
        expect(appCss).toMatch(/--color-action-primary-bg:/);
        expect(appCss).toMatch(/--color-action-primary-fg:/);
        expect(appCss).toMatch(/--color-surface:/);
        expect(appCss).toMatch(/--color-text-secondary:/);
    });

    it('defines a Roboto-first --font-sans stack', () => {
        expect(appCss).toMatch(/--font-sans:\s*['"]?Roboto/);
    });

    it('defines light (:root) and dark (.dark) values for the semantic aliases', () => {
        // Blok YAPISI deponun kendi dosyasında ölçülür: AEP katmanı da
        // `:root` açar ve birleşik metinde ilk eşleşme onunki olurdu.
        const rootBlock = repoCss.match(/:root\s*{([^}]*)}/s)?.[1] ?? '';
        const darkBlock = repoCss.match(/\.dark\s*{([^}]*)}/s)?.[1] ?? '';
        expect(rootBlock).toMatch(/--color-action-primary-bg:/);
        expect(rootBlock).toMatch(/--color-text-secondary:/);
        expect(darkBlock).toMatch(/--color-action-primary-bg:/);
        expect(darkBlock).toMatch(/--color-text-secondary:/);
    });

    it('defines a forced-colors media fallback', () => {
        expect(appCss).toMatch(/@media\s*\(forced-colors:\s*active\)/);
    });

    it('defines a safe .surface-glass class with a WebKit-safe @supports fallback', () => {
        expect(appCss).toMatch(/\.surface-glass\s*{/);
        // Positive condition must cover unprefixed OR -webkit- prefixed backdrop-filter,
        // so Safari/WebKit (prefixed-only support) matches the glass block.
        expect(appCss).toMatch(
            /@supports\s*\(\s*backdrop-filter:\s*blur\(1px\)\s*\)\s*or\s*\(\s*-webkit-backdrop-filter:\s*blur\(1px\)\s*\)/,
        );
        // The solid fallback must be a mirrored negative of that same OR condition —
        // not just `not (backdrop-filter: ...)`, which would also match WebKit's
        // prefixed-only support and apply both blocks at once.
        expect(appCss).toMatch(
            /@supports\s+not\s*\(\s*\(\s*backdrop-filter:\s*blur\(1px\)\s*\)\s*or\s*\(\s*-webkit-backdrop-filter:\s*blur\(1px\)\s*\)\s*\)/,
        );
    });

    it('defines a fluid clamp() spacing token --space-fluid-md', () => {
        expect(appCss).toMatch(/--space-fluid-md:\s*clamp\(/);
    });

    it('pairs an explicit dark foreground with #ffb900 that meets 4.5:1 contrast', () => {
        const fgMatch = appCss.match(/--aep-on-accent-primary:\s*var\(--aep-(ink-950)\)/)
            ? ['', '#080616']
            : appCss.match(/--color-action-primary-fg:\s*(#[0-9a-fA-F]{3,6})/);
        expect(fgMatch).not.toBeNull();
        const fgHex = fgMatch?.[1] ?? '#ffffff';
        expect(contrastRatio(fgHex, '#ffb900')).toBeGreaterThanOrEqual(4.5);
    });

    it('never assigns #ffb900 to a *-fg or text token', () => {
        expect(appCss).not.toMatch(/--[\w-]*(?:-fg|text)[\w-]*:\s*#ffb900/i);
    });
});
