import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PlanForm } from './PlanForm';

/**
 * PLAN_FORM_VISIBILITY_RED
 *
 * Live 320x480 evidence on /platform#plans: all six PlanForm input controls
 * and the entitlements textarea render with computed transparent background,
 * 0px border, and only 20px input height — they visually disappear into the
 * dark page. This freezes the narrow behavior/affordance contract: every
 * labelled textbox stays accessible by its current label, and every input/
 * textarea must carry explicit full-width, visible-border, opaque light/dark
 * surface, readable light/dark text, and focus-visible classes, with no
 * Tailwind responsive breakpoint token. Single-line controls need at least a
 * 44px/min-h-11 interaction target; the textarea stays multi-line and
 * visually bounded. No invented plan data is exercised here.
 */

const LABELS = ['Plan name', 'Code', 'Version', 'Amount (minor units)', 'Currency', 'Sort order'];

const ENTITLEMENTS_LABEL = 'Entitlements (one per line)';

const BREAKPOINT_PATTERN = /(^|\s)(sm|md|lg|xl|2xl):/;

const FULL_WIDTH_PATTERN = /(^|\s)w-full(\s|$)/;
const BORDER_PATTERN = /(^|\s)border(-[a-z0-9]+)?(\s|$)/;
const LIGHT_SURFACE_PATTERN = /(^|\s)bg-white(\s|$)/;
const DARK_SURFACE_PATTERN = /(^|\s)dark:bg-gray-\d{3}(\s|$)/;
const LIGHT_TEXT_PATTERN = /(^|\s)text-gray-900(\s|$)/;
const DARK_TEXT_PATTERN = /(^|\s)dark:text-white(\s|$)/;
const FOCUS_VISIBLE_PATTERN = /(^|\s)focus:(ring|border|outline)-/;
const MIN_HEIGHT_PATTERN = /(^|\s)min-h-11(\s|$)/;

function renderForm() {
    render(<PlanForm onSubmit={vi.fn()} submitting={false} />);
}

describe('PlanForm — visible control affordances (S1-WP01A)', () => {
    it('keeps every labelled textbox accessible by its current label', () => {
        renderForm();

        LABELS.forEach((label) => {
            expect(screen.getByLabelText(label)).toBeInTheDocument();
        });
        expect(screen.getByLabelText(ENTITLEMENTS_LABEL)).toBeInTheDocument();
    });

    it('gives every single-line input an explicit full-width, bordered, opaque, readable, focus-visible surface', () => {
        renderForm();

        LABELS.forEach((label) => {
            const input = screen.getByLabelText(label);
            const className = input.className ?? '';

            expect(className).toMatch(FULL_WIDTH_PATTERN);
            expect(className).toMatch(BORDER_PATTERN);
            expect(className).toMatch(LIGHT_SURFACE_PATTERN);
            expect(className).toMatch(DARK_SURFACE_PATTERN);
            expect(className).toMatch(LIGHT_TEXT_PATTERN);
            expect(className).toMatch(DARK_TEXT_PATTERN);
            expect(className).toMatch(FOCUS_VISIBLE_PATTERN);
            expect(className).not.toMatch(BREAKPOINT_PATTERN);
        });
    });

    it('gives every single-line input at least a 44px/min-h-11 interaction target', () => {
        renderForm();

        LABELS.forEach((label) => {
            const input = screen.getByLabelText(label);
            expect(input.className ?? '').toMatch(MIN_HEIGHT_PATTERN);
        });
    });

    it('keeps the entitlements textarea multi-line, visually bounded, and equally visible', () => {
        renderForm();

        const textarea = screen.getByLabelText(ENTITLEMENTS_LABEL);
        const className = textarea.className ?? '';

        expect(textarea.tagName).toBe('TEXTAREA');
        expect(textarea).toHaveAttribute('rows');
        expect(Number(textarea.getAttribute('rows'))).toBeGreaterThan(1);

        expect(className).toMatch(FULL_WIDTH_PATTERN);
        expect(className).toMatch(BORDER_PATTERN);
        expect(className).toMatch(LIGHT_SURFACE_PATTERN);
        expect(className).toMatch(DARK_SURFACE_PATTERN);
        expect(className).toMatch(LIGHT_TEXT_PATTERN);
        expect(className).toMatch(DARK_TEXT_PATTERN);
        expect(className).toMatch(FOCUS_VISIBLE_PATTERN);
        expect(className).not.toMatch(BREAKPOINT_PATTERN);
    });
});
