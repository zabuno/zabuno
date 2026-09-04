import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { DashboardSetupJourney } from './DashboardSetupJourney';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * AEP teslim paketine UYUM — `Restoran Paneli v2.dc.html` Home bölümü ve
 * `design_handoff_restoran_paneli/DESIGN_SPEC.md` §2.
 *
 * Sahibin kararı: paketteki çalışan ekran kanoniktir. Bu dosya o ekranın
 * Home'a ait iki kararını depoda ZORLAYICI hâle getirir:
 *
 *   1. "Şimdi" kartı MARKA ZEMİNLİDİR. Depodaki hâl nötr yüzey üstünde
 *      ince bir sol şeritti; ekranın tek eylemi, etrafındaki beş kurulum
 *      satırıyla aynı görsel ağırlıktaydı ve "önce neye bakayım" sorusu
 *      cevapsız kalıyordu. Marka sarısı burada küçük metin değil, tam bir
 *      ZEMİN — külliyatın "marka yapısal vurgudur" kuralının kendisi.
 *
 *   2. Kurulum adımı BİTTİĞİNİ üç işaretle söyler: dolu onay dairesi,
 *      soluk metin ve üstü çizili etiket. Renk tek başına asla yeterli
 *      değildir (WCAG 1.4.1); üstü çizgi, renk körü bir kullanıcı için de,
 *      yüksek kontrast modundaki biri için de okunur.
 */
const TREE: DashboardMenuTree = {
    id: 42,
    workspaceId: 7,
    locationId: 3,
    name: 'Ana Menü',
    state: 'draft',
    categories: [{ id: 5, menuId: 42, name: 'Kebaplar', position: 1, menuItems: [] }],
};

const BRAND = { id: 1, name: 'Zeytin' } as never;
const LOCATION = { id: 3, display_name: 'Kadıköy' } as never;

describe('Home "şimdi" kartı — AEP uyumu (AEP_HOME_NOW_RED)', () => {
    it('kart marka zeminini ve o zeminin metin rengini taşır', () => {
        render(
            <DashboardSetupJourney
                brand={null}
                location={null}
                dashboardMenuTree={null}
                onNavigateToSection={() => {}}
            />,
        );

        const now = screen.getByRole('region', { name: 'What to do now' });

        expect(now.className).toContain('bg-action');
        expect(now.className).toContain('text-action-fg');
        // Nötr kart grameri geride kaldı: marka zemin üstünde kenarlık gürültüdür.
        expect(now.className).not.toContain('bg-surface');
    });

    /*
        TEK eylem, ama BÜYÜK eylem. Referansta birincil düğme 52 piksel
        yüksekliğinde ve mürekkep zeminli; sarı zemin üstünde sarı bir
        düğme görünmez olurdu.
    */
    it('tek eylem düğmesi marka zemininin üstünde mürekkep dolgusuyla ayrışır', () => {
        render(
            <DashboardSetupJourney
                brand={null}
                location={null}
                dashboardMenuTree={null}
                onNavigateToSection={() => {}}
            />,
        );

        const now = screen.getByRole('region', { name: 'What to do now' });
        const buttons = within(now).getAllByRole('button');

        // `docs/101` A1: ekranda TEK "şimdi". Bu sayı bir sözleşmedir.
        expect(buttons).toHaveLength(1);
        expect(buttons[0].className).toContain('bg-[var(--color-fg)]');
        expect(buttons[0].className).toContain('text-[var(--color-surface)]');
    });
});

describe('Home kurulum kartı — AEP uyumu (AEP_HOME_SETUP_RED)', () => {
    it('biten adım hem soluk hem üstü çizili yazılır', () => {
        render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={TREE}
                onNavigateToSection={() => {}}
            />,
        );

        const region = screen.getByRole('region', { name: 'Dashboard Setup' });
        const doneLabel = within(region).getByText('Brand');

        expect(doneLabel.className).toContain('line-through');
        expect(doneLabel.className).toContain('text-fg-secondary');
    });

    it('bitmemiş adım ne soluklaşır ne üstü çizilir', () => {
        render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={TREE}
                onNavigateToSection={() => {}}
            />,
        );

        const region = screen.getByRole('region', { name: 'Dashboard Setup' });
        const nextLabel = within(region).getByText('Menu');

        expect(nextLabel.className).not.toContain('line-through');
        expect(nextLabel.className).toContain('font-bold');
    });

    /*
        İLERLEME ÇUBUĞU 6 PİKSEL (`DESIGN_SPEC` §2 "Kurulum kartı").
        Dört piksellik çubuk uzaktan bir çizgiden ayırt edilemiyordu;
        ilerleme, bakmadan anlaşılması gereken tek şeydir.
    */
    it('ilerleme çubuğu referans kalınlığındadır', () => {
        const { container } = render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={TREE}
                onNavigateToSection={() => {}}
            />,
        );

        const track = container.querySelector('.rounded-pill.overflow-hidden');

        expect(track).not.toBeNull();
        expect((track as HTMLElement).className).toContain('h-[0.375rem]');
    });
});
