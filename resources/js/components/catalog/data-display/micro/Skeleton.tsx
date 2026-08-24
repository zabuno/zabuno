import clsx from 'clsx';

export type SkeletonShape = 'text' | 'circle' | 'rect';

export type SkeletonProps = {
    shape?: SkeletonShape;
    width?: string;
    height?: string;
    className?: string;
};

const SHAPE_CLASS: Record<SkeletonShape, string> = {
    text: 'rounded',
    circle: 'rounded-full',
    rect: 'rounded-md',
};

/**
 * Micro building block: a loading placeholder block. Purely presentational —
 * callers decide what layout of skeletons represents their loading content.
 */
export function Skeleton({ shape = 'text', width, height, className }: SkeletonProps) {
    return (
        <span
            role="presentation"
            aria-hidden="true"
            className={clsx(
                'block animate-pulse bg-gray-200 dark:bg-gray-700',
                SHAPE_CLASS[shape],
                !height && shape === 'text' && 'h-4',
                !width && shape === 'text' && 'w-full',
                !width && !height && shape !== 'text' && 'h-10 w-10',
                className,
            )}
            style={{ width, height }}
        />
    );
}
