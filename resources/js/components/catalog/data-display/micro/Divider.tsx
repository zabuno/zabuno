import { AccessibleSeparator } from '../../../adapters/AccessibleSeparator';

export type DividerOrientation = 'horizontal' | 'vertical';

export type DividerProps = {
    orientation?: DividerOrientation;
    className?: string;
};

/**
 * Micro building block: a semantic divider line. Wraps the existing
 * source-owned separator adapter rather than reimplementing role/orientation
 * wiring (docs/03 ADR-L06 duplicate-prevention).
 */
export function Divider({ orientation = 'horizontal', className }: DividerProps) {
    return (
        <AccessibleSeparator orientation={orientation} decorative={false} className={className} />
    );
}
