import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

import { TeamRoleGuide } from './TeamRoleGuide';

/**
 * "ROLLER NE YAPABİLİR?" — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Takım"`; cümleler `docs/109` §6.4).
 *
 * NEDEN KIRMIZI: bu kart yok. Takım ekranı rolün ne yapabildiğini yalnız
 * DAVET ALANININ ALTINDA, seçili rol için tek satır olarak söylüyordu. Yani
 * sahip "Yönetici mi Editör mü vereyim?" diye sorduğunda ikisini yan yana
 * göremiyor, seçeneği değiştirip cümleyi tekrar okumak zorunda kalıyordu.
 *
 * MUTFAK ARTIK SAHTE DEĞİL. Bir önceki paket kaynağın dördüncü rolünü
 * ("Mutfak") bilerek çizmedi: depoda ne `MembershipRole::Kitchen` ne de dar
 * bir alerjen/stok izni vardı, yani kartta yazsaydı sahibe hiç kimseye
 * veremeyeceği bir yetkiyi vaat etmiş olurdu. O eksik bu pakette kapandı —
 * rol, izin matrisi ve sunucu kapısıyla birlikte GERÇEK. Kart da onu artık
 * kaynağın kendi cümlesiyle anlatır.
 *
 * `member` ise SAHTE DEĞİL, ESKİ: yalnız o rolü fiilen taşıyan biri varsa
 * çizilir. Listede durduğu hâlde kartta açıklanmayan bir rol, satırdaki
 * "member" kelimesini açıklanmamış bırakırdı.
 */
afterEach(() => {
    cleanup();
});

describe('TeamRoleGuide — yalnız gerçek roller', () => {
    it('üç kanonik rolün cümlesini birebir yazar', () => {
        render(<TeamRoleGuide roles={['owner', 'manager', 'editor']} />);

        expect(screen.getByText('Owner')).toBeInTheDocument();
        expect(screen.getByText('Everything: billing, team, publishing.')).toBeInTheDocument();

        expect(screen.getByText('Manager')).toBeInTheDocument();
        expect(
            screen.getByText('Menu, QR codes, publishing. Cannot touch billing.'),
        ).toBeInTheDocument();

        expect(screen.getByText('Editor')).toBeInTheDocument();
        expect(
            screen.getByText('Products, prices and photos. Cannot publish.'),
        ).toBeInTheDocument();
    });

    /**
     * Kaynağın dördüncü rolü, birebir cümlesiyle: *"Mutfak — Alerjen ve
     * 'bugün bitti'. Başka bir şey görmez."* (`docs/109` §6.4).
     *
     * Cümlenin ikinci yarısı en az birincisi kadar önemlidir: sahip, aşçıya
     * bu rolü verirken fiyatların ve faturanın da açılıp açılmadığını burada
     * öğrenir. "Alerjen ve bitti" tek başına, geri kalanı hakkında hiçbir şey
     * söylemezdi.
     */
    it('kaynağın "Mutfak" cümlesini birebir yazar', () => {
        render(<TeamRoleGuide roles={['owner', 'manager', 'editor', 'kitchen']} />);

        expect(screen.getByText('Kitchen')).toBeInTheDocument();
        expect(
            screen.getByText('Allergens and “sold out today”. Sees nothing else.'),
        ).toBeInTheDocument();
    });

    /** Kart yalnız KENDİSİNE VERİLEN rolleri anlatır; listeyi kendisi kurmaz. */
    it('istenmeyen rolü uydurmaz', () => {
        render(<TeamRoleGuide roles={['owner', 'manager', 'editor']} />);

        expect(screen.queryByText('Kitchen')).not.toBeInTheDocument();
    });

    it('eski salt okunur rol yalnız fiilen kullanılıyorsa açıklanır', () => {
        const { rerender } = render(<TeamRoleGuide roles={['owner', 'editor']} />);

        expect(screen.queryByText('Member')).not.toBeInTheDocument();

        rerender(<TeamRoleGuide roles={['owner', 'editor', 'member']} />);

        expect(screen.getByText('Member')).toBeInTheDocument();
        expect(screen.getByText(/Read-only/)).toBeInTheDocument();
    });

    it('kartın başlığı sorunun kendisidir', () => {
        render(<TeamRoleGuide roles={['owner']} />);

        expect(screen.getByRole('heading', { name: 'What can each role do?' })).toBeInTheDocument();
    });

    /** Jeton kökünü atlayan sınıflar bu depoda yasaktır (`docs/36` §5). */
    it('jeton kökünü atlayan sınıf taşımaz', () => {
        const { container } = render(
            <TeamRoleGuide roles={['owner', 'manager', 'editor', 'kitchen', 'member']} />,
        );

        const markup = container.innerHTML;

        expect(markup).not.toMatch(/\bfont-semibold\b/);
        expect(markup).not.toMatch(/\brounded-full\b/);
        expect(markup).not.toMatch(
            /\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/,
        );
        expect(markup).not.toMatch(/\b(?:ml|mr|pl|pr)-\d/);
        expect(markup).not.toMatch(/\b(?:sm|md|lg|xl|2xl):/);
    });
});
