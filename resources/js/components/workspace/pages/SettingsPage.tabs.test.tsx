import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { SettingsPage } from './SettingsPage';

/**
 * SEKMELER BÖLÜMLÜ BİR KONTROLDÜR — teslim paketinin düzeni (FF-131).
 *
 * Önceki hâlde sekmeler serbest duran, her biri kendi kenarlığını taşıyan
 * düğmelerdi. Üç ayrı kenarlıklı düğme yan yana durunca "üç eylem" gibi
 * okunuyordu; oysa bunlar birbirini DIŞLAYAN seçeneklerdir — biri seçilince
 * diğerleri kapanır. Kenarlık her birine ayrı ayrı verildiğinde bu ilişki
 * kayboluyordu.
 *
 * Paketin düzeni tek bir kutu: kenarlık DIŞARIDA, seçili olan içeride
 * dolguyla işaretli. Bu, "aynı ailenin üyeleri" demenin görsel yoludur ve
 * ekran okuyucudaki `tablist` semantiğiyle de örtüşür — göz ile kulak aynı
 * şeyi duyar.
 */
describe('SettingsPage — sekme grameri', () => {
    function renderPage() {
        return render(
            <SettingsPage
                workspaceId={1}
                userName="Mehmet"
                brand={null}
                onSaved={vi.fn()}
                activeTab="brand"
                onSelectTab={vi.fn()}
            />,
        );
    }

    it('sekmeler tek bir kenarlıklı kutunun içinde durur', () => {
        renderPage();

        const list = screen.getByRole('tablist');

        // Kutunun kendisi kenarlıklı ve dolgulu.
        expect(list.className).toMatch(/\bborder\b/);
        expect(list.className).toMatch(/rounded-\[var\(--radius-lg\)\]/);

        // Sekmelerin KENDİ kenarlığı yok: aile sınırı dışarıda.
        for (const tab of screen.getAllByRole('tab')) {
            expect(tab.className).not.toMatch(/(?:^|\s)border(?:-|\s|$)/);
        }
    });
});
