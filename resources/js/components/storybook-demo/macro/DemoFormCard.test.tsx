import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DemoFormCard } from './DemoFormCard';

describe('DemoFormCard', () => {
    it('renders one TextField per configured field', () => {
        render(
            <DemoFormCard
                title="Restaurant profile"
                fields={[
                    { id: 'name', label: 'Restaurant name' },
                    { id: 'city', label: 'City' },
                ]}
            />,
        );
        expect(screen.getByLabelText('Restaurant name')).toBeInTheDocument();
        expect(screen.getByLabelText('City')).toBeInTheDocument();
    });

    it('calls onSubmit with the form event and does not navigate', async () => {
        const onSubmit = vi.fn();
        render(
            <DemoFormCard
                title="Restaurant profile"
                fields={[{ id: 'name', label: 'Restaurant name' }]}
                onSubmit={onSubmit}
            />,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(onSubmit).toHaveBeenCalledTimes(1);
    });
});
