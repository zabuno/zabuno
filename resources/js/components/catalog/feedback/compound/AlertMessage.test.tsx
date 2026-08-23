import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AlertMessage } from './AlertMessage';

describe('AlertMessage', () => {
    it('renders the title and body', () => {
        render(
            <AlertMessage status="info" title="New feature">
                Details here
            </AlertMessage>,
        );
        expect(screen.getByText('New feature')).toBeInTheDocument();
        expect(screen.getByText('Details here')).toBeInTheDocument();
    });

    it('uses an assertive alert role for error status', () => {
        render(<AlertMessage status="error" title="Save failed" />);
        const alert = screen.getByRole('alert');
        expect(alert).toHaveAttribute('aria-live', 'assertive');
    });

    it('uses an assertive alert role for warning status', () => {
        render(<AlertMessage status="warning" title="Quota" />);
        expect(screen.getByRole('alert')).toHaveAttribute('aria-live', 'assertive');
    });

    it('uses a polite status role for info status', () => {
        render(<AlertMessage status="info" title="FYI" />);
        const status = screen.getByRole('status');
        expect(status).toHaveAttribute('aria-live', 'polite');
    });

    it('uses a polite status role for success status', () => {
        render(<AlertMessage status="success" title="Saved" />);
        expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
    });

    it('composes the Badge micro to show the status label', () => {
        render(<AlertMessage status="success" title="Saved" />);
        expect(screen.getByText('Success')).toBeInTheDocument();
    });
});
