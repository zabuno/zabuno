import clsx from 'clsx';

const alertClass = clsx(
    'rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800',
    'dark:border-red-800 dark:bg-red-950 dark:text-red-200',
);

export function FieldError({ message }: { message: string }) {
    return (
        <p role="alert" className={alertClass}>
            {message}
        </p>
    );
}
