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
});
