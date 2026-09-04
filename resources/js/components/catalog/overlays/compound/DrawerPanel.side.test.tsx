import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { DrawerPanel } from './DrawerPanel';

/**
 * SHELL-DRAWER-SIDE-01 — FF-115, sahibin bildirimi (2026-09-04):
 * "sidebar, sağdan değil, soldan açılmalı. tüm sayfalarda. shell standardı
 * bu olsun."
 *
 * Kural keyfi değil: masaüstünde kenar çubuğu SOLDA duruyor ve onu açan
 * düğme de solda. Telefonda aynı menünün sağdan girmesi, kullanıcının
 * bastığı yerle açılan yerin ters olması demek — parmak solda, panel sağda.
 * Bu, aynı ürünün iki farklı zihinsel haritası olur.
 */
describe('DrawerPanel — hangi kenardan gelir', () => {
    it('varsayılan olarak SOLDAN gelir', () => {
        render(
            <DrawerPanel open onClose={() => {}} title="Restaurant admin">
                Nav
            </DrawerPanel>,
        );

        const dialog = screen.getByRole('dialog');
        expect(dialog.className).toMatch(/(^|\s)left-0(\s|$)/);
        expect(dialog.className).not.toMatch(/(^|\s)right-0(\s|$)/);
    });

    it('denetçi paneli AÇIKÇA sağdan gelebilir', () => {
        /*
            Gezinme ile İNCELEME farklı işlerdir. Soldaki listeden bir öğe
            seçip ayrıntısını açmak, listeyi ekranda tutmayı gerektirir;
            panel sağdan girerse okuma yönü korunur. Bu istisna KOD İÇİNDE
            açıkça yazılır, varsayılan olarak sızmaz.
        */
        render(
            <DrawerPanel open onClose={() => {}} title="Photo detail" position="right">
                Detail
            </DrawerPanel>,
        );

        expect(screen.getByRole('dialog').className).toMatch(/(^|\s)right-0(\s|$)/);
    });
});
