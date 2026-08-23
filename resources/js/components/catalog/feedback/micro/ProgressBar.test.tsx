import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ProgressBar } from './ProgressBar';

describe('ProgressBar', () => {
    it('exposes the value via aria-valuenow/min/max', () => {
        render(<ProgressBar value={40} label="Uploading" />);
        const bar = screen.getByRole('progressbar');
        expect(bar).toHaveAttribute('aria-valuenow', '40');
        expect(bar).toHaveAttribute('aria-valuemin', '0');
        expect(bar).toHaveAttribute('aria-valuemax', '100');
    });

    it('exposes the label via aria-label', () => {
        render(<ProgressBar value={10} label="Uploading file" />);
        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-label', 'Uploading file');
    });

    it('clamps a value above 100 down to 100', () => {
        render(<ProgressBar value={140} label="Uploading" />);
        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100');
    });

    it('clamps a negative value up to 0', () => {
        render(<ProgressBar value={-10} label="Uploading" />);
        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '0');
    });
});
