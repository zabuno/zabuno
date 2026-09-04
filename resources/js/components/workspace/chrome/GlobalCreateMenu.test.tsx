import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GlobalCreateMenu, type GlobalCreateTarget } from './GlobalCreateMenu';

/**
 * Global oluştur menüsü — `docs/64`.
 */

/*
    Kaynak kapısı yorumları okumaz: bir kuralın gerekçesini yazmak için
    yasaklı sınıfın adını anmak gerekir.
*/
const SOURCE = readFileSync(
    path.resolve(path.dirname(fileURLToPath(import.meta.url)), 'GlobalCreateMenu.tsx'),
    'utf8',
)
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '');
const TARGETS: GlobalCreateTarget[] = [
    {
        key: 'location',
        labelKey: 'workspace.create.location',
        destination: 'locations/new',
        available: true,
    },
    { key: 'menu', labelKey: 'workspace.create.menu', destination: 'menu', available: false },
];

describe('global oluştur menüsü', () => {
    /**
     * Ön koşulu sağlanmayan hedef LİSTELENMEZ. Şubesiz bir çalışma alanında
     * "Menü" maddesi kullanıcıyı çıkışsız bir ekrana götürürdü.
     */
    it('yalnız ön koşulu sağlanan hedefleri gösterir', async () => {
        const user = userEvent.setup();
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={vi.fn()} />);

        await user.click(screen.getByRole('button', { name: 'Create' }));

        expect(await screen.findByRole('menuitem', { name: 'Location' })).toBeInTheDocument();
        expect(screen.queryByRole('menuitem', { name: 'Menu' })).toBeNull();
    });

    it('seçilen hedefe götürür', async () => {
        const user = userEvent.setup();
        const onNavigate = vi.fn();
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={onNavigate} />);

        await user.click(screen.getByRole('button', { name: 'Create' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Location' }));

        // Bölüm İÇİ yol: liste değil, formun açık olduğu adres.
        expect(onNavigate).toHaveBeenCalledWith('locations/new');
    });

    /**
     * Boş bir "Oluştur" düğmesi, tıklandığında hiçbir şey sunmayan bir
     * vaattir. Hiçbir hedef uygun değilse menü hiç çizilmez.
     */
    it('uygun hedef yoksa hiç çizilmez', () => {
        const { container } = render(
            <GlobalCreateMenu
                targets={TARGETS.map((target) => ({ ...target, available: false }))}
                onNavigate={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    /**
     * TETİKLEYİCİ ARTI İŞARETİ TAŞIR — AEP teslim paketi ("Restoran Paneli
     * v2") üst çubuktaki "Oluştur" düğmesini ikon + söz olarak çiziyor.
     *
     * Sebep dar ekranda ortaya çıkıyor: sözcük sığmadığında geriye anlamını
     * taşıyan bir işaret kalmalı. İkon `aria-hidden` olduğu için düğmenin
     * erişilebilir adı DEĞİŞMEZ — ekran okuyucu yine "Create" duyar, bir
     * "artı" fazlası değil.
     */
    it('tetikleyicide artı işareti taşır ve erişilebilir adı değişmez', () => {
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={vi.fn()} />);

        const trigger = screen.getByRole('button', { name: 'Create' });

        expect(trigger.querySelector('svg')).not.toBeNull();
    });

    /**
     * HER MADDE KENDİ İŞARETİNİ TAŞIR. Dört maddelik bir listede sözcükler
     * birbirine benzer ("Menü", "Şube"); ikon, listeyi okumadan hedefi
     * bulmayı sağlayan tek işarettir. Referans paket de menüyü böyle
     * çiziyor.
     */
    it('menü maddeleri kendi ikonlarını taşır', async () => {
        const user = userEvent.setup();
        render(<GlobalCreateMenu targets={TARGETS} onNavigate={vi.fn()} />);

        await user.click(screen.getByRole('button', { name: 'Create' }));

        const item = await screen.findByRole('menuitem', { name: 'Location' });

        expect(item.querySelector('svg')).not.toBeNull();
    });

    /**
     * Tanımadığı bir hedef anahtarı için ikon UYDURMAZ: yanlış bir işaret,
     * işaretsiz bir satırdan daha kötüdür.
     */
    it('bilinmeyen hedef için ikon uydurmaz', async () => {
        const user = userEvent.setup();
        render(
            <GlobalCreateMenu
                targets={[
                    {
                        key: 'unknown-thing',
                        labelKey: 'workspace.create.location',
                        destination: 'somewhere',
                        available: true,
                    },
                ]}
                onNavigate={vi.fn()}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Create' }));

        const item = await screen.findByRole('menuitem', { name: 'Location' });

        expect(item.querySelector('svg')).toBeNull();
    });

    /**
     * Ağırlık ölçeği 400/500/700; ham renk, ham piksel ve sabit değerli hap
     * sınıfı yazılmaz.
     */
    it('ham değer ve 600 ağırlık yazmaz', () => {
        expect(SOURCE).not.toMatch(/\bfont-semibold\b/);
        expect(SOURCE).not.toMatch(/\brounded-full\b/);
        expect(SOURCE).not.toMatch(/\buppercase\b/);
        expect(SOURCE).not.toMatch(/#[0-9a-fA-F]{3,8}\b/);
        expect(SOURCE).not.toMatch(
            /\b(?:p|px|py|pt|pb|ps|pe|m|mx|my|mt|mb|w|h|min-h|max-w|gap|rounded|size)-\[[^\]]*\d+px/,
        );
    });
});
