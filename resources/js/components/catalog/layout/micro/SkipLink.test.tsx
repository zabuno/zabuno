import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SkipLink } from './SkipLink';

describe('SkipLink', () => {
    it('links to the given target id', () => {
        render(<SkipLink targetId="main-content" />);
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );
    });

    it('accepts custom link text', () => {
        render(<SkipLink targetId="main-content">İçeriğe geç</SkipLink>);
        expect(screen.getByRole('link', { name: 'İçeriğe geç' })).toBeInTheDocument();
    });

    it('is visually hidden by default via sr-only', () => {
        render(<SkipLink targetId="main-content" />);
        expect(screen.getByRole('link')).toHaveClass('sr-only');
    });

    /*
        ATLAMA BAĞLANTISI DA BİR KONTROLDÜR (FF-125).

        2026-09-04'te AEP jeton merdiveni tarayıcıda ölçülürken bu bağlantı
        koyu temada `bg-action` (marka sarısı) üstüne `text-white` yazıyordu:
        ~1.75:1. Klavye kullanan birinin her sayfada İLK karşılaştığı kontrol
        buydu ve okunmuyordu.

        Marka sarısının üstündeki tek doğru mürekkep `--color-action-fg`'dir
        (#1c1500, ölçülmüş 11.63:1). Bu yüzden burada donan şey renk DEĞİL,
        kural: bu bağlantı ham renk sınıfı (`bg-white`, `text-white`)
        kullanamaz — jetonu kullanır, böylece tema değiştiğinde birlikte
        döner. Sınıf listesini okumak, jsdom'un `sr-only`yi boyamaması
        yüzünden gerçek pikselden daha güvenilir bir sözleşmedir.
    */
    it('never pairs the action surface with a raw colour utility', () => {
        render(<SkipLink targetId="main-content" />);

        const className = screen.getByRole('link').className;

        expect(className).not.toMatch(/(?:^|:)bg-white\b/);
        expect(className).not.toMatch(/(?:^|:)text-white\b/);
        expect(className).toContain('focus:bg-action');
        expect(className).toContain('focus:text-action-fg');
    });
});
