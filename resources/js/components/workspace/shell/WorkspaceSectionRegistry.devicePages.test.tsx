import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

import { renderActiveSection } from './WorkspaceSectionRegistry';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspacePageOverrideMap } from '../pages/pageOverride';

/**
 * CİHAZA ÖZGÜ SAYFA HARİTASI — `docs/153` §5.
 *
 * Bu dosyanın sorusu, kuyruğun nasıl göründüğü değil: haritanın SÖZLEŞMESİ.
 * Üç şey donuyor ve üçü de sessizce bozulabilecek türden:
 *
 * 1. Harita verilmediğinde (telefon) hiçbir şey değişmez — yani bu
 *    mekanizmanın 320 tabanına maliyeti sıfırdır.
 * 2. Verildiğinde yalnız ADI GEÇEN bölüm değişir; ötekiler kayıtlı
 *    çizimleriyle kalır.
 * 3. Harita İZİN KAPISINI AŞMAZ. En pahalı hata bu olurdu: bir ekran
 *    masaüstünde görünür, telefonda görünmez hâle gelirdi — yani yetki
 *    sınırı cihaza göre değişirdi. Sunucu her ucu yine doğrular, ama iki
 *    paketin AYNI kararı vermesi kaydın işidir.
 */

afterEach(() => {
    cleanup();
});

function contextWith(permissions: string[] | null): WorkspaceSectionRuntimeContext {
    return {
        workspaceId: 1,
        catalogPhase: 'menu-catalog',
        subPath: '',
        can: (permission: string) => permissions === null || permissions.includes(permission),
        onNavigateToSection: () => undefined,
        features: {},
        catalogLocationId: 5,
    } as unknown as WorkspaceSectionRuntimeContext;
}

const overrides: WorkspacePageOverrideMap = {
    orders: () => <p>masaüstü siparişler</p>,
};

describe('renderActiveSection — cihaza özgü sayfa haritası', () => {
    it('harita verilmezse bölüm kendi kayıtlı çizimiyle çizilir', () => {
        /*
            TELEFONUN HÂLİ. Bu satır geçmezse, mekanizmanın kendisi 320
            tabanına bir bedel yüklüyor demektir — ve bu paketin ihlal
            edemeyeceği tek kural odur.
        */
        render(<>{renderActiveSection('orders', contextWith(null))}</>);

        expect(screen.queryByText('masaüstü siparişler')).not.toBeInTheDocument();
    });

    it('harita verilirse adı geçen bölümün yerine cihazın sayfası çizilir', () => {
        render(<>{renderActiveSection('orders', contextWith(null), overrides)}</>);

        expect(screen.getByText('masaüstü siparişler')).toBeInTheDocument();
    });

    it('haritada olmayan bölüm kayıtlı çizimiyle kalır', () => {
        render(<>{renderActiveSection('team', contextWith(null), overrides)}</>);

        expect(screen.queryByText('masaüstü siparişler')).not.toBeInTheDocument();
    });

    it('izni olmayan kullanıcıya cihazın sayfası da çizilmez', () => {
        /*
            İzin kapısı haritadan ÖNCE durur. Aksi hâlde `order.view` izni
            olmayan biri, yalnız masaüstünden girdiğinde sipariş ekranını
            görürdü: aynı hesap, aynı yetki, iki farklı cevap.

            Kayıt bu durumda kullanıcıyı görebildiği ilk bölüme taşır ve o
            kare boyunca hiçbir şey çizmez — yönlendirme bir yan etkidir,
            çizim sırasında yapılamaz.
        */
        render(
            <>
                {renderActiveSection(
                    'orders',
                    contextWith(['workspace.view', 'menu.view']),
                    overrides,
                )}
            </>,
        );

        expect(screen.queryByText('masaüstü siparişler')).not.toBeInTheDocument();
    });
});
