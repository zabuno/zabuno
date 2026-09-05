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
                brand={null}
                onSaved={vi.fn()}
                activeTab="brand"
                onSelectTab={vi.fn()}
                onNavigateToMedia={vi.fn()}
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

    /*
        KANONİK SEKME KÜMESİ (docs/109, `panel.dc.html` > "Ayarlar").
        Kaynağın kendi dizisi: `['Marka','Çalışma alanı','Plan ve fatura','Denetim']`.

        NEDEN BU TEST: depoda ikinci sekmenin adı "Hesap"tı ve içinde KİŞİSEL
        ad/şifre formu duruyordu. Aynı form Profil ekranında da vardı — yani
        bir ayarın iki evi. Kaynak bu ikiliği çözüyor: Ayarlar ÇALIŞMA ALANINA
        aittir, kişisel olan her şey Profil'dedir. Sekme adı değişmeden içerik
        düzeltilirse kullanıcı "Hesap" yazan yere bakıp kendi adını arar ve
        bulamaz; ad ile içerik birlikte değişmek zorundadır.
    */
    it('sekmeler kaynağın kümesi ve sırasıdır: Marka · Çalışma alanı · Plan ve fatura · Denetim', () => {
        renderPage();

        expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual([
            'Brand',
            'Workspace',
            'Plan & billing',
            'Audit',
        ]);
    });

    /*
        KİŞİSEL FORM AYARLAR'DA ÇİZİLMEZ. "Hesap" sekmesi kaldırıldı ama asıl
        ölçü ad değil DAVRANIŞ: hangi sekmede olursak olalım Ayarlar ekranı
        kişinin adını ya da şifresini sormaz. Bu ölçü olmasaydı sekme yeniden
        adlandırılıp içerik olduğu gibi bırakılabilirdi.
    */
    it('kişisel ad/şifre formu Ayarlar ekranında hiç görünmez', () => {
        for (const tab of ['brand', 'workspace', 'audit'] as const) {
            const view = render(
                <SettingsPage
                    workspaceId={1}
                    brand={null}
                    onSaved={vi.fn()}
                    activeTab={tab}
                    onSelectTab={vi.fn()}
                    onNavigateToMedia={vi.fn()}
                />,
            );

            expect(screen.queryByLabelText('Your name')).toBeNull();
            expect(screen.queryByRole('button', { name: 'Change password' })).toBeNull();

            view.unmount();
        }
    });

    /*
        Ekranın kendi cümlesi kaynağınkidir: "Nadiren açılan işler: marka,
        çalışma alanı, plan, denetim." Eski cümle yalnız marka ve plandan
        bahsediyordu; dört sekmeli bir ekranı iki sekmeymiş gibi tanıtıyordu.
    */
    it('açıklama dört sekmenin dördünü de sayar', () => {
        renderPage();

        expect(
            screen.getByText('Rarely opened: brand, workspace, plan, audit.'),
        ).toBeInTheDocument();
    });
});
