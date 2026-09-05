import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, waitFor } from '@testing-library/react';

import { SECTION_DESCRIPTORS, renderActiveSection } from './WorkspaceSectionRegistry';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

/**
 * "BAŞKA BİR ŞEY GÖRMEZ" — kaynağın kendi cümlesi (`panel.dc.html`,
 * `data-screen-label="Takım"`; `docs/109` §6.4).
 *
 * NEDEN KIRMIZI: bugün Panom, Şubeler ve Medya kayıtlarında `permission`
 * alanı HİÇ YOK, yani izin listesi ne olursa olsun kenar çubuğunda
 * çiziliyorlar. Mutfak rolündeki bir aşçı, açtığı her birinde ya boş ya
 * 403 dolu bir ekran bulurdu — `docs/98` FF-74'ün ("Editor 403 görmez")
 * tam tersi.
 *
 * MÜŞTERİ YOLCULUĞU. Aşçı Hasan telefondan girer. Kenar çubuğunda tek bir
 * hedef vardır: Menüler. Ölçüm, karekod, fatura, şube ve medya orada
 * DEĞİLDİR — ve olmadıkları için Hasan onları arayıp bulamadığı bir
 * yetkiyle uğraşmaz.
 *
 * ═══ ADRESE ELLE GİRMEK DE BİR YOLDUR ═══
 *
 * Kenar çubuğundan kaldırmak yetmez: uygulama, tanımadığı adresi Panom'a
 * düşürüyor ve giriş sonrası varış noktası da orası. Görmediği bir ekranın
 * üstüne düşen kullanıcı, boş bir sayfada mahsur kalır. O yüzden kayıt,
 * izni olmayan bölüm istendiğinde kullanıcıyı GERÇEKTEN görebildiği ilk
 * bölüme taşır.
 */
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

/** `App\Domain\Authorization\RolePermissions::for(MembershipRole::Kitchen)`. */
const KITCHEN_PERMISSIONS = [
    'workspace.view',
    'menu.view',
    'menu.allergens.manage',
    'menu.stock.manage',
];

/** Bugünkü editör izin listesi — Mutfak'ın yanında bir kontrol grubudur. */
const EDITOR_PERMISSIONS = [
    'workspace.view',
    'menu.view',
    'menu.manage',
    'menu.allergens.manage',
    'menu.stock.manage',
    'qr.view',
    'analytics.view',
    'media.manage',
    'media.download_original',
];

/*
    Süzme kuralı WorkspaceApp'in kuralının AYNISIDIR: `permission` tanımsızsa
    bölüm herkese açıktır, tanımlıysa izin listesinde olmalıdır. Burada
    tekrar edilmesinin sebebi, kayıttaki METADATA'nın o kurala göre doğru
    olup olmadığını sınamaktır — ikinci bir bölüm listesi kurmak değil.
*/
function sidebarKeysFor(permissions: string[]): string[] {
    return SECTION_DESCRIPTORS.filter(
        (descriptor) =>
            descriptor.group !== undefined &&
            (descriptor.permission === undefined || permissions.includes(descriptor.permission)),
    ).map((descriptor) => descriptor.key);
}

describe('Mutfak rolü — kenar çubuğu', () => {
    it('Mutfak yalnız Menüler bölümünü görür', () => {
        expect(sidebarKeysFor(KITCHEN_PERMISSIONS)).toEqual(['menu']);
    });

    it('editörün kenar çubuğu daralmaz', () => {
        /*
            Bu paket bir rol EKLER, üç rolden bir şey almaz. Editör bugün
            Panom, Menüler, QR, Insights ve Medya görüyor; Şubeler ise
            `workspace.manage` ister ve editörde o izin zaten yok — orada
            yapabileceği hiçbir yazma yok, yani onu çizmek FF-74'ün
            yasakladığı şeydi.
        */
        const keys = sidebarKeysFor(EDITOR_PERMISSIONS);

        expect(keys).toContain('dashboard');
        expect(keys).toContain('menu');
        expect(keys).toContain('qr-codes');
        expect(keys).toContain('analytics');
        expect(keys).toContain('media');
    });

    it('izni olmayan bölüm istendiğinde kullanıcı görebildiği ilk bölüme taşınır', async () => {
        const onNavigateToSection = vi.fn();
        const ctx = {
            can: (permission: string) => KITCHEN_PERMISSIONS.includes(permission),
            onNavigateToSection,
        } as unknown as WorkspaceSectionRuntimeContext;

        // Giriş sonrası varış noktası ve bilinmeyen adresin düştüğü yer.
        render(<>{renderActiveSection('dashboard', ctx)}</>);

        await waitFor(() => {
            expect(onNavigateToSection).toHaveBeenCalledWith('menu');
        });
    });

    it('izni olan bölüm olduğu gibi çizilir ve hiçbir yere taşınmaz', () => {
        const onNavigateToSection = vi.fn();
        const ctx = {
            can: () => true,
            onNavigateToSection,
        } as unknown as WorkspaceSectionRuntimeContext;

        // Kayıt yalnız İZİN kapısıdır; izin varsa araya girmez.
        const dashboard = SECTION_DESCRIPTORS.find((descriptor) => descriptor.key === 'dashboard');

        expect(dashboard?.permission).toBeDefined();
        expect(ctx.can(dashboard?.permission ?? '')).toBe(true);
        expect(onNavigateToSection).not.toHaveBeenCalled();
    });
});
