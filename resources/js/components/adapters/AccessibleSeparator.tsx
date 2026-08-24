import * as React from 'react';
import { Separator } from '../ui/separator';

/**
 * Adapter layer for the source-owned shadcn Separator (docs/03 ADR-L06):
 * the only consumption path for this Radix-based primitive. Flowbite React
 * has no Separator component, so this does not duplicate a primitive that
 * already exists in the primary library.
 */
export function AccessibleSeparator(props: React.ComponentProps<typeof Separator>) {
    return <Separator {...props} />;
}
