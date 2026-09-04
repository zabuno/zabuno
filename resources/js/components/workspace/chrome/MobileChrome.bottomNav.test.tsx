import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { House } from '@phosphor-icons/react';

import { MobileBottomNav } from './MobileChrome';

/**
 * Telefon alt gezintisi — teslim paketinin `DESIGN_SPEC.md` §1 "Telefon alt
 * gezintisi".
 *
 * Referans çubuk BEŞ EŞİT sütundur ve aktif hedefi, satırın tamamını
 * boyayarak değil, ikonun altına kayan bir marka HAPIYLA gösterir.
 */
const ITEMS = [
    { key: 'home', label: 'Home', icon: <House size={22} />, onSelect: () => {} },
    { key: 'menus', label: 'Menus', icon: <House size={22} />, onSelect: () => {} },
    { key: 'qr', label: 'QR codes', icon: <House size={22} />, onSelect: () => {} },
    { key: 'insights', label: 'Insights', icon: <House size={22} />, onSelect: () => {} },
];

function renderNav(activeKey = 'menus') {
    return render(
        <MobileBottomNav
            items={ITEMS}
            activeKey={activeKey}
            moreLabel="More"
            onOpenMore={() => {}}
            label="Restaurant admin"
        />,
    );
}

describe('telefon alt gezintisi — AEP grameri (FF-131)', () => {
    /**
     * Sütunlar EŞİTTİR.
     *
     * `justify-between` + `flex-1` görünüşte aynı işi yapar ama etiketler
     * farklı uzunlukta olduğunda hücreler farklı genişler: "QR kodlar" komşusunu
     * daraltır ve beş dokunma hedefi beş farklı büyüklükte olur. Başparmağın
     * kas hafızası sabit bir ızgaraya güvenir; ekranda gezinirken her seferinde
     * hedefe bakmak zorunda kalmamanın tek yolu budur.
     */
    it('beş hedef eşit genişlikte bir ızgara kurar', () => {
        renderNav();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        expect(nav.className).toContain('grid-cols-5');
    });

    /**
     * Hücre yüksekliği yoğunluk jetonundan gelir.
     *
     * `--density-hit-area-min` yalnız 44px'lik tabanı bilir; ferah moda geçen
     * kullanıcı masaüstünde 52px'lik satırlar görürken telefonda 44px'te
     * kalıyordu — yani ayarı yaptığı yerde değişiyor, en çok ihtiyaç duyduğu
     * yerde değişmiyordu. `--control-height` ikisini tek değere bağlar ve
     * tabanı zaten içinde taşır.
     */
    it('hücre yüksekliği yoğunluk ayarını izler', () => {
        renderNav();

        const cell = screen.getByRole('button', { name: 'Home' });

        expect(cell.className).toContain('min-h-[var(--control-height)]');
    });

    /**
     * Aktif hedef bir HAP taşır, boyanmış bir kutu değil.
     *
     * Satırın tamamını tonlamak, beş hücreli dar bir çubukta iki komşu hücrenin
     * arasındaki sınırı siliyordu. Hap ikonu kucaklar: göz çubuğu tararken
     * önce ikon şeridine bakar, işaret de tam oraya iner. Zemin marka sarısı,
     * ikon ölçülmüş mürekkep — marka burada yapısal bir vurgudur.
     */
    it('aktif hedefin işareti ikonu saran bir marka hapıdır', () => {
        renderNav('menus');

        const active = screen.getByRole('button', { name: 'Menus' });
        const pill = active.querySelector('[data-slot="bottom-nav-pill"]') as HTMLElement;

        expect(pill).not.toBeNull();
        expect(pill.className).toContain('rounded-pill');
        expect(pill.className).toContain('bg-action');
        expect(pill.className).toContain('text-action-fg');

        const idlePill = screen
            .getByRole('button', { name: 'Home' })
            .querySelector('[data-slot="bottom-nav-pill"]') as HTMLElement;

        /*
            Pasif hap GÖRÜNMEZ ama YER TUTAR: aksi hâlde aktif hedef değiştikçe
            komşu etiketler birkaç piksel aşağı yukarı zıplardı.
        */
        expect(idlePill).not.toBeNull();
        expect(idlePill.className).not.toContain('bg-action');
    });

    /**
     * "Daha fazla" da bir sütundur ve aynı geometriyi taşır.
     *
     * Dört hedef hap yüksekliğinde, beşincisi değilse çubuğun sağ ucu optik
     * olarak yukarı kayar — beş sütunlu bir ızgarada bir hücrenin farklı
     * davranması, o hücrenin başka bir şey olduğunu düşündürür.
     */
    it('beşinci sütun diğer dördüyle aynı geometriyi taşır', () => {
        renderNav();

        const more = screen.getByRole('button', { name: 'More' });

        expect(more.className).toContain('min-h-[var(--control-height)]');
        expect(more.querySelector('[data-slot="bottom-nav-pill"]')).not.toBeNull();
    });
});
