import clsx from 'clsx';

const alertClass = clsx(
    'rounded-md border border-border-danger bg-surface-danger px-3 py-2 text-body text-fg-danger',
);

export function FieldError({ message }: { message: string }) {
    return (
        <p role="alert" className={alertClass}>
            {message}
        </p>
    );
}
