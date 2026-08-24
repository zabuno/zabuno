import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Badge } from './Badge';

describe('Badge', () => {
    it('renders its children as text content', () => {
        render(<Badge status="info">Draft</Badge>);
        expect(screen.getByText('Draft')).toBeInTheDocument();
    });

    it.each([['info'], ['success'], ['warning'], ['error']] as const)(
        'renders without throwing for the %s status',
        (status) => {
            render(<Badge status={status}>{status}</Badge>);
            expect(screen.getByText(status)).toBeInTheDocument();
        },
    );
});
