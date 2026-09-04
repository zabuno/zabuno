import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { TeamMemberList } from './TeamMemberList';

/**
 * SATIR GRAMERİ — teslim paketinin kendi düzeni (FF-131).
 *
 * Üye listesi her satırı AYRI bir kutu olarak çiziyordu: kendi kenarlığı,
 * kendi köşe yarıçapı, aralarında boşluk. Dört kişilik bir takımda bu dört
 * ayrı kart demekti ve göz her satırda yeniden "bu nedir?" diye başlıyordu.
 *
 * Paketin düzeni bir LİSTEDİR: tek kart, satırlar ince bir ayraçla
 * bölünmüş, her satırda baş harf dairesi. Kutuların sınırı bilgi taşımıyordu
 * — kişiler zaten bir aradaydı; sınır yalnız gürültüydü.
 *
 * Donan şey görsel zevk değil KURAL: satır kart değildir. Bir satırın kendi
 * kenarlığı ve yarıçapı olursa, listedeki her öğe kendi başına bir nesne
 * gibi okunur ve liste olmaktan çıkar.
 */
describe('TeamMemberList — satır grameri', () => {
    const members = [
        { id: 1, name: 'Mehmet Usta', email: 'mehmet@ornek.com', role: 'owner' },
        { id: 2, name: 'Ayşe Yılmaz', email: 'ayse@ornek.com', role: 'editor' },
    ];

    const noop = async () => ({ ok: true }) as never;

    function renderList() {
        return render(
            <TeamMemberList
                status="ready"
                members={members}
                label="Üyeler"
                loadingText="Yükleniyor"
                errorText="Alınamadı"
                emptyText="Kimse yok"
                onRemoveMember={noop}
                removeButtonText="Çıkar"
                removeConfirmText="Onayla"
                removeCancelText="Vazgeç"
                removeBusyText="Çıkarılıyor"
                removeErrorText="Olmadı"
                removeSuccessText="Çıkarıldı"
                removeRetryText="Yeniden dene"
                onTransferOwnership={noop}
                transferButtonText="Sahipliği devret"
                transferDialogTitle="Devret"
                transferDialogBody="Emin misin?"
                transferConfirmText="Evet"
                transferCancelText="Hayır"
                transferBusyText="Devrediliyor"
                transferErrorText="Olmadı"
                transferRetryText="Yeniden dene"
                transferSuccessText="Devredildi"
                onChangeRole={noop}
                assignableRoles={[
                    { value: 'editor', label: 'Editör' },
                    { value: 'viewer', label: 'İzleyici' },
                ]}
                roleLabelFor={(name) => `${name} rolü`}
                roleErrorText="Rol değişmedi"
            />,
        );
    }

    it('satırlar kutu değil, ayraçla bölünmüş liste öğesidir', () => {
        renderList();

        const items = screen.getAllByRole('listitem');

        expect(items.length).toBeGreaterThan(0);

        for (const item of items) {
            // Yasak olan KUTU: her yönden kenarlık ve köşe yarıçapı.
            // `border-border` rengi ayraç için de kullanılır, o yüzden
            // ölçülen şey renk değil GEOMETRİ.
            expect(item.className).not.toMatch(/\brounded-(?:sm|md|lg|xl)\b/);
            expect(item.className).not.toMatch(/(?:^|\s)border(?:-\[|\s|$)/);
            expect(item.className).toMatch(/\bborder-t\b/);
        }
    });

    it('her satır kişinin baş harfini taşır — ad aranmadan satır bulunur', () => {
        renderList();

        const items = screen.getAllByRole('listitem');

        // Baş harf DEKORATİFTİR: adı zaten satırda yazıyor, ekran okuyucu
        // aynı bilgiyi iki kez okumamalı.
        for (const item of items) {
            const initial = item.querySelector('[data-avatar-initial]');

            expect(initial).not.toBeNull();
            expect(initial?.getAttribute('aria-hidden')).toBe('true');
        }
    });
});
