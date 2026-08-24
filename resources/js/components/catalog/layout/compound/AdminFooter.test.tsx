import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AdminFooter } from './AdminFooter';

describe('AdminFooter', () => {
    it('renders exactly one footer/contentinfo landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        expect(screen.getAllByRole('contentinfo')).toHaveLength(1);
    });

    it('renders the supplied real product name inside the landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer).toHaveTextContent('Zabuno');
    });

    it('renders the supplied current copyright year inside the landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer).toHaveTextContent('2026');
    });

    it('reflects a different product name and year without fabricating either', () => {
        render(<AdminFooter productName="DestekTesvik" currentYear={2031} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer).toHaveTextContent('DestekTesvik');
        expect(footer).toHaveTextContent('2031');
        expect(footer).not.toHaveTextContent('Zabuno');
        expect(footer).not.toHaveTextContent('2026');
    });

    it('does not render any link inside the footer landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer.querySelectorAll('a')).toHaveLength(0);
    });

    it('does not render a version string inside the footer landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer.textContent ?? '').not.toMatch(/v\d+\.\d+\.\d+/i);
    });

    it('does not use fixed or sticky positioning on the footer landmark', () => {
        render(<AdminFooter productName="Zabuno" currentYear={2026} />);
        const footer = screen.getByRole('contentinfo');
        expect(footer.className ?? '').not.toMatch(/(?:^|\s)fixed(?:\s|$)/);
        expect(footer.className ?? '').not.toMatch(/(?:^|\s)sticky(?:\s|$)/);
    });
});
