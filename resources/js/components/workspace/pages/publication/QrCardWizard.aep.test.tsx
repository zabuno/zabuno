import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrCardWizard } from './QrCardWizard';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * MASA KARTI SİHİRBAZININ AEP GRAMERİ — kanonik teslim paketi
 * (`Restoran Paneli v2.dc.html`, `DESIGN_SPEC.md` §4).
 *
 * NEDEN AYRI DOSYA: buradaki iddialar sihirbazın NE ÜRETTİĞİNİ değil, NASIL
 * OKUNDUĞUNU korur. `QrCardWizard` içindeki iş kuralları (hangi uca istek
 * gider, kaç kart basılır) başka sebeplerle değişir; adım göstergesinin
 * biçimi teslim paketi değişince değişir. İkisi aynı dosyada dururken bir
 * sınıf adı kaydığında hangi sözleşmenin kırıldığı anlaşılmıyordu.
 *
 * Restoran sahibinin yolculuğu: kırk masalık salonuna kart bastırmak için
 * dört soruya sırayla cevap verir. Kaçıncı soruda olduğunu ve kaç soru
 * kaldığını GÖREMEZSE formu yarıda bırakır — çünkü "daha ne kadar sürecek"
 * sorusunun cevabı ekranda yoktur. Referansın çok adımlı sihirbazı (§3
 * "Fotoğraftan aktar", `importSteps`) bunu DOLGU ZITLIĞIYLA anlatır: geçerli
 * adım koyu (`ink`) dolgudur, diğerleri soluk (`subtle`) dolgudur ve hepsi
 * 700 ağırlıktadır.
 */
const item: QrCodeItem = {
    id: 4021,
    workspaceId: 7,
    locationId: 3,
    menuId: 11,
    token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    resolverUrl: 'https://zabuno.com/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    tableName: 'T12',
    areaLabel: 'Bahçe',
    destinationType: 'published_menu',
    state: 'active',
};

function classListsOf(root: HTMLElement): string[] {
    const found: string[] = [];

    if (typeof root.className === 'string' && root.className !== '') found.push(root.className);

    root.querySelectorAll<HTMLElement>('*').forEach((element) => {
        if (typeof element.className === 'string' && element.className !== '') {
            found.push(element.className);
        }
    });

    return found;
}

describe('QrCardWizard — adım göstergesi', () => {
    it('geçerli adım DOLU, diğerleri SOLUK dolguyla çizilir', () => {
        /*
            Önceki hâlde geçerli olmayan adımlar hiç dolgusuzdu ve yalnız
            `hover` sırasında bir zemin kazanıyordu. Dokunmatik bir tablette
            `hover` diye bir şey yoktur: sahip dört adımın üçünü zeminsiz,
            soluk bir yazı olarak görüyor ve bunların TIKLANABİLİR olduğunu
            anlamıyordu. Dolgu, "burası bir hedeftir" bilgisini fareye
            sormadan verir.
        */
        render(<QrCardWizard item={item} />);

        const current = screen.getByRole('button', { name: '1. What to print' });
        const other = screen.getByRole('button', { name: '3. Size' });

        expect(current).toHaveClass('bg-fg');
        expect(current).toHaveClass('text-surface');
        expect(other).toHaveClass('bg-surface-subtle');
        expect(other).toHaveClass('text-fg-muted');
    });

    it('bütün adım hapları 700 ağırlıkta ve hap yarıçaplıdır', () => {
        /*
            Referansta adım etiketleri ağırlıkla DEĞİL dolguyla ayrışır; hepsi
            700'dür. Ağırlığı da değiştirmek aynı bilgiyi iki kez söyler ve
            soluk adımı okunmaz hâle getirir (ince + düşük kontrast).

            `font-semibold` (600) AEP ölçeğinde YOKTUR: ölçek 400/500/700'dür
            ve 600 yazmak tarayıcının en yakın kesimi uydurmasına bırakılan
            bir karardır — aynı ekran iki makinede iki farklı ağırlık çizer.
        */
        render(<QrCardWizard item={item} />);

        for (const name of ['1. What to print', '2. Design', '3. Size', '4. Download']) {
            const step = screen.getByRole('button', { name });

            expect(step, `${name} adımı 700 taşımıyor`).toHaveClass('font-bold');
            expect(step, `${name} adımı hap yarıçapı taşımıyor`).toHaveClass('rounded-pill');
        }
    });
});

describe('QrCardWizard — AEP tipografi ve yarıçap sınırları', () => {
    it('hiçbir yerde 600 ağırlık ya da ham `rounded-full` yazmaz', () => {
        // `rounded-full` Tailwind'in kendi ölçeğidir ve token kökünden
        // gelmez: `--radius-pill` değişse bu sınıf değişmez, yani "master
        // değişince hepsi değişir" burada geçerli olmaz.
        const { container } = render(<QrCardWizard item={item} />);

        const classes = classListsOf(container).join(' ');

        expect(classes).not.toMatch(/\bfont-semibold\b/);
        expect(classes).not.toMatch(/\brounded-full\b/);
        expect(classes).not.toMatch(/\buppercase\b/);
    });

    it('form etiketi gövde boyundadır, ölçüm etiketi değil', async () => {
        /*
            `--text-meta` bu sistemde zaman damgası, sayaç ve birim eki
            içindir (`app.css` §text-meta). Bir form etiketi kullanıcının
            CEVAPLAYACAĞI sorudur; ikincil bilgi değildir. İkisi bugün aynı
            1rem'e bağlı olduğu için fark ekranda görünmüyor — ama meta ölçeği
            yarın küçülürse sihirbazın soruları da onunla küçülür.
        */
        const user = userEvent.setup();
        render(<QrCardWizard item={item} />);

        await user.click(screen.getByRole('button', { name: '2. Design' }));

        const label = screen.getByText('Sentence on the card', { selector: 'label' });

        expect(label).toHaveClass('text-body');
        expect(label).not.toHaveClass('text-meta');
    });
});
