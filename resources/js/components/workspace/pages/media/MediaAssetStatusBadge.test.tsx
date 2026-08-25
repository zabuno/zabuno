import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

describe('MediaAssetStatusBadge (MEDIA-UI-STATUS-01)', () => {
    it('maps known intake/scan states to their exact distinct copy', () => {
        const { rerender } = render(<MediaAssetStatusBadge status="quarantined" />);
        expect(screen.getByRole('status').textContent).toBe('Scan pending (quarantined)');

        rerender(<MediaAssetStatusBadge status="scanning" />);
        expect(screen.getByRole('status').textContent).toBe('Scanning in progress');

        expect(screen.getByRole('status').textContent).not.toBe('Scan pending (quarantined)');
    });

    it('maps accepted/processing/ready states to their exact distinct copy', () => {
        const { rerender } = render(<MediaAssetStatusBadge status="accepted" />);
        expect(screen.getByRole('status').textContent).toBe('Accepted — awaiting processing');

        rerender(<MediaAssetStatusBadge status="processing" />);
        expect(screen.getByRole('status').textContent).toBe('Processing');

        rerender(<MediaAssetStatusBadge status="ready" />);
        expect(screen.getByRole('status').textContent).toBe('Ready');
    });

    it('maps rejected/failed states to explicit failure copy with non-success tone', () => {
        const { rerender } = render(<MediaAssetStatusBadge status="rejected" />);
        const rejectedBadge = screen.getByRole('status');
        expect(rejectedBadge.textContent).toBe('Rejected — failed security scan');
        expect(rejectedBadge.className).not.toMatch(/success/i);

        rerender(<MediaAssetStatusBadge status="failed" />);
        const failedBadge = screen.getByRole('status');
        expect(failedBadge.textContent).toBe('Processing failed');
        expect(failedBadge.className).not.toMatch(/success/i);
    });

    it('falls back to a non-empty "Status unavailable" for unknown input and forbids fixed-pixel/breakpoint classes across every variant', () => {
        const initialRender = render(<MediaAssetStatusBadge status="some-unrecognized-value" />);
        let unmount = initialRender.unmount;

        const unknownBadge = screen.getByRole('status');
        expect(unknownBadge.textContent).toBe('Status unavailable');
        expect(unknownBadge.textContent).not.toBe('');
        expect(unknownBadge.textContent).not.toBe('Ready');

        const allStatuses = [
            'quarantined',
            'scanning',
            'rejected',
            'accepted',
            'processing',
            'ready',
            'failed',
            'some-unrecognized-value',
        ];

        allStatuses.forEach((status) => {
            unmount();
            const rendered = render(<MediaAssetStatusBadge status={status} />);
            const badge = screen.getByRole('status');
            expect(badge).toBeInTheDocument();

            const elements = rendered.container.querySelectorAll('[class]');
            elements.forEach((element) => {
                expect(element.className).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
                expect(element.className).not.toMatch(BREAKPOINT_CLASS_PATTERN);
            });

            unmount = rendered.unmount;
        });

        unmount();
    });
});
