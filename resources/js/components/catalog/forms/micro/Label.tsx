import { Label as FlowbiteLabel } from 'flowbite-react';
import type { ComponentProps } from 'react';

import { labelTokenTheme } from '../../../../design-system/flowbite-theme';

export type LabelProps = ComponentProps<typeof FlowbiteLabel> & {
    required?: boolean;
};

/**
 * Micro building block: a form field label. Knows nothing about the field
 * it labels beyond `htmlFor` — no fetch, no route, no business rule.
 */
export function Label({ required = false, children, ...rest }: LabelProps) {
    return (
        <FlowbiteLabel theme={labelTokenTheme} {...rest}>
            {children}
            {required ? (
                <span aria-hidden="true" className="ms-0.5 text-fg-danger ">
                    *
                </span>
            ) : null}
        </FlowbiteLabel>
    );
}
