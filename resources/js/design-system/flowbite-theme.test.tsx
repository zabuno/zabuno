import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Button, TextInput } from 'flowbite-react';

/**
 * Flowbite'ın token köküne bağlandığının kanıtı.
 *
 * Bu test olmadan bağlama sessizce kopabilir: bir Flowbite sürümü tema
 * anahtarlarını değiştirir, override düşer ve arayüz eski ham palete geri
 * döner — hiçbir şey hata vermez, yalnız marka kaybolur.
 *
 * Requirement ID'leri: DS-FLOWBITE-BOUND-10, DS-FLOWBITE-NO-FIXED-HEIGHT-11.
 */
describe('Flowbite token bağlaması', () => {
    // --- DS-FLOWBITE-BOUND-10 ---------------------------------------------
    it('birincil buton markanın eylem token’ını okur, ham paleti değil', () => {
        render(<Button>Publish</Button>);

        const button = screen.getByRole('button', { name: 'Publish' });

        expect(button.className).toContain('bg-action');
        expect(button.className).not.toMatch(/bg-(blue|cyan|gray|primary)-\d/);
    });

    it('ikincil buton yüzey ve kenarlık token’larını okur', () => {
        render(<Button color="light">Cancel</Button>);

        const button = screen.getByRole('button', { name: 'Cancel' });

        expect(button.className).toContain('bg-surface');
        expect(button.className).toContain('border-border');
        expect(button.className).not.toMatch(/(bg|text|border)-gray-\d/);
    });

    it('metin alanı yüzey ve kenarlık token’larını okur', () => {
        render(<TextInput aria-label="Restaurant name" />);

        const input = screen.getByLabelText('Restaurant name');

        expect(input.className).toContain('bg-surface');
        expect(input.className).not.toMatch(/(bg|text|border|placeholder)-gray-\d/);
    });

    // --- DS-FLOWBITE-NO-FIXED-HEIGHT-11 -----------------------------------
    it('hiçbir kontrol sabit piksel yükseklik taşımaz', () => {
        // Sabit yükseklik, yoğunluk token'ını yok sayar: "compact" moda geçen
        // bir kullanıcıda satır küçülür, kontrol küçülmez ve hizalama bozulur.
        render(
            <>
                <Button>Publish</Button>
                <TextInput aria-label="Restaurant name" />
            </>,
        );

        for (const node of [
            screen.getByRole('button', { name: 'Publish' }),
            screen.getByLabelText('Restaurant name'),
        ]) {
            expect(node.className).not.toMatch(/(^|\s)h-\d+(\s|$)/);
            expect(node.className).toContain('min-h-[var(--density-hit-area-min)]');
        }
    });
});
