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
    it('yükleme yer tutucusu rakamın gerçek yüksekliğini kaplar', () => {
        const { container } = render(<StatCard label="Orders today" value="1,204" loading />);
        const skeleton = container.querySelector('[role="presentation"]') as HTMLElement | null;

        expect(skeleton).not.toBeNull();
        expect(skeleton?.style.height).toBe('var(--aep-text-metric)');
    });
});
