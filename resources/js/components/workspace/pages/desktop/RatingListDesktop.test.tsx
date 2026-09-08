import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { RatingListDesktop } from './RatingListDesktop';
import type { RatingRow } from '../ratings/ratingPresentation';

/**
 * PUAN LİSTESİ — MASAÜSTÜ (`docs/151` §B).
 *
 * Donan üç şey:
 *
 * 1. **Sıralama gerçekten var mı** ve puanı OLMAYAN satır en sonda mı?
 *    İkincisi bir ürün kuralıdır: eşiği geçmemiş bir ürünü "en kötü" diye
 *    başa koymak, olmayan bir ölçümü bir yargıya çevirirdi.
 * 2. **Klavyeyle gezinme.**
 * 3. **Kalıcı bölme** seçimle değişiyor ve yanıt kutusunun taslağı satır
 *    değişince TAŞINMIYOR mu?
 */

function row(id: number, name: string, score: number | null, signalCount = 12): RatingRow {
    return {
        menuItemId: id,
        productId: 100 + id,
        productName: name,
        score,
        scaleMax: 5,
        signalCount,
        meetsDisplayThreshold: score !== null,
        computedAt: '2026-09-07T09:00:00Z',
        reply: null,
    };
}

const ROWS = [row(1, 'Adana', 4.4), row(2, 'Baklava', 2.1), row(3, 'Cacik', null, 3)];

function renderList(rows: RatingRow[] = ROWS) {
    const onReplySaved = vi.fn();

    render(
        <RatingListDesktop
            workspaceId={7}
            rows={rows}
            algorithmVersion="v3"
            onReplySaved={onReplySaved}
        />,
    );

    return { onReplySaved };
}

async function options() {
    return within(await screen.findByRole('listbox')).findAllByRole('option');
}

describe('RatingListDesktop', () => {
    it('varsayılan sıra EN DÜŞÜK puandır ve puanı olmayan satır en sondadır', async () => {
        renderList();

        const names = (await options()).map((option) => option.textContent ?? '');

        expect(names[0]).toContain('Baklava');
        expect(names[1]).toContain('Adana');
        // Eşiği geçmemiş ürün "en kötü" değildir; sunucunun söylediği tek
        // şey "henüz söyleyemem".
        expect(names[2]).toContain('Cacik');
    });

    it('sıralama düğmesi üç seçenek arasında döner', async () => {
        const user = userEvent.setup();
        renderList();

        await user.click(screen.getByRole('button', { name: /Sort/ }));
        expect(screen.getByRole('button', { name: 'Sort: Most votes' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /Sort/ }));
        expect(screen.getByRole('button', { name: 'Sort: Name' })).toBeInTheDocument();
    });

    it('listeye bir kez Tab ile girilir ve içinde oklarla gezilir', async () => {
        const user = userEvent.setup();
        renderList();

        const rows = await options();

        expect(rows[0]).toHaveAttribute('tabindex', '0');
        expect(rows[1]).toHaveAttribute('tabindex', '-1');

        rows[0]?.focus();
        await user.keyboard('{ArrowDown}');

        expect(rows[1]).toHaveFocus();
    });

    it('kalıcı bölme seçimle değişir ve yanıt kutusu taslağı taşımaz', async () => {
        const user = userEvent.setup();
        renderList();

        const pane = screen.getByRole('complementary', { name: 'Selected product' });
        const rows = await options();

        expect(within(pane).getByRole('heading')).toHaveTextContent('Baklava');

        const box = within(pane).getByRole('textbox');
        await user.type(box, 'Teşekkürler');

        await user.click(rows[1] as HTMLElement);

        // Bölme KALICIDIR: başka bir ürüne geçildiğinde önceki ürüne
        // yazılmış yarım cümle yeni ürünün kutusunda durmamalı — yanlış
        // ürüne yayınlanan bir yanıt, misafirin gördüğü menüde kalır.
        expect(within(pane).getByRole('heading')).toHaveTextContent('Adana');
        expect(within(pane).getByRole('textbox')).toHaveValue('');
    });

    it('eşik altındaki satırda sayım yine yazılır', async () => {
        renderList();

        const rows = await options();

        // Gizlenen şey PUANDIR — henüz güvenilmeyen türetilmiş değer. Kaç oy
        // geldiği bilinen bir ölçümdür ve "eşiğe ne kadar kaldı?" sorusunun
        // tek cevabıdır.
        expect(rows[2]).toHaveTextContent('Votes so far: 3');
    });
});
