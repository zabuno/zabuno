import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { LoginForm } from './LoginForm';

/**
 * Giriş formu, sağlayıcı OLMADAN da token'lara bağlı kalmalı.
 *
 * Flowbite'ın teması bir React context'i üzerinden dağıtılır ve uygulamada
 * `ThemeRoot` sağlar. Bir test bileşeni doğrudan render ettiğinde o sağlayıcı
 * yoktur; bileşen Flowbite'ı DOĞRUDAN import ediyorsa varsayılan ham palete
 * düşer ve testin doğruladığı görünüm, kullanıcının gördüğü görünüm olmaz.
 *
 * Bu yüzden yüzeyler katalog primitiflerinden geçer: onlar kendi tema
 * dilimlerini prop olarak taşır, yani sağlayıcısız da bağlıdır.
 *
 * Requirement ID'leri: DS-AUTH-BOUND-12.
 */
describe('LoginForm token bağlaması', () => {
    it('alanlar ve etiketler sağlayıcısız render edildiğinde bile token okur', () => {
        render(<LoginForm />);

        const email = screen.getByLabelText(/e-?mail/i);
        expect(email.className).toContain('bg-surface');
        expect(email.className).not.toMatch(/(bg|text|border|placeholder)-gray-\d/);

        const label = screen.getByText(/e-?mail/i);
        expect(label.className).toContain('text-fg');
        expect(label.className).not.toMatch(/text-gray-\d/);
    });
});
