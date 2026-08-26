import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Label } from './Label';

describe('Label', () => {
    it('renders its text and associates via htmlFor', () => {
        render(<Label htmlFor="field-id">Restaurant name</Label>);
        const label = screen.getByText('Restaurant name', { exact: false });
        expect(label.closest('label')).toHaveAttribute('for', 'field-id');
    });

    it('renders a decorative required marker that is hidden from screen readers', () => {
        render(
            <Label htmlFor="field-id" required>
                Restaurant name
            </Label>,
        );
        const marker = screen.getByText('*');
        expect(marker).toHaveAttribute('aria-hidden', 'true');
    });

    it('omits the required marker by default', () => {
        render(<Label htmlFor="field-id">Restaurant name</Label>);
        expect(screen.queryByText('*')).not.toBeInTheDocument();
    });
});
