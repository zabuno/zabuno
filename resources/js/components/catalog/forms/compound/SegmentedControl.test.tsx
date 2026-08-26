import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { SegmentedControl } from './SegmentedControl';

const OPTIONS = [
    { value: 'classic', label: 'Classic' },
    { value: 'bold', label: 'Bold' },
    { value: 'rounded', label: 'Rounded' },
] as const;

describe('SegmentedControl', () => {
    it('announces itself as a single choice, not three unrelated buttons', () => {
        render(
            <SegmentedControl
                label="QR theme"
                value="bold"
                options={OPTIONS}
                onChange={() => {}}
            />,
        );

        const group = screen.getByRole('radiogroup', { name: 'QR theme' });
        expect(screen.getAllByRole('radio')).toHaveLength(3);
        expect(screen.getByRole('radio', { name: 'Bold' })).toBeChecked();
        expect(group).toBeInTheDocument();
    });

    it('reports the chosen value to the caller', async () => {
        const onChange = vi.fn();
        render(
            <SegmentedControl
                label="QR theme"
                value="classic"
                options={OPTIONS}
                onChange={onChange}
            />,
        );

        await userEvent.click(screen.getByRole('radio', { name: 'Rounded' }));

        expect(onChange).toHaveBeenCalledWith('rounded');
    });

    it('does not signal selection by colour alone', () => {
        render(
            <SegmentedControl
                label="QR theme"
                value="bold"
                options={OPTIONS}
                onChange={() => {}}
            />,
        );

        // Renk körü bir kullanıcı için `aria-checked` tek güvenilir işarettir.
        expect(screen.getByRole('radio', { name: 'Bold' })).toHaveAttribute('aria-checked', 'true');
        expect(screen.getByRole('radio', { name: 'Classic' })).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('refuses interaction when disabled', async () => {
        const onChange = vi.fn();
        render(
            <SegmentedControl
                label="QR theme"
                value="classic"
                options={OPTIONS}
                onChange={onChange}
                disabled
            />,
        );

        await userEvent.click(screen.getByRole('radio', { name: 'Bold' }));

        expect(onChange).not.toHaveBeenCalled();
    });
});
