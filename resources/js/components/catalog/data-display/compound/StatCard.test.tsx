import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatCard } from './StatCard';

describe('StatCard', () => {
    it('renders the label and value', () => {
        render(<StatCard label="Orders today" value="1,204" />);
        expect(screen.getByText('Orders today')).toBeInTheDocument();
        expect(screen.getByText('1,204')).toBeInTheDocument();
    });

    it('composes the StatValue trend indicator when trend is given', () => {
        render(<StatCard label="Orders today" value="1,204" trend="up" />);
        expect(screen.getByText('(trending up)')).toBeInTheDocument();
    });

    it('renders a Skeleton placeholder instead of the value while loading', () => {
        render(<StatCard label="Orders today" value="1,204" loading />);
        expect(screen.queryByText('1,204')).not.toBeInTheDocument();
    });

    /*
        YÜKLENİRKEN KART ZIPLAMASIN (FF-131).

        Yer tutucu sabit `1.75rem` yüksekliğindeydi; rakam AEP metrik
        ölçeğine (2–3rem) çıkınca veri geldiği anda kart bir anda uzuyor,
        altındaki her şey aşağı kayıyor ve kullanıcı tam o sırada
        tıkladığı hedefi kaybediyor. Yer tutucu, yerini tutacağı şeyin
        ölçüsünü BİLMELİ.
    */
    /*
        DESTEK SATIRI (`docs/109` §1, kaynak `stats[].delta`).

        Bir sayaç kartı çıplak bir rakamdır: "46" sahibe menüsünün büyük mü
        küçük mü olduğunu söylemez. Kaynak bu yüzden rakamın altına tek bir
        satır koyuyor. Yuva ZORUNLU DEĞİL — verilmediğinde hiçbir yer
        kaplamaz, çünkü boş bir açıklama satırı kartı uzatır ve yan yana
        duran kartların rakamlarını farklı hizalara düşürür.
    */
    it('destek satırı verilmediğinde kart yalnız etiket ve rakamdan ibarettir', () => {
        render(<StatCard label="Menu items" value={46} />);

        expect(screen.getByText('Menu items').parentElement?.childElementCount).toBe(2);
    });

    it('destek satırı verildiğinde rakamın altında sakin bir ölçü satırı olarak çizilir', () => {
        render(<StatCard label="Menu items" value={46} support="3 hidden" />);

        const support = screen.getByText('3 hidden');

        expect(support).toBeInTheDocument();
        expect(support.className).toContain('text-meta');
    });

    /*
        Rakam henüz gelmemişken onun hakkında bir cümle göstermek, bilinmeyen
        bir şeyi açıklamaya çalışmaktır.
    */
    it('yüklenirken destek satırı çizilmez', () => {
        render(<StatCard label="Menu items" value={46} support="3 hidden" loading />);

        expect(screen.queryByText('3 hidden')).not.toBeInTheDocument();
    });

    it('yükleme yer tutucusu rakamın gerçek yüksekliğini kaplar', () => {
        const { container } = render(<StatCard label="Orders today" value="1,204" loading />);
        const skeleton = container.querySelector('[role="presentation"]') as HTMLElement | null;

        expect(skeleton).not.toBeNull();
        expect(skeleton?.style.height).toBe('var(--aep-text-metric)');
    });
});
